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
    }

    /**
     * Broadcast publish
     * @param $fds
     * @param $topic
     * @param $connect
     * @return bool;
     */
    public function publish($fds, $topic, $connect)
    {
        if (!is_array($fds)) $fds = array($fds);
        $msg = $this->buildBuffer($topic, $connect);
        $result = 1;
        do {
            $fd = array_pop($fds);
            if ($this->server->exist($fd))
                $result &= $this->server->send($fd, $msg) ? 1 : 0;
        } while ($fds);
        return !!$result;
    }

    public function fdsInRds($key, $prefix = '')
    {
        $prefix = $prefix ?: 'mqtt_sub_fds_set_#';
        return $this->server->redis->smembers($prefix . $key);
    }

    private function buildBuffer($topic, $content, $cmd = 0x30, $qos = 0, $retain = 0)
    {
        $buffer = "";
        $buffer .= $topic;
        $buffer .= $content;
        $head = " ";
        $head{0} = chr($cmd);
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