<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/8
 * Time: 下午6:07
 */

namespace mqtt\controllers;

use immusen\mqtt\base\BaseController;

class NoticeController extends BaseController
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
        $msg = $this->server->redis->get('mqtt_notice_offline_@' . $symbol);
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
        $fds = $this->server->redis->smembers('mqtt_notice_fds_set_@' . $symbol);
        return $this->publish($fds, 'notice/sub/' . $symbol, $payload);
    }
}