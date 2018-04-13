<?php

namespace dokuwiki\plugin\issuelinks\services;

abstract class AbstractService implements ServiceInterface
{
    protected static $instance = [];

    public static function getInstance($forcereload = false) {
        $class = static::class;
        if(empty(self::$instance[$class]) || $forcereload) {
            self::$instance[$class] = new $class();
        }
        return self::$instance[$class];
    }

}
