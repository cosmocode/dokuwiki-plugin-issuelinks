<?php

namespace dokuwiki\plugin\issuelinks\services;

use dokuwiki\Form\Form;
use dokuwiki\plugin\issuelinks\classes\ExternalServerException;
use dokuwiki\plugin\issuelinks\classes\HTTPRequestException;
use dokuwiki\plugin\issuelinks\classes\Issue;
use dokuwiki\plugin\issuelinks\classes\Repository;
use dokuwiki\plugin\issuelinks\classes\RequestResult;

class GitHub extends AbstractService
{


    const SYNTAX = 'gh';
    const DISPLAY_NAME = 'GitHub';
    const ID = 'github';
    const WEBHOOK_UA = 'GitHub-Hookshot/';
    protected $configError = '';
    protected $user = [];
    protected $total = null;
    protected $orgs;
    /** @var \DokuHTTPClient */
    protected $dokuHTTPClient;
    protected $githubUrl = 'https://api.github.com';
    private $scopes = ['admin:repo_hook', 'read:org', 'public_repo'];

    protected function __construct()
    {
        $this->dokuHTTPClient = new \DokuHTTPClient();
    }

    public static function getProjectIssueSeparator($isMergeRequest)
    {
        return '#';
    }

    public static function isOurWebhook()
    {
        global $INPUT;
        $userAgent = $INPUT->server->str('HTTP_USER_AGENT');
        return strpos($userAgent, self::WEBHOOK_UA) === 0;
    }

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

    public function getIssueURL($projectId, $issueId, $isMergeRequest)
    {
        return 'https://github.com' . '/' . $projectId . '/issues/' . $issueId;
    }

    public function parseIssueSyntax($issueSyntax)
    {
        list($projectKey, $issueId) = explode('#', $issueSyntax);

        // try to load as pull request
        $issue = Issue::getInstance('github', $projectKey, $issueId, true);
        $isPullRequest = $issue->getFromDB();

        if ($isPullRequest) {
            return $issue;
        }

        // not a pull request, retrieve it as normal issue
        $issue = Issue::getInstance('github', $projectKey, $issueId, false);
        $issue->getFromDB();

        return $issue;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $authToken = $db->getKeyValue('github_token');

        if (empty($authToken)) {
            $this->configError = 'Authentication token is missing!';
            return false;
        }

        try {
            $user = $this->makeGitHubGETRequest('/user');
//            $status = $this->connector->getLastStatus();
        } catch (\Exception $e) {
            $this->configError = 'The GitHub authentication failed with message: ' . hsc($e->getMessage());
            return false;
        }
        $this->user = $user;

        $headers = $this->dokuHTTPClient->resp_headers;
        $missing_scopes = array_diff($this->scopes, explode(', ', $headers['x-oauth-scopes']));
        if (count($missing_scopes) !== 0) {
            $this->configError = 'Scopes "' . implode(', ', $missing_scopes) . '" are missing!';
            return false;
        }
        return true;
    }

    /**
     *
     * todo: ensure correct headers are set: https://developer.github.com/v3/#current-version
     *
     * @param string   $endpoint the endpoint as defined in the GitHub documentation. With leading and trailing slashes
     * @param int|null $max      do not make more requests after this number of items have been retrieved
     *
     * @return array The decoded response-text
     * @throws HTTPRequestException
     */
    protected function makeGitHubGETRequest($endpoint, $max = null)
    {
        $results = [];
        $waittime = 0;
        /** @var \helper_plugin_issuelinks_util $utils */
        $utils = plugin_load('helper', 'issuelinks_util');
        do {
            usleep($waittime);
            try {
                $data = $this->makeGitHubRequest($endpoint, [], 'GET', []);
            } catch (ExternalServerException $e) {
                if ($waittime >= 500) {
                    msg('Error repeats. Aborting Requests.', -1);
                    dbglog('Error repeats. Aborting Requests.', -1);
                    break;
                }
                $waittime += 100;
                msg("Server Error occured. Waiting $waittime ms between requests and repeating request.", -1);
                dbglog("Server Error occured. Waiting $waittime ms between requests and repeating request.", -1);

                continue;
            }


            if ($this->dokuHTTPClient->resp_headers['x-ratelimit-remaining'] < 500) {
                msg(sprintf(
                    $utils->getLang('error:system too many requests'),
                    dformat($this->dokuHTTPClient->resp_headers['x-ratelimit-reset'])
                ), -1);
                break;
            }

            $results = array_merge($results, $data);

            if (empty($this->dokuHTTPClient->resp_headers['link'])) {
                break;
            }
            $links = $utils->parseHTTPLinkHeaders($this->dokuHTTPClient->resp_headers['link']);
            if (empty($links['next'])) {
                break;
            }
            $endpoint = substr($links['next'], strlen($this->githubUrl));
        } while (empty($max) || count($results) < $max);
        return $results;
    }

