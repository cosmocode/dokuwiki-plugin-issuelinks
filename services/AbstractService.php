<?php

namespace dokuwiki\plugin\issuelinks\services;

abstract class AbstractService implements ServiceInterface
{
    protected static $instance;

    public static function getInstance($forcereload = false) {
        if(null === self::$instance || $forcereload) {
            $class = static::class;
            self::$instance = new $class();
        }
        return self::$instance;
    }

}
