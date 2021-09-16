<?php
namespace yii2swoole\webserver;

use Swoole\Http\Server;

/**
 * Class Application
 * @property Server $swooleServer
 * @package yii2swoole\webserver
 */
class Application extends \yii\web\Application
{
    public $swooleServer;
}