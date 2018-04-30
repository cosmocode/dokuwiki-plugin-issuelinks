<?php

namespace dokuwiki\plugin\issuelinks\classes;

/**
 * Class IssueLinksException
 *
 * A translatable exception
 *
 * @package dokuwiki\plugin\issuelinks\classes
 */
class IssueLinksException extends \RuntimeException
{

    protected $trans_prefix = 'Exception: ';

    /**
     * IssueLinksException constructor.
     *
     * @param string $message
     * @param ...string $vars
     */
    public function __construct($message)
    {
        /** @var \helper_plugin_struct $plugin */
        $plugin = plugin_load('helper', 'issuelinks_util');
        $trans = $plugin->getLang($this->trans_prefix . $message);
        if (!$trans) {
            $trans = $message;
        }

        $args = func_get_args();
        array_shift($args);

        $trans = vsprintf($trans, $args);

        parent::__construct($trans);
    }
}
