<?php
/**
 * Created by IntelliJ IDEA.
 * User: michael
 * Date: 4/16/18
 * Time: 7:57 AM
 */

namespace dokuwiki\plugin\issuelinks\services;


use dokuwiki\Form\Form;
use dokuwiki\plugin\issuelinks\classes\ExternalServerException;
use dokuwiki\plugin\issuelinks\classes\HTTPRequestException;
use dokuwiki\plugin\issuelinks\classes\Issue;
use dokuwiki\plugin\issuelinks\classes\Repository;
use dokuwiki\plugin\issuelinks\classes\RequestResult;

class Jira extends AbstractService
{

    const SYNTAX = 'jira';
    const DISPLAY_NAME = 'Jira';
    const ID = 'jira';

    protected $dokuHTTPClient;
    protected $jiraUrl;
    protected $token;
    protected $configError;
    protected $authUser;
    protected $total;

    // FIXME should this be rather protected?
    public function __construct()
    {
        $this->dokuHTTPClient = new \DokuHTTPClient();
        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $jiraUrl = $db->getKeyValue('jira_url');
        $this->jiraUrl = $jiraUrl ? trim($jiraUrl, '/') : null;
        $authToken = $db->getKeyValue('jira_token');
        $this->token = $authToken;
        $jiraUser = $db->getKeyValue('jira_user');
        $this->authUser = $jiraUser;
    }

    /**
     * Decide whether the provided issue is valid
     *
     * @param Issue $issue
     *
     * @return bool
     */
    public static function isIssueValid(Issue $issue)
    {
        $summary = $issue->getSummary();
        $valid = !blank($summary);
        $status = $issue->getStatus();
        $valid &= !blank($status);
        $type = $issue->getType();
        $valid &= !blank($type);
        return $valid;
    }

    /**
     * Provide the character separation the project name from the issue number, may be different for merge requests
     *
     * @param bool $isMergeRequest
     *
     * @return string
     */
    public static function getProjectIssueSeparator($isMergeRequest)
    {
        return '-';
    }

    public static function isOurWebhook()
    {
        global $INPUT;
        $userAgent = $INPUT->server->str('HTTP_USER_AGENT');
        return strpos($userAgent, 'Atlassian HttpClient') === 0;
    }

    /**
     * Get the url to the given issue at the given project
     *
     * @param      $projectId
     * @param      $issueId
     * @param bool $isMergeRequest ignored, GitHub routes the requests correctly by itself
     *
     * @return string
     */
    public function getIssueURL($projectId, $issueId, $isMergeRequest)
    {
        return $this->jiraUrl . '/browse/' . $projectId . '-' . $issueId;
    }

    /**
     * @param string $issueSyntax
     *
     * @return Issue
     */
    public function parseIssueSyntax($issueSyntax)
    {
        if (preg_match('/^\w+\-[1-9]\d*$/', $issueSyntax) !== 1) {
            return null;
        }

        list($projectKey, $issueNumber) = explode('-', $issueSyntax);

        $issue = Issue::getInstance('jira', $projectKey, $issueNumber, false);
        $issue->getFromDB();

        return $issue;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        if (null === $this->jiraUrl) {
            $this->configError = 'Jira URL not set!';
            return false;
        }

        if (empty($this->token)) {
            $this->configError = 'Authentication token is missing!';
            return false;
        }

        if (empty($this->authUser)) {
            $this->configError = 'Authentication user is missing!';
            return false;
        }

        try {
            $this->makeJiraRequest('/rest/webhooks/1.0/webhook', [], 'GET');
//            $user = $this->makeJiraRequest('/rest/api/2/user', [], 'GET');
        } catch (\Exception $e) {
            $this->configError = 'Attempt to verify the Jira authentication failed with message: ' . hsc($e->getMessage());
            return false;
        }

        return true;
    }

    protected function makeJiraRequest($endpoint, array $data, $method, array $headers = [])
    {
        $url = $this->jiraUrl . $endpoint;
        $dataToBeSend = json_encode($data);
        $defaultHeaders = [
            'Authorization' => 'Basic ' . base64_encode("$this->authUser:$this->token"),
            'Content-Type' => 'application/json',
        ];

        $this->dokuHTTPClient->headers = array_merge($this->dokuHTTPClient->headers, $defaultHeaders, $headers);

        try {
            $responseSuccess = $this->dokuHTTPClient->sendRequest($url, $dataToBeSend, $method);
        } catch (\HTTPClientException $e) {
            throw new HTTPRequestException('request error', $this->dokuHTTPClient, $url, $method);
        }

        if (!$responseSuccess || $this->dokuHTTPClient->status < 200 || $this->dokuHTTPClient->status > 206) {
            if ($this->dokuHTTPClient->status >= 500) {
                throw new ExternalServerException('request error', $this->dokuHTTPClient, $url, $method);
            }
            throw new HTTPRequestException('request error', $this->dokuHTTPClient, $url, $method);
        }
        return json_decode($this->dokuHTTPClient->resp_body, true);
    }

