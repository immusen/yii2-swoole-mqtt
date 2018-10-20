MQTT For Yii2 Base On Swoole 4
==============================
MQTT server for Yii2 base on swoole 4,  Resolve topic as a route reflect into controller/action/param, And support redis pub/sub to trigger async task from your web application

Installation
------------
Install Yii2: [Yii2](https://www.yiiframework.com).

Install swoole: [swoole](https://www.swoole.com), recommend version 4+.

Other dependency: php-redis extension.

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist immusen/yii2-swoole-mqtt "~1.0"
```

or add

```
"immusen/yii2-swoole-mqtt": "~1.0"
```

to the require section of your `composer.json` file.


Test or Usage
-------------

```
# after installation, cd project root path, e.g. cd yii2-advanced-project/
mv vendor/immusen/yii2-swoole-mqtt/example/mqtt ./
mv vendor/immusen/yii2-swoole-mqtt/example/mqtt-server ./
chmod a+x ./mqtt-server
# run:
./mqtt-server
# config :
cat ./mqtt/config/params.php
<?php
return [
    'listen' => 8721,
    'daemonize' => 0,
    'auth' => 1, // config auth class in ./main.php
];
# or coding in ./mqtt/controllers/
```

Test client: MQTTLens, MQTT.fx

Example:
--------
Case A: Subscribe/Publish

> 1, mqtt client subscribe topic: room/count/100011

> 2.1, mqtt client publish: every time publish topic: room/join/100011, the subscribe side will get count+1, or publish topic: room/join/100011 get count -1.

> 2.2, redis client pulish: every time $redis->publish('async', 'room/join/100011'), the subscribe side will get count+1, or $redis->publish('async', 'room/join/100011') get count -1.

Case B: Publish(Notification Or Report)

> mqtt client publish topic: report/coord/100111 and payload: e.g. 110.12345678,30.12345678,0,85

Coding:
------
MQTT subscribe topic:  "channel/count/100001" will handle at:
``` 
    class ChannelController{
        public function actionCount($channel_id){
            echo "client {$this->fd} subscribed the count change of channel {$channel_id}";
        }
    }
```
> //client 1 subscribed the count change of channel 100001


MQTT Publish Topic:  "channel/join/100001"  with payload: "Foo"  will handle at:
```  
    class ChannelController{
        public function actionJoin($channel_id, $who){
            echo "{$who} join in channel {$channel_id}";
        }
    }
```
> // Foo join in channel 100001

MQTT
----

About MQTT: [MQTT Version 3.1.1 Plus Errata 01](http://docs.oasis-open.org/mqtt/mqtt/v3.1.1/mqtt-v3.1.1.html)

> Non-complete implementation of MQTT 3.1.1 in this project, Upgrading...