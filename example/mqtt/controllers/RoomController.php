<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/14
 * Time: 下午4:13
 */

namespace mqtt\controllers;

use immusen\mqtt\src\Controller;

class RoomController extends Controller
{

    /**
     * Verb: subscribe
     * Client want to get real time online person count, subscribe a topic e.g: room/count/100001
     * @param $room_id
     */
    public function actionCount($room_id)
    {
        //get current count
        $online_count = $this->server->redis->hget('mqtt_sub_set_#room', $room_id);
        //reply current count
        $this->publish([$this->fd], $this->topic, $online_count);
    }

    /**
     * Verb: publish
     * Client who join a room, then send a notice to server by publish, publish to a topic e.g. room/join/100001, and can submit user info into $payload about somebody who join
     * also support redis pub/sub, so you can trigger this method by Yii::$app->redis->publish('async', 'room/join/100001') in your Yii Web application
     * @param $room_id
     * @param $payload
     * @return bool
     */
    public function actionJoin($room_id, $payload = '')
    {
        echo '# room ', $room_id , ' has been viewed one more time, #', $payload, PHP_EOL;
        $count = $this->server->redis->hincrby('mirror_record_hash_#room', $room_id, 1);
        $sub_topic = 'room/count/' . $room_id;
        return $this->publish($this->fdsInRds($sub_topic), $sub_topic, $count);
    }

    /**
     * Verb: publish
     * similar with actionView
     * @param $room_id
     * @return bool
     */
    public function actionLeave($room_id)
    {
        $count = $this->server->redis->hincrby('mirror_record_hash_#room', $room_id, -1);
        $count = $count < 1 ? 0 : $count;
        $sub_topic = 'room/count/' . $room_id;
        return $this->publish($this->fdsInRds($sub_topic), $sub_topic, $count);
    }
}