    /**
     * @param Form $configForm
     *
     * @return void
     */
    public function hydrateConfigForm(Form $configForm)
    {
        $url = 'https://id.atlassian.com/manage/api-tokens';
        $link = "<a href=\"$url\">$url</a>";
        $configForm->addHTML("<p>{$this->configError} Please go to $link and generate a new token for this plugin.</p>");
        $configForm->addTextInput('jira_url', 'Jira Url')->val($this->jiraUrl);
        $configForm->addTextInput('jira_user', 'Jira User')
            ->val($this->authUser)
            ->attr('placeholder', 'username@company.com');
        $configForm->addPasswordInput('jira_token', 'Jira AccessToken')->useInput(false);
    }

    public function handleAuthorization()
    {
        global $INPUT;

        $token = $INPUT->str('jira_token');
        $url = $INPUT->str('jira_url');
        $user = $INPUT->str('jira_user');

        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        if (!empty($token)) {
            $db->saveKeyValuePair('jira_token', $token);
        }
        if (!empty($url)) {
            $db->saveKeyValuePair('jira_url', $url);
        }
        if (!empty($user)) {
            $db->saveKeyValuePair('jira_user', $user);
        }

    }

    public function getUserString()
    {
        return hsc($this->authUser);
    }

    public function retrieveIssue(Issue $issue)
    {
        // FIXME: somehow validate that we are allowed to retrieve that issue

        $projectKey = $issue->getProject();

        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $webhooks = $db->getWebhooks('jira');
        $allowedRepos = explode(',', $webhooks[0]['repository_id']);

        if (!in_array($projectKey, $allowedRepos, true)) {
//            Jira Projects must be enabled as Webhook for on-demand fetching
            return;
        }


        $issueNumber = $issue->getKey();
        $endpoint = "/rest/api/2/issue/$projectKey-$issueNumber";

        $issueData = $this->makeJiraRequest($endpoint, [], 'GET');
        $this->setIssueData($issue, $issueData);
    }

    protected function setIssueData(Issue $issue, $issueData)
    {
        $issue->setSummary($issueData['fields']['summary']);
        $issue->setStatus($issueData['fields']['status']['name']);
        $issue->setDescription($issueData['fields']['description']);
        $issue->setType($issueData['fields']['issuetype']['name']);
        $issue->setPriority($issueData['fields']['priority']['name']);

        $issue->setUpdated($issueData['fields']['updated']);
        $versions = array_column($issueData['fields']['fixVersions'], 'name');
        $issue->setVersions($versions);
        $components = array_column($issueData['fields']['components'], 'name');
        $issue->setComponents($components);
        $issue->setLabels($issueData['fields']['labels']);

        if ($issueData['fields']['assignee']) {
            $assignee = $issueData['fields']['assignee'];
            $issue->setAssignee($assignee['displayName'], $assignee['avatarUrls']['48x48']);
        }

        if ($issueData['fields']['duedate']) {
            $issue->setDuedate($issueData['fields']['duedate']);
        }

        // FIXME: check and handle these fields:
//        $issue->setParent($issueData['fields']['parent']['key']);
    }

    public function retrieveAllIssues($projectKey, &$startat = 0)
    {
        $jqlQuery = "project=$projectKey";
//        $jqlQuery = urlencode("project=$projectKey ORDER BY updated DESC");
        $endpoint = '/rest/api/2/search?jql=' . $jqlQuery . '&maxResults=50&startAt=' . $startat;
        $result = $this->makeJiraRequest($endpoint, [], 'GET');

        if (empty($result['issues'])) {
            return [];
        }

        $this->total = $result['total'];

        $startat += $result['maxResults'];

        $retrievedIssues = [];
        foreach ($result['issues'] as $issueData) {
            list(, $issueNumber) = explode('-', $issueData['key']);
            try {
                $issue = Issue::getInstance('jira', $projectKey, $issueNumber, false);
            } catch (\InvalidArgumentException $e) {
                continue;
            }
            $this->setIssueData($issue, $issueData);
            $issue->saveToDB();
            $retrievedIssues[] = $issue;
        }
        return $retrievedIssues;
    }

    /**
     * Get the total of issues currently imported by retrieveAllIssues()
     *
     * This may be an estimated number
     *
     * @return int
     */
    public function getTotalIssuesBeingImported()
    {
        return $this->total;
    }

    /**
     * Get a list of all organisations a user is member of
     *
     * @return string[] the identifiers of the organisations
     */
    public function getListOfAllUserOrganisations()
    {
        return ['All projects'];
    }

    /**
     * @param $organisation
     *
     * @return Repository[]
     */
    public function getListOfAllReposAndHooks($organisation)
    {
        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $webhooks = $db->getWebhooks('jira');
        $subscribedProjects = [];
        if (!empty($webhooks)) {
            $subscribedProjects = explode(',', $webhooks[0]['repository_id']);
        }

        $projects = $this->makeJiraRequest('/rest/api/2/project', [], 'GET');

        $repositories = [];
        foreach ($projects as $project) {
            $repo = new Repository();
            $repo->displayName = $project['name'];
            $repo->full_name = $project['key'];
            if (in_array($project['key'], $subscribedProjects)) {
                $repo->hookID = 1;
            }
            $repositories[] = $repo;
        }
        return $repositories;
    }

