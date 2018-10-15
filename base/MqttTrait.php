<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/8
 * Time: 下午1:25
 */

namespace immusen\mqtt\base;

trait MqttTrait
{
    public function mqttDecode($data, $server, $fd)
    {
        $this->printStr($data);
        $data_len_byte = 1;
        $fix_header['data_len'] = $this->getMsgLength($data, $data_len_byte);
        $this->debug($fix_header['data_len'], "get msg length");
        $byte = ord($data[0]);
        $fix_header['type'] = ($byte & 0xF0) >> 4;
        switch ($fix_header['type']) {
            case 1:  //CONNECT
                $connect_info = $this->getConnectInfo(substr($data, 2));
                $authorized = $this->authorization($connect_info);
                if ($authorized === false) {
                    $resp = chr(32) . chr(2) . chr(0) . chr(0x05);
                    $this->printStr($resp);
                    $server->send($fd, $resp);
                    break;
                }
                if ($authorized === true) {
                    $server->redis->hset('mqtt_online_hash_fd@uid', $connect_info['uid'], $fd);
                    $server->redis->hset('mqtt_online_hash_uid@fd', $fd, $connect_info['uid']);
                }
                $resp = chr(32) . chr(2) . chr(0) . chr(0);
                $server->send($fd, $resp);
                break;
            case 3: //PUBLISH
                $fix_header['dup'] = ($byte & 0x08) >> 3;
                $fix_header['qos'] = ($byte & 0x06) >> 1;
                $fix_header['retain'] = $byte & 0x01;
                $offset = 2;
                $topic = $this->decodeString(substr($data, $offset));
                $offset += strlen($topic) + 2;
                $msg = substr($data, $offset);
                $server->task(task::publish($fd, $topic, $msg));
                echo "client msg: $topic\n$msg\n";
                break;
            case 8:  //SUBSCRIBE
                $msg_id = ord($data[3]);
                $fix_header['sign'] = ($byte & 0x02) >> 1;
                $qos = ord($data[$fix_header['data_len']+1]); //TODO QOS...
                if ($fix_header['sign'] == 1) {
                    $topic = substr($data, 6, $fix_header['data_len'] - 5);
                    $server->redis->sadd('mqtt_sub_fds_set_#' . $topic, $fd);
                    $server->task(task::subscribe($fd, $topic));
                }
                $resp = chr(0x90) . chr(3) . chr(0) . chr($msg_id) . chr(0);
                $this->printStr($resp);
                $server->send($fd, $resp);
                break;
            case 10:  //UNSUBSCRIBE
                $topic = $this->decodeString(substr($data, 4));
                $server->redis->srem('mqtt_sub_fds_set_#' . $topic, $fd);
                $resp = chr(0x0B) . chr(2) . chr(0) . chr(0);
                $server->send($fd, $resp);
                break;
            case 12:  //PINGREQ
                $resp = chr(208) . chr(0);
                $server->send($fd, $resp);
                break;
            case 14: //DISCONNECT
                if ($uid = $server->redis->hget('mqtt_online_hash_uid@fd', $fd)) {
                    $server->redis->hdel('mqtt_online_hash_fd@uid', $uid);
                    $server->redis->hdel('mqtt_online_hash_uid@fd', $fd);
                }
                break;
        }
    }

    public function decodeValue($data)
    {
        return 256 * ord($data[0]) + ord($data[1]);
    }

    public function decodeString($data)
    {
        $length = $this->decodeValue($data);
        return substr($data, 2, $length);
    }

    public function getConnectInfo($data)
    {
        $connect_info['protocol_name'] = $this->decodeString($data);
        $offset = strlen($connect_info['protocol_name']) + 2;
        $connect_info['version'] = ord(substr($data, $offset, 1));
        $offset += 1;
        $byte = ord($data[$offset]);
        $connect_info['willRetain'] = ($byte & 0x20 == 0x20);
        $connect_info['willQos'] = ($byte & 0x18 >> 3);
        $connect_info['willFlag'] = ($byte & 0x04 == 0x04);
        $connect_info['cleanStart'] = ($byte & 0x02 == 0x02);
        $hasUid = (($byte & 0x80) >> 7 == 0x01);
        $hasToken = (($byte & 0x40) >> 6 == 0x01);
        $offset += 1;
        $connect_info['keepalive'] = $this->decodeValue(substr($data, $offset, 2));
        $offset += 2;
        $connect_info['clientId'] = $this->decodeString(substr($data, $offset));
        if ($hasUid && $hasToken) {
            $offset += strlen($connect_info['clientId']) + 2;
            $connect_info['uid'] = $this->decodeString(substr($data, $offset));
            $offset += strlen($connect_info['uid']) + 2;
            $connect_info['token'] = $this->decodeString(substr($data, $offset));
        }
        return $connect_info;
    }

    public function debug($str, $title = "Debug")
    {
        echo "-------------------------------\n";
        echo '[' . time() . "] " . $title . ':[' . $str . "]\n";
        echo "-------------------------------\n";
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

    public function getMsgLength(&$msg, &$i)
    {
        $multiplier = 1;
        $value = 0;
        do {
            $digit = ord($msg{$i});
            $value += ($digit & 127) * $multiplier;
            $multiplier *= 128;
            $i++;
        } while (($digit & 128) != 0);
        return $value;
    }

    public function setMsgLength($len)
    {
        $string = "";
        do {
            $digit = $len % 128;
            $len = $len >> 7;
            if ($len > 0)
                $digit = ($digit | 0x80);
            $string .= chr($digit);
        } while ($len > 0);
        return $string;
    }

    public function strWriteString($str, &$i)
    {
        $ret = " ";
        $len = strlen($str);
        $msb = $len >> 8;
        $lsb = $len % 256;
        $ret = chr($msb);
        $ret .= chr($lsb);
        $ret .= $str;
        $i += ($len + 2);
        return $ret;
    }

    public function authorization($data)
    {
        if (!isset($data['uid']) || !isset($data['token'])) return 1;
        $legal_token = $this->server->redis->hget('PASSPORT_HUB_HASH_#MQTT', $data['uid']);
        if (!$legal_token || $legal_token !== $data['token']) return false;
        return true;
    }

}