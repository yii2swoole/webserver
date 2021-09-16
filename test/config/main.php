<?php
return [
    'id' => 'Yii2Swoole',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'yii2swoole\test\controllers',
    'bootstrap' => ['log'],
    'language' => 'zh-CN',
    'vendorPath' => __DIR__.'/../vendor',
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=127.0.0.1;dbname=yii2swoole',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'request' => [
            'class' => 'yii2swoole\webserver\Request',
            'cookieValidationKey' => 'B1uah2HVO-CEdFt5o-G46_4-dL3aEo_K',
        ],
        'response' =>[
            'class'=>'yii2swoole\webserver\Response',
        ],
        'user' => [
            'identityClass' => 'yii2swoole\test\models\User',
        ],
        'session' => [
            'class'=> 'yii2swoole\webserver\session\Session',
            'name' => 'Yii2SwooleSession',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    //'class' => 'yii\log\FileTarget',
                    'class' => 'yii2swoole\webserver\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'class'=>'yii2swoole\webserver\ErrorHandler',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],

    ],
    'params' => [],
];