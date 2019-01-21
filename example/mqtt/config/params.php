<?php
return [
    'listen' => 8721,
    'daemonize' => 0,
    'auth' => 1, // config auth class in ./main.php
    'worker_num' => 2,
    'task_worker_num' => 2,
    'redis' => [
        'host' => '127.0.0.1',
        'port' => '6379',
//        'auth' => 'passwd',
        'pool_size' => 10,
    ],
];
