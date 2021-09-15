<?php
return [
    'id' => 'Yii2Swoole',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'easydowork\test\controllers',
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
            'class' => 'easydowork\swoole\Request',
            'cookieValidationKey' => 'B1uah2HVO-CEdFt5o-G46_4-dL3aEo_K',
        ],
        'response' =>[
            'class'=>'easydowork\swoole\Response',
        ],
        'user' => [
            'identityClass' => 'easydowork\test\models\User',
        ],
        'session' => [
            'class'=> 'easydowork\swoole\session\Session',
            'name' => 'EasyDoWorkYii2Session',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    //'class' => 'yii\log\FileTarget',
                    'class' => 'easydowork\swoole\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'class'=>'easydowork\swoole\ErrorHandler',
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