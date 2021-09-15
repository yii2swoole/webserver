<?php
namespace easydowork\swoole;

use Swoole\Http\Server;

/**
 * Class Application
 * @property Server $swooleServer
 * @package easydowork\swoole
 */
class Application extends \yii\web\Application
{
    public $swooleServer;
}