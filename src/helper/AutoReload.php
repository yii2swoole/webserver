<?php

namespace easydowork\swoole\helper;

use Swoole\Process;
use Swoole\Server;
use Swoole\Timer;
use yii\helpers\FileHelper;

/**
 * Class AutoReload
 * @package easydowork\swoole\helper
 */
class AutoReload
{

    /**
     * @var Server
     */
    public $server;

    /**
     * @var string
     */
    public $dir;

    /**
     * @var Process
     */
    public $hotReloadProcess;

    /**
     * 文件类型
     * @var array
     */
    public $reloadFileTypes = ['.php' => true];

    /**
     * 监听文件
     * @var array
     */
    protected $lastFileList    = [];

    /**
     * 是否正在重启
     */
    protected $reloading = false;


    /**
     * AutoReload constructor.
     * @param Server $server
     */
    public function __construct(Server $server,$dir)
    {
        $this->server = $server;

        $this->dir = $dir;

        $this->hotReloadProcess = new Process([$this,'hotReloadProcessCallBack'], false, 2);

    }

    /**
     * hotReloadProcessCallBack
     * @param Process $worker
     */
    public function hotReloadProcessCallBack(Process $worker)
    {

        $this->run();

        $currentOS = PHP_OS;

        $currentPID = $this->hotReloadProcess->pid;

        cli_set_process_title("Yii2 Swoole Reload Process");

        print_success("hot reload process start at $currentOS, pid: $currentPID. master pid :".$this->server->getMasterPid());
    }

    /**
     * sendReloadSignal
     */
    protected function sendReloadSignal()
    {
        //向主进程发送信号
        Process::kill($this->server->getMasterPid(),SIGUSR1)?print_success('reloaded success ' . date('Y-m-d H:i:s')):print_error('重启失败');
    }

    /**
     * 添加文件类型
     * addFileType
     * @param $type
     * @return $this
     */
    public function addFileType($type)
    {
        $type = trim($type, '.');

        $this->reloadFileTypes['.' . $type] = true;

        return $this;
    }

    /**
     * watch
     */
    public function watch()
    {
        $files = FileHelper::findFiles($this->dir,['only'=>['*.php']]);

        $dirtyList = [];

        foreach ($files as $file) {
            //检测文件类型
            $fileType = strrchr($file, '.');
            if (isset($this->reloadFileTypes[$fileType])) {
                $fileInfo   = new \SplFileInfo($file);
                $mtime  = $fileInfo->getMTime();
                $inode = $fileInfo->getInode();
                $dirtyList[$inode] = $mtime;
            }
        }

        // 当数组中出现脏值则发生了文件变更
        if (array_diff_assoc($dirtyList, $this->lastFileList)) {
            $this->lastFileList = $dirtyList;
            if ($this->reloading) {
                $this->sendReloadSignal();
            }
        }

        $this->reloading = true;
    }

    /**
     * run
     */
    public function run()
    {
        Timer::tick(1000, function () {
            $this->watch();
        });
    }
}