    /**
     * @param string $endpoint
     * @param array  $data
     * @param string $method
     * @param array  $headers
     *
     * @return mixed
     *
     * @throws HTTPRequestException|ExternalServerException
     */
    protected function makeGitHubRequest($endpoint, $data, $method, $headers = [])
    {
        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $authToken = $db->getKeyValue('github_token');
        $defaultHeaders = [
            'Authorization' => "token $authToken",
            'Content-Type' => 'application/json',
        ];

        $requestHeaders = array_merge($defaultHeaders, $headers);

        // todo ensure correct slashes everywhere
        $url = $this->githubUrl . $endpoint;

        return $this->makeHTTPRequest($this->dokuHTTPClient, $url, $requestHeaders, $data, $method);
    }

    public function hydrateConfigForm(Form $configForm)
    {
        $scopes = implode(', ', $this->scopes);
        $link = '<a href="https://github.com/settings/tokens">https://github.com/settings/tokens/</a>';
        $message = '<p>';
        $message .= $this->configError;
        $message .= " Please go to $link and generate a new token for this plugin with the scopes $scopes.";
        $message .= '</p>';
        $configForm->addHTML($message);
        $configForm->addTextInput('githubToken', 'GitHub AccessToken')->useInput(false);
    }

    public function handleAuthorization()
    {
        global $INPUT;

        $token = $INPUT->str('githubToken');

        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $db->saveKeyValuePair('github_token', $token);
    }

    /**
     * @inheritdoc
     */
    public function getListOfAllReposAndHooks($organisation)
    {
        $endpoint = "/orgs/$organisation/repos";
        try {
            $repos = $this->makeGitHubGETRequest($endpoint);
        } catch (HTTPRequestException $e) {
            msg($e->getMessage() . ' ' . $e->getCode(), -1);
            return [];
        }
        $projects = [];

        foreach ($repos as $repoData) {
            $repo = new Repository();
            $repo->full_name = $repoData['full_name'];
            $repo->displayName = $repoData['name'];

            $endpoint = "/repos/$repoData[full_name]/hooks";
            try {
                $repoHooks = $this->makeGitHubGETRequest($endpoint);
            } catch (HTTPRequestException $e) {
                $repoHooks = [];
                $repo->error = 403;
            }
            $repoHooks = array_filter($repoHooks, [$this, 'isOurIssueHook']);
            $ourIsseHook = reset($repoHooks);
            if (!empty($ourIsseHook)) {
                $repo->hookID = $ourIsseHook['id'];
            }
            $projects[] = $repo;
        }

        return $projects;
    }

    public function deleteWebhook($project, $hookid)
    {
        try {
            $data = $this->makeGitHubRequest("/repos/$project/hooks/$hookid", [], 'DELETE');
            $status = $this->dokuHTTPClient->status;

            /** @var \helper_plugin_issuelinks_db $db */
            $db = plugin_load('helper', 'issuelinks_db');
            $db->deleteWebhook('github', $project, $hookid);
        } catch (HTTPRequestException $e) {
            $data = $e->getMessage();
            $status = $e->getCode();
        }

        return ['data' => $data, 'status' => $status];
    }

    public function createWebhook($project)
    {
        $secret = md5(openssl_random_pseudo_bytes(32));
        $config = [
            "url" => self::WEBHOOK_URL,
            "content_type" => 'json',
            "insecure_ssl" => 0,
            "secret" => $secret,
        ];
        $data = [
            "name" => "web",
            "config" => $config,
            "active" => true,
            'events' => ['issues', 'issue_comment', 'pull_request'],
        ];
        try {
            $data = $this->makeGitHubRequest("/repos/$project/hooks", $data, 'POST');
            $status = $this->dokuHTTPClient->status;
            $id = $data['id'];
            /** @var \helper_plugin_issuelinks_db $db */
            $db = plugin_load('helper', 'issuelinks_db');
            $db->saveWebhook('github', $project, $id, $secret);
        } catch (HTTPRequestException $e) {
            $data = $e->getMessage();
            $status = $e->getCode();
        }

        return ['data' => $data, 'status' => $status];
    }

    public function validateWebhook($webhookBody)
    {
        $data = json_decode($webhookBody, true);
        if (!$this->isSignatureValid($webhookBody, $data['repository']['full_name'])) {
            return new RequestResult(403, 'Signature invalid or missing!');
        }
        return true;
    }

