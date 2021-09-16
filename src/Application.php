<?php
namespace yii2swoole\webserver;

use Swoole\Http\Server;
use yii\base\ExitException;

/**
 * Class Application
 * @property Server $swooleServer
 * @package yii2swoole\webserver
 */
class Application extends \yii\web\Application
{
    public $swooleServer;

    public function end($status = 0, $response = null)
    {
        if ($this->state === self::STATE_BEFORE_REQUEST || $this->state === self::STATE_HANDLING_REQUEST) {
            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);
        }

        if ($this->state !== self::STATE_SENDING_RESPONSE && $this->state !== self::STATE_END) {
            $this->state = self::STATE_END;
            $response = $response ?: $this->getResponse();
            $response->send();
        }

        if (YII_ENV_TEST) {
            throw new ExitException($status);
        } else {
            return $status;
        }
    }
}