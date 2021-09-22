<?php
namespace yii2swoole\webserver;

use Yii;
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

    /**
     * run
     * @return int
     */
    public function run()
    {
        try {
            return parent::run();
        }catch (\Throwable $e) {
            $this->getErrorHandler()->handleException($e);
        }finally {
            if(!empty($e)){
                Yii::error($e);
            }else{
                $this->getSession()->close();
            }
            $logger = $this->getLog()->getLogger();
            if($logger->messages){
                $logger->flush(true);
            }
        }
    }

    /**
     * end
     * @param int  $status
     * @param null $response
     * @return int
     * @throws ExitException
     */
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