<?php
/**
 * DokuWiki Plugin Issuelinks (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

if (!defined('DOKU_INC')) die();

use dokuwiki\plugin\issuelinks\classes\Issue;
use dokuwiki\plugin\issuelinks\classes\ServiceProvider;

class action_plugin_issuelinks_ajax extends DokuWiki_Action_Plugin {

    /** @var helper_plugin_issuelinks_util util */
    public $util;

    public function __construct() {
        /** @var helper_plugin_issuelinks_util util */
        $this->util = plugin_load('helper', 'issuelinks_util');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'repo_admin_toggle');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'repo_admin_org');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'asyncImportAllIssues');
    }

    /**
     * Create/Delete the webhook of a repository
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function repo_admin_toggle(Doku_Event $event, $param) {
        if ($event->data !== 'issuelinks_repo_admin_toggle') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        if (empty($_SERVER['REMOTE_USER'])) {
            $this->util->sendResponse(401, 'Not logged in!');
            return;
        }

        global $INPUT, $INFO;
        if (false && !$INFO['isadmin']) { // FIXME
            $this->util->sendResponse(403, 'Must be Admin');
            return;
        }

        $serviceId = $INPUT->str('servicename');

        $serviceProvider = ServiceProvider::getInstance();
        $services = $serviceProvider->getServices();
        $service = $services[$serviceId]::getInstance();

        $organisation = $INPUT->str('org');
        $repo = $INPUT->str('repo');

        if ($INPUT->has('hookid')) {
            $response = $service->deleteWebhook($organisation, $repo, $INPUT->str('hookid'));
        } else {
            $hookType = $INPUT->str('hooktype');
            $response = $service->createWebhook($organisation, $repo, $hookType);
        }

        // jira: https://developer.atlassian.com/cloud/jira/platform/webhooks/#registering-a-webhook-via-the-jira-rest-api

        $this->util->sendResponse($response['status'], $response['data']);
    }

    /**
     * Get the repos of an organisation and create HTML from them and return it to the request
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function repo_admin_org(Doku_Event $event, $param) {
        if ($event->data !== 'issuelinks_repo_admin_getorg') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        if (empty($_SERVER['REMOTE_USER'])) {
            $this->util->sendResponse(401, 'Not logged in!');
            return;
        }

        global $INPUT;
        if (!auth_isadmin()) {
            $this->util->sendResponse(403, 'Must be Admin');
            return;
        }

        $serviceId = $INPUT->str('servicename');
        $organisation = $INPUT->str('org');
        $html = $this->createOrgRepoHTML($serviceId, $organisation);
        $this->util->sendResponse(200, $html);
    }

    /**
     * Create the HTML of the repositories of an organisation/group of a service.
     *
     * @param string $org the organisation from which to request the repositories
     * @return string
     */
    public function createOrgRepoHTML($serviceId, $org) {
        $serviceProvider = ServiceProvider::getInstance();
        $services = $serviceProvider->getServices();
        $service = $services[$serviceId]::getInstance();

        $repos = $service->getListOfAllReposAndHooks($org);
        $html = '<div class="org_repos">';
        $html .= '<p>Below are the repositories of the organisation to which the authorized user has access to. Click on the icon to create/delete the webhook.</p>';
        $html .= '<div><ul>';
        usort($repos, function ($repo1, $repo2) {return $repo1->displayName < $repo2->displayName ? -1 : 1;});
        foreach ($repos as $repo) {
            $stateIssue = empty($repo->hookID) ? 'inactive' : 'active';
            if ($repo->error === 403) {
                $stateIssue = 'forbidden';
            } elseif (!empty($repo->error)) {
                continue;
            }
            $reponame = $repo->displayName;
            $project = $repo->full_name;
            $issueHookID = empty($repo->hookID) ? '' : "data-id='$repo->hookID'";
            $issueHookTitle = $repo->error === 403 ? 'The associated account has insufficient rights for this action' : 'Toggle the hook for issue-events';
            $html .= "<li><div class='li'>";
            $html .= "<span title='$issueHookTitle' data-org='$org' data-repo='$reponame' $issueHookID class='repohookstatus $stateIssue issue'></span>";
            $importSVG = inlineSVG(__DIR__ . '/../images/import.svg');
            $html .= "<button title='Import all issues of this repository' data-project='$project' class='issueImport js-importIssues'>$importSVG</button>";
            $html .= "<span class='mm_reponame'>$reponame</span>";
            $html .= '</div></li>';
        }
        $html .= '</ul></div></div>';
        return $html;
    }

    public function asyncImportAllIssues(Doku_Event $event, $param) {
        if ($event->data !== 'issuelinks_import_all_issues_async') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        if (!auth_isadmin()) {
            $this->util->sendResponse(403, 'Must be Admin');
        }
        global $INPUT;
        $serviceName = $INPUT->str('servicename');
        $projectKey = $INPUT->str('project');

        // fixme check if $serviceName and $projectKey exist
        if (empty($serviceName) || empty($projectKey)) {
            $this->util->sendResponse(400, 'service or project is missing');
        }


        ignore_user_abort(true);
        set_time_limit(60*20);
        ob_start();
        $this->util->sendResponse(202, 'Importing  issues...');
        header('Connection: close');
        header('Content-Length: '.ob_get_length());
        ob_end_flush();
        ob_end_flush();
        flush();

        /** @var helper_plugin_issuelinks_data $data */
        $data = plugin_load('helper', 'issuelinks_data');
        $data->importAllIssues($serviceName, $projectKey);

    }

    /**
     * Pass Ajax call to a type
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_ajax(Doku_Event $event, $param) {
        if ($event->data !== 'plugin_issuelinks') return;
        $event->preventDefault();
        $event->stopPropagation();

        if (empty($_SERVER['REMOTE_USER'])) {
            $this->util->sendResponse(401, 'Not logged in!');
            return;
        }

        global $INPUT;
        $sectok = $INPUT->str('sectok'); // fixme check for admin!?
//        if (!checkSecurityToken($sectok)) { // FIXME
//            $this->util->sendResponse(403, 'Security-Token invalid!');
//            return;
//        }

        $action = $INPUT->str('issuelinks-action');
        $serviceName = $INPUT->str('issuelinks-service');
        $projectKey = $INPUT->str('issuelinks-project');
        $issueId = $INPUT->str('issuelinks-issueid');
        $isMergeRequest = $INPUT->bool('issuelinks-ismergerequest');

        // FIXME remove duplicate merge-request detection
//        if ($issueId[0] === '!') {
//            $isMergeRequest = true;
//            $issueId = substr($issueId, 1);
//        }
        $page = $INPUT->str('issuelinks-page');
        switch ($action) {
            case 'checkImportStatus':
                list($code, $data) = $this->checkImportStatus($serviceName, $projectKey);
                break;
            case 'abortIssueImport':
                list($code, $data) = $this->abortIssueImport();
                break;
            case 'abortCommitImport':
                list($code, $data) = $this->abortCommitImport();
                break;
            case 'getProjectIssues':
                list($code, $data) = $this->getProjectIssues($serviceName, $projectKey);
                break;
            case 'relatedIssueScoreDetails':
                list($code, $data) = $this->getRelatedIssueScoreDetails($serviceName, $projectKey, $issueId, $isMergeRequest);
                break;
            case 'updateIssue':
                list($code, $data) = $this->updateIssueSession($serviceName, $projectKey, $issueId, $isMergeRequest, $page);
                break;
            case 'getSuggestions':
                list($code, $data) = $this->getSuggestionsPage($page);
                break;
            case 'addIssue':
                list($code, $data) = $this->addIssue($serviceName, $projectKey, $issueId, $isMergeRequest, $page);
                break;
            case 'removeIssue':
                list($code, $data) = $this->removeIssue($serviceName, $projectKey, $issueId, $isMergeRequest, $page);
                break;
            case 'issueToolTip':
                list($code, $data) = $this->getIssueTooltipHTML($serviceName, $projectKey, $issueId, $isMergeRequest);
                break;
            case 'getAdditionalIssueData':
                list($code, $data) = $this->getAdditionalIssueData($serviceName, $projectKey, $issueId, $isMergeRequest);
                break;
            default:
                $code = 400;
                $data = 'malformed request';
        }
        $this->util->sendResponse($code, $data);
    }

    protected function checkImportStatus($serviceName, $projectKey) {
        if (!auth_isadmin()) {
            return [403, 'Must be Admin'];
        }

        /** @var helper_plugin_issuelinks_data $data */
        $data = plugin_load('helper', 'issuelinks_data');
        $lockID = $data->getImportLockID($serviceName, $projectKey);
        $lockData = $data->getLockContent($lockID);
        if ($lockData === false) {
            msg('Import not locked ' . $lockID,2);
            return [200, ['status' => 'done']];
        }
        if (!empty($lockData['status']) && $lockData['status'] === 'done') {
            $data->removeLock($lockID);
        }

        return [200, $lockData];
    }

    protected function abortIssueImport() {
        if (!auth_ismanager()) {
            return array(403,'');
        }
        /** @var helper_plugin_magicmatcher_data $data */
        $data = plugin_load('helper', 'magicmatcher_data');
        @$data->unlockImport('jiraimport');
        $data->lockImport('issueImportAborted');
        global $INPUT, $conf;
        if ($conf['allowdebug']) {
            $user = $INPUT->server->str('REMOTE_USER') . ' (' . clientIP() . ')';
            dbglog("Abort received by $user!");
        }
        return array(200, array());
    }

    protected function abortCommitImport() {
        if (!auth_ismanager()) {
            return array(403,'');
        }
        /** @var helper_plugin_magicmatcher_data $data */
        $data = plugin_load('helper', 'magicmatcher_data');
        @$data->unlockImport('commitImport');
        $data->lockImport('commitImportAborted');
        global $INPUT, $conf;
        if ($conf['allowdebug']) {
            $user = $INPUT->server->str('REMOTE_USER') . ' (' . clientIP() . ')';
            dbglog("Abort received by $user!");
        }
        return array(200, array());
    }

    protected function removeIssue($pmServiceName, $projectKey, $issueId, $isMergeRequest, $page) {
        if (auth_quickaclcheck($page) < AUTH_EDIT) {
            return array(403, 'You do not have the rights to edit this page!');
        }
        /** @var helper_plugin_magicmatcher_db $db_helper */
        $db_helper = $this->loadHelper('magicmatcher_db');
        $db_helper->deleteAllIssuePageRevisions($page, $pmServiceName, $projectKey, $issueId, $isMergeRequest, 'context');
        return array(200, '');
    }

    protected function addIssue($pmServiceName, $projectKey, $issueId, $isMergeRequest, $page) {
        if (auth_quickaclcheck($page) < AUTH_EDIT) {
            return array(403, 'You do not have the rights to edit this page!');
        }

        /** @var helper_plugin_magicmatcher_db $db_helper */
        $db_helper = $this->loadHelper('magicmatcher_db');
        $db_helper->savePageRevIssues($page, 0, $pmServiceName, $projectKey, $issueId, $isMergeRequest, 'context');

        /** @var syntax_plugin_magicmatcher_issuelist $issuelist */
        $issuelist = plugin_load('syntax', 'magicmatcher_issuelist');
        return array(200, array('issueListItems' => $issuelist->getIssueListItems($page)));
    }

    protected function getProjectIssues($pmServiceName, $projectKey) {
        /** @var helper_plugin_magicmatcher_data $data */
        $data = plugin_load('helper', 'magicmatcher_data');
        $issues = $data->assembleProjectIssueOptions($pmServiceName, $projectKey);
        $options = '<option></option>';
        foreach ($issues as $issueNumber => $issue_option) {
            if (empty($issue_option)) {
                continue;
            }
            $option = "<option value='$issueNumber'";
            $option .= " data-project='{$issue_option['attrs']['data-project']}'";
            $option .= " data-status='{$issue_option['attrs']['data-status']}'";
            $option .= '>';
            $option .= $issue_option['label'];
            $option .= '</option>';
            $options.= $option;
        }
        return array(200, array('issue_options' => $options));
    }

    protected function getRelatedIssueScoreDetails($pmServiceName, $projectKey, $issueId, $isMergeRequest) {
        global $INPUT;
        /** @var helper_plugin_magicmatcher_db $db_helper */
        $db_helper = $this->loadHelper('magicmatcher_db');
        $originalIssue = Issue::getInstance($pmServiceName, $projectKey, $issueId, $isMergeRequest);
        $relatedService = $INPUT->str('relatedService');
        $relatedProject = $INPUT->str('relatedProject');
        $relatedIssueID = $INPUT->str('relatedIssueID');
        $relatedIssue = Issue::getInstance($relatedService, $relatedProject, $relatedIssueID, $isMergeRequest);
        $sharedFiles = $db_helper->getSharedFiles($originalIssue, $relatedIssue);
        $html = '<ul>';
        foreach ($sharedFiles as $file) {
            $html .= "<li>$file[sharedWeight] - $file[rmService] - $file[repository] - $file[path]</li>";
        }
        $html .= '</ul>';
        return array(200, $html);
    }

    protected function updateIssueSession($pmServiceName, $projectKey, $issueId, $isMergeRequest, $page) {
        session_start();
        if (empty($issueId)) {
            $this->util->unsetMMSession();
            /** @var syntax_plugin_magicmatcher_issuelist $issuelist */
            $issuelist = plugin_load('syntax', 'magicmatcher_issuelist');
            $issueListItems = $issuelist->getIssueListItems($page);
            return array(200, array('issue' => '', 'issueListItems' => $issueListItems));
        }


        /** @var helper_plugin_magicmatcher_data $data_helper */
        $data_helper = $this->loadHelper('magicmatcher_data');
        $issue = $data_helper->getIssue($pmServiceName, $projectKey, $issueId, $isMergeRequest);
        $status = 200;
        if (!$issue->isValid()) {
            $this->util->unsetMMSession();
            $status = 404;
        } else {
            $_SESSION['MAGICMATCHER_PMSERVICE'] = $pmServiceName;
            $_SESSION['MAGICMATCHER_PROJECT'] = $projectKey;
            $_SESSION['MAGICMATCHER_ISSUE'] = $issueId;
            $_SESSION['MAGICMATCHER_ISMERGEREQUEST'] = $isMergeRequest;
        }

        /** @var syntax_plugin_magicmatcher_issuelist $issuelist */
        $issuelist = plugin_load('syntax', 'magicmatcher_issuelist');
        $issueListItems = $issuelist->getIssueListItems($page);

        return array($status, array('issue' => $issue, 'issueListItems' => $issueListItems));
    }

    protected function getSuggestionsPage($page) {
        /** @var action_plugin_magicmatcher_suggestion $suggestions */
        $suggestions = plugin_load('action', 'magicmatcher_suggestion');
        return array(200, $suggestions->getSuggestionsPage($page));
    }

    /**
     * @param $pmServiceName
     * @param $projectKey
     * @param $issueId
     *
     * @return array
     */
    private function getIssueTooltipHTML($pmServiceName, $projectKey, $issueId, $isMergeRequest) {
        try {
            $issue = Issue::getInstance($pmServiceName, $projectKey, $issueId, $isMergeRequest);
            $issue->getFromDB();
        } catch (Exception $e) {
            return array(400, $e->getMessage());
        }
//        if (!$issue->isValid()) {
//            return array(404, '');
//        }

        return array(200, $issue->buildTooltipHTML());
    }

    private function getAdditionalIssueData($pmServiceName, $projectKey, $issueId, $isMergeRequest) {
        try {
            $issue = Issue::getInstance($pmServiceName, $projectKey, $issueId, $isMergeRequest);
        } catch (Exception $e) {
            return array(400, $e->getMessage());
        }
//        if (!$issue->isValid()) {
//            return array(404, '');
//        }

        return array(200, $issue->getAdditionalDataHTML());
    }

}
