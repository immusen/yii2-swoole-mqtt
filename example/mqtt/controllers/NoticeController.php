<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/8
 * Time: 6:07 PM
 */

namespace mqtt\controllers;

use immusen\mqtt\src\Controller;

class NoticeController extends Controller
{

    /**
     * Verb: Subscribe
     * Client subscribe topic: e.g. notice/sub/global
     * @param $symbol
     * @return bool
     */
    public function actionSub($symbol = 'global')
    {
        //Check offline msg demo
        $msg = $this->redis->get('mqtt_notice_offline_@' . $symbol);
        return $this->publish([$this->fd], $this->topic, $msg);
    }


    /**
     * Verb: publish
     * May come forom mqtt publish or redis-publish
     * @param string $symbol
     * @param $payload
     * @return bool
     */
    public function actionSend($symbol = 'global', $payload)
    {
        //get fds demo
        $fds = $this->redis->smembers('mqtt_notice_fds_set_@' . $symbol);
        return $this->publish($fds, 'notice/sub/' . $symbol, $payload);
    }
}