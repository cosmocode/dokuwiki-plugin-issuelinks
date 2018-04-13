<?php

if (!defined('DOKU_INC')) {
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../') . '/');
}
define('NOSESSION', 1);
require_once(DOKU_INC . 'inc/init.php');

class webhook_plugin_issuelinks extends DokuWiki_Plugin
{

    public function run()
    {
        $body = file_get_contents('php://input');

        global $INPUT;
        $userAgent = $INPUT->server->str('HTTP_USER_AGENT');
        dbglog($userAgent);

        $serviceProvider = dokuwiki\plugin\issuelinks\classes\ServiceProvider::getInstance();
        $knownUserAgentPrefixes = $serviceProvider->getWebHookUserAgents();

        $webhookHasBeenHandled = false;
        /** @var helper_plugin_issuelinks_util $util */
        $util = plugin_load('helper', 'issuelinks_util');
        if (!$util) {
            http_status(424);
            echo 'Plugin is deactived at server. Aborting.';
        }

        try {
            foreach ($knownUserAgentPrefixes as $uaPrefix => $serviceClass) {
                if (strpos($userAgent, $uaPrefix) !== 0) {
                    continue;
                }
                $webhookHasBeenHandled = true;
                $service = $serviceClass::getInstance();
                $validationResult = $service->validateWebhook($body);
                if ($validationResult !== true) {
                    $util->sendResponse($validationResult->code, $validationResult->body);
                    return;
                }
                $result = $service->handleWebhook($body);
                break;
            }
        } catch (\Throwable $e) {
            $util->sendResponse(500, $e->getMessage());
            return;
        }

        if (!$webhookHasBeenHandled) {
            dbglog('unknown user agent: ' . $userAgent, __FILE__ . ': ' . __LINE__);
            $util->sendResponse(400, 'unknown user agent');
            return;
        }

        $util->sendResponse($result->code, $result->body);
    }
}

if (!defined('DOKU_TESTING')) {
    // Main
    $hook = new webhook_plugin_issuelinks();
    $hook->run();
}

