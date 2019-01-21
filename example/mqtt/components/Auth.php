<?php
/**
 * Created by PhpStorm.
 * User: immusen
 * Date: 2018/10/15
 * Time: 2:30 PM
 */

namespace mqtt\components;

class Auth
{
    /**
     * Client connect Auth
     * @param array $data , connect_info @see \immusen\mqtt\src\Mqtt
     * @return bool
     */
    public static function judge(Array $data)
    {
//        if (!isset($data['username']) && !isset($data['password'])) return 1;
//        $legal_token = \Yii::$app->redis->hget('PASSPORT_HUB_HASH_#MQTT', $data['uid']);
//        if (!$legal_token || $legal_token !== $data['token']) return false;
//        return true;
        return time() % 2 ? true : false;
    }
}