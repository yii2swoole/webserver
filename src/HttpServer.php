<?php

namespace yii2swoole\webserver;

use Yii;
use Swoole\Timer;
use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use yii2swoole\webserver\helper\ConsoleTable;

/**
 * Class HttpServer
 * @property-read Application $app
 * @property-read Server $_server
 * @package yii2swoole\webserver
 */
class HttpServer
{

    public static $version = '1.0.0';

    /**
     * @var Application
     */
    public $app;

    /**
     * @var Server
     */
    protected $_server;

    /**
     * HttpServer constructor.
     */
    public function __construct(Application $app)
    {
        $config = Config::getInstance();

        $this->_server = new Server($config->host, $config->port, $config->mode, $config->sock_type);

        $this->_server->set($config->settings);

        $defaultEvent = [
            'Start'       => [$this, 'onStart'],
            'WorkerStart' => [$this, 'onWorkerStart'],
            'request'     => [$this, 'onRequest'],
        ];

        $eventArray = array_merge($defaultEvent, $config->event);

        foreach ($eventArray as $name => $callback) {
            $this->on($name, $callback);
        }

        $app->swooleServer = $this->_server;

        $this->app = $app;

    }

    /**
     * onStart
     * @param Server $server
     */
    public function onStart(Server $server)
    {
        cli_set_process_title("Yii2 Swoole : master process");
    }

    /**
     * onWorkerStart
     * @param Server $server
     * @param int    $worker_id
     */
    public function onWorkerStart(Server $server, int $worker_id)
    {
        if(function_exists('opcache_reset')){
            opcache_reset();
        }
        if($worker_id >= $server->setting['worker_num']) {
            cli_set_process_title("Yii2 Swoole Work_$worker_id task process");
        } else {
            cli_set_process_title("Yii2 Swoole Work_$worker_id process");
        }
        if($worker_id == 1){
            $webApp = $this->app;
            Timer::tick($webApp->session->getTimeout()*1000, function () use ($webApp){
                return $webApp->session->gcSession($webApp->session->getTimeout());
            });
        }
    }

    /**
     * onRequest
     * @param Request  $request
     * @param Response $response
     */
    public function onRequest(Request $request, Response $response)
    {
        $this->app->getResponse()->setSwooleResponse($response);
        $this->app->getRequest()->setSwooleRequest($request);
        $this->app->run();
    }

    /**
     * getServer
     * @return Server
     */
    public function getServer()
    {
        return $this->_server;
    }

    /**
     * on
     * @param $event
     * @param $callback
     */
    public function on($event, $callback)
    {
        $this->_server->on($event, $callback);
    }

    /**
     * start
     */
    public function start()
    {
        if(Config::getInstance()->beforeStart){
            call_user_func(Config::getInstance()->beforeStart, $this->_server);
        }

        $info = <<<Shell
__   ___ _ ____     ____                     _      
\ \ / (_|_)___ \   / ___|_      _____   ___ | | ___ 
 \ V /| | | __) |  \___ \ \ /\ / / _ \ / _ \| |/ _ \
  | | | | |/ __/    ___) \ V  V / (_) | (_) | |  __/
  |_| |_|_|_____|  |____/ \_/\_/ \___/ \___/|_|\___|
Shell;

        $daemonize = !empty($this->getServer()->setting['daemonize']) ? 'On' : 'Off';

        $info .=  PHP_EOL.(new ConsoleTable(
                ['Host', 'port', 'Daemonize', 'Version'],
                [
                    [$this->getServer()->host, $this->getServer()->port, $daemonize, self::$version]
                ]))->fetch();

        $info .= (new ConsoleTable(
            ['Component', 'Version', 'Require'],
            [
                ['PHP', PHP_VERSION, '7.4 +'],
                ['Yii2', Yii::getVersion(), '2.0.43 +'],
                ['Swoole', SWOOLE_VERSION, '4.6.3 +'],
            ]
        ))->fetch();

        print_success($info);

        $this->_server->start();
    }

}