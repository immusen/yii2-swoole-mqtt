<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/15
 * Time: 上午17:55
 */

namespace immusen\mqtt\src;

/**
 * Class Slim PHP Mqtt Model
 * MQTT Version 3.1.1 Plus Errata 01 @see http://docs.oasis-open.org/mqtt/mqtt/v3.1.1/errata01/os/mqtt-v3.1.1-errata01-os-complete.html#_Toc442180866
 * @package immusen\mqtt\src
 */
class Mqtt
{
    /**
     * @var int Message type (1...14)
     */
    private $tp;
    /**
     * @var int QoS (0,1,2)
     */
    private $qos;
    /**
     * @var string topic
     */
    private $topic;
    /**
     * @var int retain
     */
    private $retain;
    /**
     * @var int requested QoS in subscribe
     */
    private $req_qos;
    /**
     * @var string message body
     */
    private $payload;
    /**
     * @var int packet identifier
     */
    private $packet_id;
    /**
     * @var int remaining length
     */
    private $remain_len;
    /**
     * @var array connect info
     */
    private $connect_info;
    /**
     * @var string ack(reply) message
     */
    private $ack;
    /**
     * @var string buffer
     */
    private $buffer;

    /**
     * @var int flag buffer split by fixed length
     */
    const FL_FIXED = 0;
    /**
     * @var int flag buffer split by follow buffer given
     */
    const FL_FOLLOW = 1;

    const TP_CONNECT = 1;
    const TP_CONNACK = 2;
    const TP_PUBLISH = 3;
    const TP_PUBACK = 4;
    const TP_PUBREC = 5;
    const TP_PUBREL = 6;
    const TP_PUBCOMP = 7;
    const TP_SUBSCRIBE = 8;
    const TP_SUBACK = 9;
    const TP_UNSUBSCRIBE = 10;
    const TP_UNSUBACK = 11;
    const TP_PINGREQ = 12;
    const TP_PINGRESP = 13;
    const TP_DISCONNECT = 14;

    /**
     * @var array message type map
     */
    private static $tp_map = [
        1 => 'CONNECT',
        2 => 'CONNACK',
        3 => 'PUBLISH',
        4 => 'PUBACK',
        5 => 'PUBREC',
        6 => 'PUBREL',
        7 => 'PUBCOMP',
        8 => 'SUBSCRIBE',
        9 => 'SUBACK',
        10 => 'UNSUBSCRIBE',
        11 => 'UNSUBACK',
        12 => 'PINGREQ',
        13 => 'PINGRESP',
        14 => 'DISCONNECT',
    ];

    public function __construct($buffer)
    {
        $this->printStr($buffer);
        $this->buffer = $buffer;
        $this->decodeHeader();
        $decoder = 'decode' . ucfirst(strtolower(static::$tp_map[$this->tp]));
        call_user_func([$this, $decoder]);
    }

    /**
     * decode header
     */
    private function decodeHeader()
    {
        $fh = $this->bufPop(static::FL_FIXED);
        $byte = ord($fh);
        $this->tp = ($byte & 0xF0) >> 4;
        $this->dup = ($byte & 0x08) >> 3;
        $this->qos = ($byte & 0x06) >> 1;
        $this->retain = $byte & 0x01;
        $this->remain_len = $this->getRemainLen();
    }

    /**
     * decode connect message
     */
    private function decodeConnect()
    {
        $info['protocol'] = $this->bufPop();
        $info['version'] = ord($this->bufPop(static::FL_FIXED));
        $byte = ord($this->bufPop(static::FL_FIXED));
        $info['auth'] = ($byte & 0x80) >> 7;
        $info['auth'] &= ($byte & 0x40) >> 6;
        $info['will_retain'] = ($byte & 0x20) >> 5;
        $info['will_qos'] = ($byte & 0x18) >> 3;
        $info['will_flag'] = ($byte & 0x04);
        $info['clean_session'] = ($byte & 0x02) >> 1;
        $keep_alive = $this->bufPop(0, 2);
        $info['keep_alive'] = 256 * ord($keep_alive[0]) + ord($keep_alive[1]);
        $info['client_id'] = $this->bufPop();
        if ($info['auth']) {
            $info['username'] = $this->bufPop();
            $info['password'] = $this->bufPop();
        }
        $this->connect_info = $info;
//        if ($info['auth'] === 0) $this->replyConnack(0x04);
        $this->replyConnack(0x00);
    }

    /**
     * decode publish and reply puback/pubrec
     */
    private function decodePublish()
    {
        $this->topic = $this->bufPop();
        if ($this->qos > 0) {
            $this->packet_id = $this->bufPop(static::FL_FIXED, 2);
            if ($this->qos === 1) $this->ack = chr(0x40) . chr(0x02) . $this->packet_id; //puback
            if ($this->qos === 2) $this->ack = chr(0x50) . chr(0x02) . $this->packet_id; //pubrec
        }
        $this->payload = $this->buffer;
    }

