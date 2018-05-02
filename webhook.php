<?php
// phpcs:disable PSR1.Files.SideEffects

if (!defined('DOKU_INC')) {
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../') . '/');
}

define('NOSESSION', 1);
require_once(DOKU_INC . 'inc/init.php');

if (!defined('DOKU_TESTING')) {
    // Main
    $hook = new \dokuwiki\plugin\issuelinks\Webhook();
    $hook->run();
}