    /**
     * Check if the signature in the header provided by github is valid by using a stored secret
     *
     *
     * Known issue:
     *   * We have to cycle through the webhooks/secrets stored for a given repo because the hookid is not in the
     *   request
     *
     * @param string $body    The unaltered payload of the request
     * @param string $repo_id the repo id (in the format of "organisation/repo-name")
     *
     * @return bool wether the provided signature checks out against a stored one
     */
    protected function isSignatureValid($body, $repo_id)
    {
        global $INPUT;
        if (!$INPUT->server->has('HTTP_X_HUB_SIGNATURE')) {
            return false;
        }
        list($algo, $signature_github) = explode('=', $INPUT->server->str('HTTP_X_HUB_SIGNATURE'));
        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $secrets = $db->getWebhookSecrets('github', $repo_id);
        foreach ($secrets as $secret) {
            $signature_local = hash_hmac($algo, $body, $secret['secret']);
            if (hash_equals($signature_local, $signature_github)) {
                return true;
            }
        }
        return false;
    }

    public function handleWebhook($webhookBody)
    {
        global $INPUT;
        $data = json_decode($webhookBody, true);
        $event = $INPUT->server->str('HTTP_X_GITHUB_EVENT');

        if ($event === 'ping') {
            return new RequestResult(202, 'Webhook ping successful. Pings are not processed.');
        }

        if (!$this->saveIssue($data)) {
            return new RequestResult(500, 'There was an error saving the issue.');
        }


        return new RequestResult(200, 'OK');
    }

    /**
     * Handle the webhook event, triggered by an updated or created issue
     *
     * @param array $data
     *
     * @return bool whether saving was successful
     *
     * @throws \InvalidArgumentException
     * @throws HTTPRequestException
     */
    protected function saveIssue($data)
    {

        $issue = Issue::getInstance(
            'github',
            $data['repository']['full_name'],
            $data['issue']['number'],
            false,
            true
        );

        $this->setIssueData($issue, $data['issue']);

        return $issue->saveToDB();
    }

    /**
     * @param Issue $issue
     * @param array $info
     */
    protected function setIssueData(Issue $issue, $info)
    {
        $issue->setSummary($info['title']);
        $issue->setDescription($info['body']);
        $labels = [];
        foreach ($info['labels'] as $label) {
            $labels[] = $label['name'];
            $issue->setLabelData($label['name'], '#' . $label['color']);
        }
        $issue->setType($this->getTypeFromLabels($labels));
        $issue->setStatus(isset($info['merged']) ? 'merged' : $info['state']);
        $issue->setUpdated($info['updated_at']);
        if (!empty($info['milestone'])) {
            $issue->setVersions([$info['milestone']['title']]);
        }
        $issue->setLabels($labels);
        if ($info['assignee']) {
            $issue->setAssignee($info['assignee']['login'], $info['assignee']['avatar_url']);
        }
    }

    protected function getTypeFromLabels(array $labels)
    {
        $bugTypeLabels = ['bug'];
        $improvementTypeLabels = ['enhancement'];
        $storyTypeLabels = ['feature'];

        if (count(array_intersect($labels, $bugTypeLabels))) {
            return 'bug';
        }

        if (count(array_intersect($labels, $improvementTypeLabels))) {
            return 'improvement';
        }

        if (count(array_intersect($labels, $storyTypeLabels))) {
            return 'story';
        }

        return 'unknown';
    }

    public function getListOfAllUserOrganisations()
    {
        if ($this->orgs === null) {
            $endpoint = '/user/orgs';
            try {
                $this->orgs = $this->makeGitHubGETRequest($endpoint);
            } catch (\Throwable $e) {
                $this->orgs = [];
                msg(hsc($e->getMessage()), -1);
            }
        }
        // fixme: add 'user repos'!
        return array_map(function ($org) {
            return $org['login'];
        }, $this->orgs);
    }

    public function getUserString()
    {
        return $this->user['login'];
    }

    public function retrieveIssue(Issue $issue)
    {
        $repo = $issue->getProject();
        $issueNumber = $issue->getKey();
        $endpoint = '/repos/' . $repo . '/issues/' . $issueNumber;
        $result = $this->makeGitHubGETRequest($endpoint);
        $this->setIssueData($issue, $result);
        if (isset($result['pull_request'])) {
            $issue->isMergeRequest(true);
            $endpoint = '/repos/' . $repo . '/pulls/' . $issueNumber;
            $result = $this->makeGitHubGETRequest($endpoint);
            $issue->setStatus($result['merged'] ? 'merged' : $result['state']);
            $mergeRequestText = $issue->getSummary() . ' ' . $issue->getDescription();
            $issues = $this->parseMergeRequestDescription($repo, $mergeRequestText);
            /** @var \helper_plugin_issuelinks_db $db */
            $db = plugin_load('helper', 'issuelinks_db');
            $db->saveIssueIssues($issue, $issues);
        }
    }

