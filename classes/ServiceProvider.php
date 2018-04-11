<?php

namespace dokuwiki\plugin\issuelinks\classes;

use dokuwiki\plugin\issuelinks\services\ServiceInterface;

class ServiceProvider
{

    protected static $instance;
    protected $serviceClasses = [];

    public static function getInstance($forcereload = false) {
        if(null === self::$instance || $forcereload) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getSyntaxKeys() {
        $keys = [];
        foreach ($this->serviceClasses as $className) {

            $syntax = $className::SYNTAX;

            $keys[$syntax] = $className;
        }

        return $keys;
    }

    /**
     * @return ServiceInterface[]
     */
    public function getWebHookUserAgents() {
        $userAgents = [];
        foreach ($this->serviceClasses as $className) {

            $ua = $className::WEBHOOK_UA;

            $userAgents[$ua] = $className;
        }

        return $userAgents;
    }

    protected function __construct()
    {
        $serviceDir = __DIR__ . '/../services';
        $filenames = scandir($serviceDir, SCANDIR_SORT_ASCENDING);
        foreach ($filenames as $filename) {
            if ($filename === '.' || $filename === '..' ) {
                continue;
            }
            list($service, $servicePostfix) = explode('.', $filename, 2);
            if ($servicePostfix !== 'service.php') {
                continue;
            }
            require_once $serviceDir . '/' . $filename;

            $serviceClass = '\\dokuwiki\\plugin\\issuelinks\\services\\' . $service;
            $this->serviceClasses[$serviceClass::ID] = $serviceClass;
        }
    }

    /**
     * @return ServiceInterface[]]
     */
    public function getServices()
    {
        return $this->serviceClasses;
    }
}
