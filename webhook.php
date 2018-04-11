<?php

if(!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../') . '/');
define('NOSESSION', 1);
require_once(DOKU_INC . 'inc/init.php');

class webhook_plugin_issuelinks extends DokuWiki_Plugin {

    public function run() {
        $body = file_get_contents('php://input');

        global $INPUT;
        $userAgent = $INPUT->server->str('HTTP_USER_AGENT');
        dbglog($userAgent);

        $serviceProvider = dokuwiki\plugin\issuelinks\classes\ServiceProvider::getInstance();
        $knownUserAgentPrefixes = $serviceProvider->getWebHookUserAgents();

        $webhookHasBeenHandled = false;
        foreach ($knownUserAgentPrefixes as $uaPrefix => $serviceClass) {
            if (strpos($userAgent, $uaPrefix) !== 0) {
                continue;
            }
            $webhookHasBeenHandled = true;
            $service = $serviceClass::getInstance();
            try {
                $result = $service->handleWebhook($body);
            } catch (\Throwable $e) {
                http_status(500);
                echo $e->getMessage();
                return;
            }
            break;
        }

        if (!$webhookHasBeenHandled) {
            dbglog('unknown user agent: ' . $userAgent, __FILE__ . ': ' . __LINE__);
            http_status(400);
            echo 'unknown user agent';
            return;
        }

        http_status($result['code']);
        echo $result['body'];
    }
}

if(!defined('DOKU_TESTING')) {
    // Main
    $hook = new webhook_plugin_issuelinks();
    $hook->run();
}

