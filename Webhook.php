<?php

namespace dokuwiki\plugin\issuelinks;

class Webhook extends \DokuWiki_Plugin
{

    public function run()
    {
        /** @var \helper_plugin_issuelinks_util $util */
        $util = plugin_load('helper', 'issuelinks_util');
        if (!$util) {
            http_status(424);
            echo 'Plugin is deactived at server. Aborting.';
            return;
        }
        $body = file_get_contents('php://input');

        global $INPUT;
        $userAgent = $INPUT->server->str('HTTP_USER_AGENT');
        dbglog($userAgent);
        dbglog($INPUT->server);

        $serviceProvider = classes\ServiceProvider::getInstance();
        $services = $serviceProvider->getServices();
        $handlingService = null;
        foreach ($services as $service) {
            if (!$service::isOurWebhook()) {
                continue;
            }
            $handlingService = $service::getInstance();
            break;
        }


        if ($handlingService === null) {
            dbglog('webhook could not be indentified', __FILE__ . ': ' . __LINE__);
            dbglog('user agent: ' . $userAgent);
            dbglog(json_decode($body, true));
            $util->sendResponse(400, 'unknown webhook');
            return;
        }

        try {
            $validationResult = $handlingService->validateWebhook($body);
            if ($validationResult !== true) {
                $util->sendResponse($validationResult->code, $validationResult->body);
                return;
            }
            $result = $handlingService->handleWebhook($body);
        } catch (\Throwable $e) {
            $util->sendResponse(500, $e->getMessage());
            return;
        }

        $util->sendResponse($result->code, $result->body);
    }
}