    /**
     * Parse a string for issue-ids
     *
     * Currently only parses issues for the same repo and jira issues
     *
     * @param string $currentProject
     * @param string $description
     *
     * @return array
     */
    protected function parseMergeRequestDescription($currentProject, $description)
    {
        $issues = [];

        $issueOwnRepoPattern = '/(?:\W|^)#([1-9]\d*)\b/';
        preg_match_all($issueOwnRepoPattern, $description, $githubMatches);
        foreach ($githubMatches[1] as $issueId) {
            $issues[] = [
                'service' => 'github',
                'project' => $currentProject,
                'issueId' => $issueId,
            ];
        }

        // FIXME: this should be done by JIRA service class
        $jiraMatches = [];
        $jiraPattern = '/[A-Z0-9]+-[1-9]\d*/';
        preg_match_all($jiraPattern, $description, $jiraMatches);
        foreach ($jiraMatches[0] as $match) {
            list($project, $issueId) = explode('-', $match);
            $issues[] = [
                'service' => 'jira',
                'project' => $project,
                'issueId' => $issueId,
            ];
        }

        return $issues;
    }

    /**
     *
     * @see https://developer.github.com/v3/issues/#list-issues-for-a-repository
     *
     * @param string $projectKey The short-key of the project to be imported
     * @param int    $startat    The offset from the last Element from which to start importing
     *
     * @return array               The issues, suitable to be saved into the db
     * @throws HTTPRequestException
     *
     * // FIXME: set Header application/vnd.github.symmetra-preview+json ?
     */
    public function retrieveAllIssues($projectKey, &$startat = 0)
    {
        $perPage = 30;
        $page = ceil(($startat + 1) / $perPage);
        // FIXME: implent `since` parameter?
        $endpoint = "/repos/$projectKey/issues?state=all&page=$page";
        $issues = $this->makeGitHubGETRequest($endpoint);

        if (!is_array($issues)) {
            return [];
        }
        if (empty($this->total)) {
            $this->total = $this->estimateTotal($perPage, count($issues));
        }
        $retrievedIssues = [];
        foreach ($issues as $issueData) {
            try {
                $issue = Issue::getInstance(
                    'github',
                    $projectKey,
                    $issueData['number'],
                    !empty($issueData['pull_request'])
                );
            } catch (\InvalidArgumentException $e) {
                continue;
            }
            $this->setIssueData($issue, $issueData);
            $issue->saveToDB();
            $retrievedIssues[] = $issue;
        }
        $startat += $perPage;
        return $retrievedIssues;
    }

    /**
     * Estimate the total amount of results
     *
     * @param int $perPage amount of results per page
     * @param int $default what is returned if the total can not be calculated otherwise
     *
     * @return
     */
    protected function estimateTotal($perPage, $default)
    {
        $headers = $this->dokuHTTPClient->resp_headers;

        if (empty($headers['link'])) {
            return $default;
        }

        /** @var \helper_plugin_issuelinks_util $util */
        $util = plugin_load('helper', 'issuelinks_util');
        $links = $util->parseHTTPLinkHeaders($headers['link']);
        preg_match('/page=(\d+)$/', $links['last'], $matches);
        if (!empty($matches[1])) {
            return $matches[1] * $perPage;
        }
        return $default;
    }

    /**
     * @return mixed
     */
    public function getTotalIssuesBeingImported()
    {
        return $this->total;
    }

    /**
     * See if this is a hook for issue events, that has been set by us
     *
     * @param array $hook the hook data coming from github
     *
     * @return bool
     */
    protected function isOurIssueHook($hook)
    {
        if ($hook['config']['url'] !== self::WEBHOOK_URL) {
            return false;
        }

        if ($hook['config']['content_type'] !== 'json') {
            return false;
        }

        if ($hook['config']['insecure_ssl'] !== '0') {
            return false;
        }

        if (!$hook['active']) {
            return false;
        }

        $missingEvents = array_diff($hook['events'], ['issues', 'issue_comment', 'pull_request']);
        if (count($hook['events']) !== 3 || $missingEvents) {
            return false;
        }

        return true;
    }
}
