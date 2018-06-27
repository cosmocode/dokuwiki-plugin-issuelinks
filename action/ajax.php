<?php
/**
 * DokuWiki Plugin Issuelinks (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\issuelinks\classes\Issue;
use dokuwiki\plugin\issuelinks\classes\ServiceProvider;

class action_plugin_issuelinks_ajax extends DokuWiki_Action_Plugin
{

    /** @var helper_plugin_issuelinks_util util */
    public $util;

    public function __construct()
    {
        /** @var helper_plugin_issuelinks_util util */
        $this->util = plugin_load('helper', 'issuelinks_util');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjax');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'repoAdminToggle');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'repoAdminOrg');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'asyncImportAllIssues');
    }

    /**
     * Create/Delete the webhook of a repository
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function repoAdminToggle(Doku_Event $event, $param)
    {
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
        if (!auth_isadmin()) {
            $this->util->sendResponse(403, 'Must be Admin');
            return;
        }

        $serviceId = $INPUT->str('servicename');

        $serviceProvider = ServiceProvider::getInstance();
        $services = $serviceProvider->getServices();
        $service = $services[$serviceId]::getInstance();

        $project = $INPUT->str('project');

        if ($INPUT->has('hookid')) {
            $response = $service->deleteWebhook($project, $INPUT->str('hookid'));
        } else {
            $response = $service->createWebhook($project);
        }

        $this->util->sendResponse($response['status'], $response['data']);
    }

    /**
     * Get the repos of an organisation and create HTML from them and return it to the request
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function repoAdminOrg(Doku_Event $event, $param)
    {
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
        try {
            $html = $this->createOrgRepoHTML($serviceId, $organisation);
        } catch (\Throwable $e) {
            $this->util->sendResponse($e->getCode(), $e->getMessage());
        }
        $this->util->sendResponse(200, $html);
    }

    /**
     * Create the HTML of the repositories of an organisation/group of a service.
     *
     * @param string $org the organisation from which to request the repositories
     *
     * @return string
     */
    public function createOrgRepoHTML($serviceId, $org)
    {
        $serviceProvider = ServiceProvider::getInstance();
        $services = $serviceProvider->getServices();
        $service = $services[$serviceId]::getInstance();

        $repos = $service->getListOfAllReposAndHooks($org);
        $html = '<div class="org_repos">';
        $html .= '<p>' . $this->getLang('text:repo admin') . '</p>';
        $orgAdvice = $service->getRepoPageText();
        if ($orgAdvice) {
            $html .= '<p>' . $orgAdvice . '</p>';
        }
        $html .= '<div><ul>';
        usort($repos, function ($repo1, $repo2) {
            return $repo1->displayName < $repo2->displayName ? -1 : 1;
        });
        $importSVG = inlineSVG(__DIR__ . '/../images/import.svg');
        foreach ($repos as $repo) {
            $stateIssue = empty($repo->hookID) ? 'inactive' : 'active';
            if ($repo->error === 403) {
                $stateIssue = 'forbidden';
            } elseif (!empty($repo->error)) {
                continue;
            }
            $repoDisplayName = $repo->displayName;
            $project = $repo->full_name;
            $hookTitle = $repo->error === 403 ? $this->getLang('title:forbidden') : $this->getLang('title:issue hook');
            $html .= "<li><div class='li'>";
            $spanAttributes = [
                'title' => $hookTitle,
                'data-project' => $project,
                'data-id' => $repo->hookID,
                'class' => "repohookstatus $stateIssue issue",
            ];
            $html .= '<span ' . buildAttributes($spanAttributes, true) . '></span>';
            $buttonAttributes = [
                'title' => 'Import all issues of this repository',
                'data-project' => $project,
                'class' => 'issueImport js-importIssues',
            ];
            $html .= '<button ' . buildAttributes($buttonAttributes, true) . ">$importSVG</button>";
            $html .= "<span class='mm_reponame'>$repoDisplayName</span>";
            $html .= '</div></li>';
        }
        $html .= '</ul></div></div>';
        return $html;
    }

    public function asyncImportAllIssues(Doku_Event $event, $param)
    {
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


        ignore_user_abort('true');
        set_time_limit(60 * 20);
        ob_start();
        $this->util->sendResponse(202, 'Importing  issues...');
        header('Connection: close');
        header('Content-Length: ' . ob_get_length());
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
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handleAjax(Doku_Event $event, $param)
    {
        if ($event->data !== 'plugin_issuelinks') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        if (empty($_SERVER['REMOTE_USER'])) {
            $this->util->sendResponse(401, 'Not logged in!');
            return;
        }

        global $INPUT;

        $action = $INPUT->str('issuelinks-action');
        $serviceName = $INPUT->str('issuelinks-service');
        $projectKey = $INPUT->str('issuelinks-project');
        $issueId = $INPUT->str('issuelinks-issueid');
        $isMergeRequest = $INPUT->bool('issuelinks-ismergerequest');

        switch ($action) {
            case 'checkImportStatus':
                list($code, $data) = $this->checkImportStatus($serviceName, $projectKey);
                break;
            case 'issueToolTip':
                list($code, $data) = $this->getIssueTooltipHTML($serviceName, $projectKey, $issueId, $isMergeRequest);
                break;
            case 'getAdditionalIssueData':
                list($code, $data) = $this->getAdditionalIssueData(
                    $serviceName,
                    $projectKey,
                    $issueId,
                    $isMergeRequest
                );
                break;
            default:
                $code = 400;
                $data = 'malformed request';
        }
        $this->util->sendResponse($code, $data);
    }

    protected function checkImportStatus($serviceName, $projectKey)
    {
        if (!auth_isadmin()) {
            return [403, 'Must be Admin'];
        }

        /** @var helper_plugin_issuelinks_data $data */
        $data = plugin_load('helper', 'issuelinks_data');
        $lockID = $data->getImportLockID($serviceName, $projectKey);
        $lockData = $data->getLockContent($lockID);
        if ($lockData === false) {
            msg('Import not locked ' . $lockID, 2);
            return [200, ['status' => 'done']];
        }
        if (!empty($lockData['status']) && $lockData['status'] === 'done') {
            $data->removeLock($lockID);
        }

        return [200, $lockData];
    }

    /**
     * @param $pmServiceName
     * @param $projectKey
     * @param $issueId
     *
     * @return array
     */
    private function getIssueTooltipHTML($pmServiceName, $projectKey, $issueId, $isMergeRequest)
    {
        try {
            $issue = Issue::getInstance($pmServiceName, $projectKey, $issueId, $isMergeRequest);
            $issue->getFromDB();
        } catch (Exception $e) {
            return [400, $e->getMessage()];
        }
        if (!$issue->isValid()) {
            return [404, ''];
        }

        return [200, $issue->buildTooltipHTML()];
    }

    private function getAdditionalIssueData($pmServiceName, $projectKey, $issueId, $isMergeRequest)
    {
        try {
            $issue = Issue::getInstance($pmServiceName, $projectKey, $issueId, $isMergeRequest);
            $issue->getFromDB();
        } catch (Exception $e) {
            return [400, $e->getMessage()];
        }
        if (!$issue->isValid()) {
            return [404, ''];
        }

        return [200, $issue->getAdditionalDataHTML()];
    }
}
