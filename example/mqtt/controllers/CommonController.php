<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/14
 * Time: 下午11:07
 */

namespace mqtt\controllers;

use immusen\mqtt\src\Controller;
use immusen\mqtt\src\Task;

class CommonController extends Controller
{
    public function actionClose($fd)
    {
        if ($uid = $this->server->redis->hget('mqtt_online_hash_uid@fd', $fd)) {
            $this->server->redis->hdel('mqtt_online_hash_fd@uid', $uid);
            $this->server->redis->hdel('mqtt_online_hash_uid@fd', $fd);
            return true;
        }
        return false;
    }

    /**
     * Control redis by mqtt or trigger by other caller as a async task, powerful?
     * @param $verb
     * @param $param
     * @return mixed
     */
    public function actionRedis($verb, $param)
    {
//        if ($this->verb !== Task::VERB_INTERNAL) return false;  // only accept internal call
        return call_user_func_array([$this->server->redis, $verb], $param);
    }

    public function actionDefault($_)
    {
        //
    }
}