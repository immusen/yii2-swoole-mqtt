<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/14
 * Time: 11:07 PM
 */

namespace mqtt\controllers;

use immusen\mqtt\src\Controller;

class CommonController extends Controller
{

    public function actionConnect($fd, $connect_info)
    {
        $uid = isset($connect_info['username']) ? $connect_info['username'] : '';
        echo '#client connect: #FD:' . $fd . '  #C:' . $connect_info['client_id'] . '  #U:' . $uid . PHP_EOL;
        if ($uid) $this->redis->hset('mqtt_online_hash_fd@uid', $uid, $fd);
        $this->redis->hset('mqtt_online_hash_client@fd', $fd, serialize(['u' => $uid, 'c' => $connect_info['client_id']]));
        return true;
    }

    public function actionClose($fd)
    {
        echo '#client closed: ', $fd, PHP_EOL;
        if ($client = $this->redis->hget('mqtt_online_hash_client@fd', $fd)) {
            $client_arr = @unserialize($client);
            $this->redis->hdel('mqtt_online_hash_fd@uid', $client_arr['u']);
            $this->redis->hdel('mqtt_online_hash_client@fd', $fd);
            $this->actionUnsub($fd);
            return true;
        }
        return false;
    }

    public function actionUnsub($fd, $topic = '')
    {
        echo '#client unsub: ', $fd, PHP_EOL;
        if ($topic == '')
            $topics = $this->redis->smembers('mqtt_sub_topics_set_#' . $fd);
        else
            $topics = array($topic);
        if ($topics == false) return false;
        do {
            $topic = array_pop($topics);
            $this->redis->srem('mqtt_sub_fds_set_#' . $topic, $fd);
            $this->redis->srem('mqtt_sub_topics_set_#' . $fd, $topic);
        } while ($topics);
        return true;
    }

    /**
     * Control redis by mqtt or trigger by other caller as a async task
     * @param $verb
     * @param $param
     * @return mixed
     */
    public function actionRedis($verb, $param)
    {
//        if ($this->verb !== Task::VERB_INTERNAL) return false;  // only accept internal call
        return call_user_func_array([$this->redis, $verb], $param);
    }

    public function actionDefault($_)
    {
        //
    }
}