<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/14
 * Time: 4:13 PM
 */

namespace mqtt\controllers;

use immusen\mqtt\src\Controller;

/**
 * Chat room example
 * Class RoomController
 * @package mqtt\controllers
 */
class RoomController extends Controller
{

    /**
     * Verb: subscribe
     * Client want to get real time message or room status
     * @param $room_id
     */
    public function actionSub($room_id)
    {
        //get current online members count
        $count = $this->redis->hget('mqtt_record_hash_#room', $room_id);
        //reply current count
        $this->publish($this->fd, $this->topic, $count ?: 0);
        //or some history message...
        // $this->publish($this->fd, $this->topic, $history_chat_message);
    }

    /**
     * Verb: publish
     * Client who join a room, send a PUBLISH to server, with a topic e.g. room/join/100001, and submit user info into $payload about somebody who join
     * also support redis pub/sub, so you can trigger this method by Yii::$app->redis->publish('async', 'room/join/100001') in your Yii Web application
     * @param $room_id
     * @param $payload
     * @return bool
     */
    public function actionJoin($room_id, $payload = '')
    {
        echo '# room ', $room_id, ' one person joined, #', $payload, PHP_EOL;
        $count = $this->redis->hincrby('mqtt_record_hash_#room', $room_id, 1);
        $sub_topic = 'room/sub/' . $room_id;
        //May need send json string as result to client in real scene. e.g. '{"type":"notice", "count": 100, "ext":"foo"}'
        return $this->publish($this->subFds($sub_topic), $sub_topic, $count);
    }

    /**
     * Verb: publish
     * some one leave room... similar with actionJoin
     * @param $room_id
     * @return bool
     */
    public function actionLeave($room_id)
    {
        $count = $this->redis->hincrby('mqtt_record_hash_#room', $room_id, -1);
        $count = $count < 1 ? 0 : $count;
        $sub_topic = 'room/sub/' . $room_id;
        return $this->publish($this->subFds($sub_topic), $sub_topic, $count);
    }

    /**
     * Verb: publish
     * Message to room, with a topic e.g. room/msg/100001, and submit a pure or json string as payload. e.g. 'hello' or '{"type":"msg","from":"foo","content":"hello!"}'
     * also support publish by Yii::$app->redis->publish('async', 'room/msg/100001/{"type":"msg","from":"foo","content":"hello!"}')
     * @param $room_id
     * @param $payload
     * @return bool
     */
    public function actionMsg($room_id, $payload = '')
    {
        echo '# Msg to ', $room_id, ' by '. $this->verb .' with concent: ' . $payload . PHP_EOL;
        $sub_topic = 'room/sub/' . $room_id;
        return $this->publish($this->subFds($sub_topic), $sub_topic, $payload);
    }
}
