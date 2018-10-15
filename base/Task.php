<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/10
 * Time: 下午1:43
 */

namespace immusen\mqtt\base;

class Task
{
    public $fd = 0;
    public $topic;
    public $class = 'common';
    public $func = 'default';
    public $param = '';
    public $body = '';
    public $verb = 'publish';

    public function __construct($fd, $topic, $payload = '', $verb = 'publish')
    {
        $this->fd = $fd;
        $this->topic = $topic;
        $this->resolve($topic);
        $this->body = $payload;
    }

    /**
     * Mqtt Publish task
     * @param $fd
     * @param $topic
     * @param string $payload
     * @return static
     * @throws \Exception
     */
    public static function publish($fd, $topic, $payload = '')
    {
        if (empty($fd)) throw new \Exception('fd missed');
        return new static($fd, $topic, $payload, 'publish');
    }

    /**
     * Mqtt subscribe task
     * @param $fd
     * @param $topic
     * @return static
     * @throws \Exception
     */
    public static function subscribe($fd, $topic)
    {
        if (empty($fd)) throw new \Exception('fd missed');
        return new static($fd, $topic, 'subscribe');
    }

    /**
     * Redis subscribe task
     *
     * Can play like this: $redis->publish('supervisor', 'channel/play/100011'),
     * then the task will do something like mqtt publish
     *
     * @param $message
     * @return static
     */
    public static function supervisor($message)
    {
        return new static(0, $message, '', 'supervisor');
    }

    private function resolve($topic)
    {
        if (empty($topic)) throw new \Exception('topic missed');
        if (preg_match('/(\w+)\/?(\w*)\/?(.*)/', $topic, $routes)) {
            $this->class = $routes[1];
            $this->func = isset($routes[2]) ? $routes[2] : 'default';
            $this->param = isset($routes[3]) ? $routes[3] : '';
        }
    }

    public function __destruct()
    {
        echo __CLASS__ . ' Destroyed...' . PHP_EOL;
    }
}