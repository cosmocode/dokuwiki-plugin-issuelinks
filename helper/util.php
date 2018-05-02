<?php
/**
 * DokuWiki Plugin Issuelinks (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */

class helper_plugin_issuelinks_util extends DokuWiki_Plugin
{

    /**
     * Parse the link header received by DokuHTTPClient
     *
     * @param string $linkHeader
     *
     * @return array
     */
    public function parseHTTPLinkHeaders($linkHeader)
    {
        $links = explode(',', $linkHeader);
        $linkarray = [];
        foreach ($links as $linkstring) {
            list($linktarget, $linkrel) = explode(';', $linkstring);
            $linkrel = substr(trim($linkrel), strlen('rel="'), -1);
            $linktarget = trim($linktarget, '<> ');
            $linkarray[$linkrel] = $linktarget;
        }
        return $linkarray;
    }

    /**
     * @param string $page_id
     * @param int    $revision
     *
     * @return string
     */
    public function getDiffUrl($page_id, $revision = 0)
    {
        if (empty($revision)) {
            $currentRevision = filemtime(wikiFN($page_id));
            $url = wl(
                $page_id,
                [
                    "rev" => $currentRevision,
                    "do" => "diff",
                ],
                true
            );
        } else {
            $changelog = new PageChangelog($page_id);
            $previousRevision = $changelog->getRelativeRevision($revision, -1);
            $url = wl(
                $page_id,
                [
                    "do" => "diff",
                    "rev2[0]" => $revision,
                    "rev2[1]" => $previousRevision,
                    "difftype" => "sidebyside",
                ],
                true
            );
        }
        return $url;
    }

    /**
     * Show an error message for the execption, add trace if $conf['allowdebug']
     *
     * @param Exception $e
     */
    public function reportException(Exception $e)
    {
        msg(hsc($e->getMessage()), -1, $e->getLine(), $e->getFile());
        global $conf;
        if ($conf['allowdebug']) {
            msg('<pre>' . hsc($e->getTraceAsString()) . '</pre>', -1);
        }
    }

    /**
     * @param int   $code
     * @param mixed $msg
     */
    public function sendResponse($code, $msg)
    {
        header('Content-Type: application/json');
        if ((int)$code === 204) {
            http_status(200);
        } else {
            http_status($code);
        }
        global $MSG;
        echo json_encode(['data' => $msg, 'msg' => $MSG]);
    }


    /**
     * Check whether a number or string is a valid unix timestamp
     *
     * Adapted from http://stackoverflow.com/a/2524761/3293343
     *
     * @param string|int $timestamp
     *
     * @return bool
     */
    public function isValidTimeStamp($timestamp)
    {
        return ((string)(int)$timestamp === (string)$timestamp);
    }
}
