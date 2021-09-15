<?php
namespace easydowork\swoole\base;

/**
 * Trait Instance
 * @package easydowork\crontab\base
 */
trait Instance
{
    /**
     * @var static
     */
    private static $_instance;

    public static function getInstance(...$args)
    {
        if (! isset(self::$_instance)) {
            self::$_instance = new static(...$args);
        }
        return self::$_instance;
    }

}