<?php
namespace yii2swoole\webserver;

use yii2swoole\webserver\base\Instance;

/**
 * Class Config
 * @property string $host
 * @property int $port
 * @property int $mode
 * @property int $sock_type
 * @property array $settings
 * @property array $event
 * @property mixed $beforeStart
 * @package yii2swoole\webserver
 */
class Config
{
    use Instance;

    public $host = '127.0.0.1';

    public $port = 9501;

    public $mode = SWOOLE_PROCESS;

    public $sock_type = SWOOLE_SOCK_TCP;

    /**
     * HttpServe配置
     * @var array
     */
    public $settings = [
        'max_request' => 1000,
        'daemonize' => false,
        'log_file' => null,
        'log_date_format' => '%Y-%m-%d %H:%M:%S',//设置 Server 日志时间格式
    ];

    /**
     * HttpServe事件
     * @var array
     */
    public $event = [

    ];

    /**
     * HttpServe 启动之前
     * @var mixed
     */
    public $beforeStart = null;

    /**
     * Config constructor.
     * @param array $config
     */
    private function __construct(array $config=[])
    {
        foreach ($config as $key => $value){
            if(isset($this->{$key})){
                if(is_array($value)){
                    $value = array_merge($this->{$key},$value);
                }
                $this->{$key} = $value;
            }
        }
        $this->checkConfig();
    }

    /**
     * checkConfig
     */
    public function checkConfig()
    {
        if(empty($this->settings['worker_num'])){
            $this->settings['worker_num'] = swoole_cpu_num()*2;
        }
    }


}