    public function createWebhook($project)
    {

        // get old webhook id
        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $webhooks = $db->getWebhooks('jira');
        $projects = [];
        if (!empty($webhooks)) {
            $oldID = $webhooks[0]['id'];
            // get current webhook projects
            $projects = explode(',', $webhooks[0]['repository_id']);
            // remove old webhook
            $this->makeJiraRequest('/rest/webhooks/1.0/webhook/' . $oldID, [], 'DELETE');
            // delete old webhook from database
            $db->deleteWebhook('jira', $webhooks[0]['repository_id'], $oldID);
        }

        // add new project
        $projects[] = $project;
        $projects = array_filter(array_unique($projects));
        $projectsString = implode(',', $projects);

        // add new webhooks
        global $conf;
        $payload = [
            'name' => 'dokuwiki plugin issuelinks for Wiki: ' . $conf['title'],
            'url' => self::WEBHOOK_URL,
            'events' => [
                'jira:issue_created',
                'jira:issue_updated',
            ],
            'description' => 'dokuwiki plugin issuelinks for Wiki: ' . $conf['title'],
            'jqlFilter' => "project in ($projectsString)",
            'excludeIssueDetails' => 'false',
        ];
        $response = $this->makeJiraRequest('/rest/webhooks/1.0/webhook', $payload, 'POST');
        $selfLink = $response['self'];
        $newWebhookID = substr($selfLink, strrpos($selfLink, '/') + 1);


        // store new webhook to database
        $db->saveWebhook('jira', $projectsString, $newWebhookID, 'jira rest webhooks have no secrets :/');
        return ['status' => 200, 'data' => ['id' => $newWebhookID]];

    }

    /**
     * Delete our webhook in a source repository
     *
     * @param     $project
     * @param int $hookid the numerical id of the hook to be deleted
     *
     * @return array
     */
    public function deleteWebhook($project, $hookid)
    {
        // get old webhook id
        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $webhooks = $db->getWebhooks('jira');
        $projects = [];
        if (!empty($webhooks)) {
            $oldID = $webhooks[0]['id'];
            // get current webhook projects
            $projects = explode(',', $webhooks[0]['repository_id']);
            // remove old webhook
            $this->makeJiraRequest('/rest/webhooks/1.0/webhook/' . $oldID, [], 'DELETE');
            // delete old webhook from database
            $db->deleteWebhook('jira', $webhooks[0]['repository_id'], $oldID);
        }

        // remove project
        $projects = array_filter(array_diff($projects, [$project]));
        if (empty($projects)) {
            return ['status' => 204, 'data' => ''];
        }

        $projectsString = implode(',', $projects);

        // add new webhooks
        global $conf;
        $payload = [
            'name' => 'dokuwiki plugin issuelinks for Wiki: ' . $conf['title'],
            'url' => self::WEBHOOK_URL,
            'events' => [
                'jira:issue_created',
                'jira:issue_updated',
            ],
            'description' => 'dokuwiki plugin issuelinks for Wiki: ' . $conf['title'],
            'jqlFilter' => "project in ($projectsString)",
            'excludeIssueDetails' => 'false',
        ];
        $response = $this->makeJiraRequest('/rest/webhooks/1.0/webhook', $payload, 'POST');
        $selfLink = $response['self'];
        $newWebhookID = substr($selfLink, strrpos($selfLink, '/') + 1);

        // store new webhook to database
        $db->saveWebhook('jira', $projectsString, $newWebhookID, 'jira rest webhooks have no secrets :/');

        return ['status' => 204, 'data' => ''];
    }

    /**
     * Do all checks to verify that the webhook is expected and actually ours
     *
     * @param $webhookBody
     *
     * @return true|RequestResult true if the the webhook is our and should be processed RequestResult with explanation otherwise
     */
    public function validateWebhook($webhookBody)
    {
        $data = json_decode($webhookBody, true);
        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $webhooks = $db->getWebhooks('jira');
        $projects = [];
        if (!empty($webhooks)) {
            // get current webhook projects
            $projects = explode(',', $webhooks[0]['repository_id']);
        }

        if (!$data['webhookEvent'] || !in_array($data['webhookEvent'], ['jira:issue_updated', 'jira:issue_created'])) {
            return new RequestResult(400, 'unknown webhook event');
        }

        list($projectKey, $issueId) = explode('-', $data['issue']['key']);

        if (!in_array($projectKey, $projects)) {
            return new RequestResult(202, 'Project ' . $projectKey . ' is not handled by this wiki.');
        }

        return true;
    }

    /**
     * Handle the contents of the webhooks body
     *
     * @param $webhookBody
     *
     * @return RequestResult
     */
    public function handleWebhook($webhookBody)
    {
        $data = json_decode($webhookBody, true);
        $issueData = $data['issue'];
        list($projectKey, $issueId) = explode('-', $issueData['key']);
        $issue = Issue::getInstance('jira', $projectKey, $issueId, false);
        $this->setIssueData($issue, $issueData);
        $issue->saveToDB();

        return new RequestResult(200, 'OK');
    }

}
