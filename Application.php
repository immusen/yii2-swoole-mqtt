<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/1o
 * Time: 下午3:50
 */

namespace immusen\mqtt;

use Yii;
use Swoole\Server;
use Swoole\Coroutine\Redis;
use immusen\mqtt\src\MqttTrait;
use immusen\mqtt\src\Task;

class Application extends \yii\base\Application
{

    public $server;

    public function run()
    {
        $port = Yii::$app->params['listen'];
        $server = new Server('0.0.0.0', $port, SWOOLE_PROCESS);
        $server->set([
            'worker_num' => 2,
            'task_worker_num' => 2,
            'open_mqtt_protocol' => 1,
            'task_ipc_mode' => 3,
            'debug_mode' => 1,
           'daemonize' => Yii::$app->params['daemonize'],
            'log_file' => Yii::$app->getRuntimePath() . '/logs/app.log'
        ]);
        $server->on('Start', [$this, 'onStart']);
        $server->on('Task', [$this, 'onTask']);
        $server->on('Finish', [$this, 'onFinish']);
        $server->on('Connect', [$this, 'onConnect']);
        $server->on('Receive', [$this, 'onReceive']);
        $server->on('Close', [$this, 'onClose']);
        $server->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->server = $server;
        //This Redis is php-redis extension.
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        //Mount redis on server, then use $server->redis in worker.
        $server->redis = $redis;
        $this->server->start();
    }

    use MqttTrait;

    public function onStart($server)
    {
        $this->debug("Server Start {$server->master_pid}");
    }

    public function onWorkerStart(Server $server, $id)
    {
        if ($id != 0) return;
        go(function () use ($server) {
            //this Redis is Swoole\Coroutine\Redis.
            $redis = new Redis;
            $result = $redis->connect('127.0.0.1', 6379);
            $server->redis = $redis;
            if (!$result) return;
            while (true) {
                //Redis pub/sub feature; Follow the task structure, Recommend use redis publish like this: redis->publish('async', 'send/sms/15600008888').
                $result = $redis->subscribe(['async']);
                if ($result)
                    $server->task(Task::supervisor($result[2]));
            }
        });
    }

    public function onConnect($server, $fd, $from_id)
    {

    }

    public function onReceive(Server $server, $fd, $from_id, $data)
    {
        $this->mqttDecode($data, $server, $fd);
    }

    public function onClose($server, $fd, $from_id)
    {
        if ($uid = $server->redis->hget('mqtt_online_hash_uid@fd', $fd)) {
            $server->redis->hdel('mqtt_online_hash_fd@uid', $uid);
            $server->redis->hdel('mqtt_online_hash_uid@fd', $fd);
        }
    }

    public function onTask(Server $server, $worker_id, $task_id, $task)
    {
        try {
            $class = new \ReflectionClass(Yii::$app->controllerNamespace . '\\' . ucfirst($task->class) . 'Controller');
            $method = 'action' . ucfirst($task->func);
            if ($class->hasMethod($method)) {
                $actor = $class->getMethod($method);
                return $actor->invokeArgs($class->newInstanceArgs([$server, $task->fd, $task->topic, $task->verb]), [$task->param, $task->body]);
            }
            throw new \Exception($method . ' Undefined');
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }

    public function onFinish(Server $server, $task_id, $data)
    {
        echo 'Task finished ' . PHP_EOL;
        var_dump($data);
    }

    public function handleRequest($_)
    {

    }
}