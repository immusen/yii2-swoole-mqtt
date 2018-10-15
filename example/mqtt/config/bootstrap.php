<?php
//This set will insure (new immusen\mqtt\Application($config);) running in portal shell ./mqtt-server
Yii::setAlias('@immusen/mqtt', dirname(dirname(__DIR__)) . '/vendor/immusen/yii2-swoole-mqtt');
Yii::setAlias('@mqtt', dirname(dirname(__DIR__)) . '/mqtt');