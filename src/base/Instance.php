<?php
namespace yii2swoole\webserver\base;

/**
 * Trait Instance
 * @package yii2swoole\webserver\base
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