    /**
     * decode puback
     */
    private function decodePuback()
    {
        $this->packet_id = $this->bufPop(static::FL_FIXED, 2);
    }

    /**
     * decode pubrec and reply pubrel, 2nd packet of QoS 2 protocol exchange
     */
    private function decodePubrec()
    {
        $this->packet_id = $this->bufPop(static::FL_FIXED, 2);
        $this->ack = chr(0x62) . chr(0x02) . $this->packet_id; //pubrel
    }

    /**
     * decode pubrel and reply pubcomp, 3rd packet of QoS 2 protocol exchange
     * @throws \Exception
     */
    private function decodePubrel()
    {
        if ($this->qos !== 1) throw new \Exception(' bad buffer #' . __METHOD__);
        $this->packet_id = $this->bufPop(static::FL_FIXED, 2);
        $this->ack = chr(0x70) . chr(0x02) . $this->packet_id; //pubcomp
    }

    /**
     * decode pubcomp, 4th and final packet of the QoS 2 protocol exchange
     * @throws \Exception
     */
    private function decodePubcomp()
    {
        $this->packet_id = $this->bufPop(static::FL_FIXED, 2);
    }

    /**
     * decode subscribe, not support multiple topic
     * @throws \Exception
     */
    private function decodeSubscribe()
    {
        if ($this->qos !== 1) throw new \Exception(' bad buffer #' . __METHOD__);
        $this->packet_id = $this->bufPop(static::FL_FIXED, 2);
        $this->topic = $this->bufPop();
        $this->req_qos = ord($this->bufPop(static::FL_FIXED));
        $payload = chr($this->req_qos);
        $this->ack = chr(0x90) . ($payload === '' ? chr(0x02) : chr(0x02 + strlen($payload))) . $this->packet_id . $payload; //suback
    }

    /**
     * decode unsubscribe, not support multiple topic
     * @throws \Exception
     */
    private function decodeUnsubscribe()
    {
        if ($this->qos !== 1) throw new \Exception(' bad buffer #' . __METHOD__);
        $this->packet_id = $this->bufPop(static::FL_FIXED, 2);
        $this->topic = $this->bufPop();
        $this->ack = chr(0xB0) . chr(0x02) . $this->packet_id;  //unsuback
    }

    private function decodePingreq()
    {
        $this->ack = chr(0xD0) . chr(0);
    }

    private function decodeDisconnect()
    {
        //Nothing here, Connect close in server
    }

    public function replyConnack($flag = 0x00)
    {
        $this->ack = chr(0x20) . chr(0x02) . chr(0) . chr($flag);
    }

    private function bufPop($flag = 1, $len = 1)
    {
        if (1 === $flag) $len = 256 * ord($this->bufPop(0)) + ord($this->bufPop(0));
        if (strlen($this->buffer) < $len) return '';
        preg_match('/^([\x{00}-\x{ff}]{' . $len . '})([\x{00}-\x{ff}]*)$/s', $this->buffer, $matches);
        $this->buffer = $matches[2];
        return $matches[1];
    }

    public function getRemainLen()
    {
        $multiplier = 1;
        $value = 0;
        do {
            $encodedByte = ord($this->bufPop(static::FL_FIXED));
            $value += ($encodedByte & 127) * $multiplier;
            if ($multiplier > 128 * 128 * 128) $value = -1;
            $multiplier *= 128;
        } while (($encodedByte & 128) != 0);
        return $value;
    }

    /**
     * @return int
     */
    public function getTp()
    {
        return $this->tp;
    }

    /**
     * @return mixed
     */
    public function getTopic()
    {
        return $this->topic;
    }

    /**
     * @return mixed
     */
    public function getReqqos()
    {
        return $this->req_qos;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return mixed
     */
    public function getConnectInfo()
    {
        return $this->connect_info;
    }

    /**
     * @return string
     */
    public function getAck()
    {
        return $this->ack;
    }

    public function __get($name)
    {
        return call_user_func([$this, 'get' . ucfirst($name)]);
    }

    public function printStr($string)
    {
        $strlen = strlen($string);
        for ($j = 0; $j < $strlen; $j++) {
            $num = ord($string{$j});
            if ($num > 31)
                $chr = $string{$j};
            else
                $chr = " ";
            printf("%4d: %08b : 0x%02x : %s \n", $j, $num, $num, $chr);
        }
    }

    public function __toString()
    {
        return '#TP:' . $this->tp . '  #Topic:' . $this->topic . '  #Msg:' . $this->payload . PHP_EOL;
    }

}