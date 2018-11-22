<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/10
 * Time: 下午2:27
 */

namespace immusen\mqtt\src;

use Swoole\Server;

class Controller
{
    public $server;
    public $fd;
    public $topic;
    public $verb;
    public $redis;

    /**
     * BaseController constructor.
     * @param Server $server
     * @param $fd
     * @param $topic
     * @param string $verb
     */
    public function __construct(Server $server, $fd, $topic, $verb = 'publish')
    {
        $this->server = $server;
        $this->fd = $fd;
        $this->topic = $topic;
        $this->verb = $verb;
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $this->redis = $redis;
    }

    /**
     * Broadcast publish
     * @param $fds
     * @param $topic
     * @param $content
     * @return bool;
     */
    public function publish($fds, $topic, $content, $qos = 0)
    {
        if (!is_array($fds)) $fds = array($fds);
        $msg = $this->buildBuffer($topic, $content, $qos);
        $result = 1;
        $offline = [$topic];
        while ($fds) {
            $fd = (int)array_pop($fds);
            if ($this->server->exist($fd)) {
                $result &= $this->server->send($fd, $msg) ? 1 : 0;
            } else {
                $this->redis->srem('mqtt_sub_fds_set_#' . $topic, $fd);
            }
        }
        return !!$result;
    }

    public function fdsInRds($key, $prefix = '')
    {
        $prefix = $prefix ?: 'mqtt_sub_fds_set_#';
        $res = $this->redis->smembers($prefix . $key);
        if (!$res) return [];
        return $res;
    }

    public function getClientInfo()
    {
        $res = ['u' => '', 'c' => ''];
        $info = $this->redis->hget('mqtt_online_hash_client@fd', $this->fd);
        if ($info)
            $res = @unserialize($info);
        return $res;
    }

    private function buildBuffer($topic, $content, $qos = 0x00, $cmd = 0x30, $retain = 0)
    {
        $buffer = "";
        $buffer .= $topic;
        if ($qos > 0) $buffer .= chr(rand(0, 0xff)) . chr(rand(0, 0xff));
        $buffer .= $content;
        $head = " ";
        $qos = (int)($qos == '' ? 0 : $qos);
        $head{0} = chr($cmd + ($qos * 2));
        $head .= $this->setMsgLength(strlen($buffer) + 2);
        $package = $head . chr(0) . $this->setMsgLength(strlen($topic)) . $buffer;
        return $package;
    }

    private function setMsgLength($len)
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
}