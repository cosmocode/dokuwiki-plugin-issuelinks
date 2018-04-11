<?php
/**
 * DokuWiki Plugin issuelink (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}


use dokuwiki\plugin\issuelinks\classes\Issue;
use dokuwiki\plugin\issuelinks\services\ServiceInterface;


class syntax_plugin_issuelinks extends DokuWiki_Syntax_Plugin {

    protected $syntaxPatterns = [];

    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 50;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $serviceProvider = dokuwiki\plugin\issuelinks\classes\ServiceProvider::getInstance();
        $this->syntaxPatterns = $serviceProvider->getSyntaxKeys();

        foreach ($this->syntaxPatterns as $pattern => $class) {
            $this->Lexer->addSpecialPattern("\[\[$pattern>.*?\]\]", $mode, 'plugin_issuelinks');
        }

    }

    /**
     * Handle matches of the magicmatcher syntax
     *
     * @param string $match The match of the syntax
     * @param int $state The state of the handler
     * @param int $pos The position in the document
     * @param Doku_Handler $handler The handler
     *
     * @return array Data for the renderer
     *
     * @throws Exception
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        list($pmServiceKey,$issueSyntax) = explode('>', trim($match,'[]'));

        /** @var ServiceInterface $serviceClass */
        $serviceClass = $this->syntaxPatterns[$pmServiceKey]::getInstance();

        $issue = $serviceClass->parseIssueSyntax($issueSyntax);

        if(null === $issue) {
            return [$pmServiceKey, $issueSyntax];
        }

        global $ID, $REV, $ACT;
        $isLatest = empty($REV);
        if (act_clean($ACT) === 'show' && $isLatest && page_exists($ID)) {
            $this->saveLinkToDatabase($issue->getServiceName(), $issue->getProject(), $issue->getKey(), $issue->isMergeRequest());
        }

        return array(
            'service' => $issue->getServiceName(),
            'project' => $issue->getProject(),
            'issueId' => $issue->getKey(),
            'isMergeRequest' => $issue->isMergeRequest(),
        );
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string $mode Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array $data The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode !== 'xhtml' || count($data) === 2) {
            $renderer->interwikilink(null, null, 'google.com', implode(' ', $data));
            return true;
        }

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection We already checked for this in the handler */
        $issue = Issue::getInstance($data['service'], $data['project'], $data['issueId'], $data['isMergeRequest']);
        $issue->getFromDB();
        $renderer->doc .= $issue->getIssueLinkHTML();
        return true;
    }

    /**
     * @param string $project
     * @param int    $issue_id
     *
     * @throws InvalidArgumentException
     */
    private function saveLinkToDatabase($pmServiceName, $project, $issue_id, $isMergeRequest) {
        global $ID;
        $currentRev = @filemtime(wikiFN($ID));

        /** @var helper_plugin_issuelinks_db $db_helper */
        $db_helper = $this->loadHelper('issuelinks_db');
        $db_helper->deleteAllIssuePageRevisions($ID, $pmServiceName, $project, $issue_id, $isMergeRequest, 'link');
        $db_helper->savePageRevIssues($ID, $currentRev, $pmServiceName, $project, $issue_id, $isMergeRequest, 'link');
    }

}

// vim:ts=4:sw=4:et:
