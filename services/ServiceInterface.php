<?php

namespace dokuwiki\plugin\issuelinks\services;

use dokuwiki\Form\Form;
use dokuwiki\plugin\issuelinks\classes\Issue;
use dokuwiki\plugin\issuelinks\classes\Repository;
use dokuwiki\plugin\issuelinks\classes\RequestResult;

interface ServiceInterface
{

    /**
     * Get the singleton instance of the Services
     *
     * @return ServiceInterface
     */
    public static function getInstance();

    /**
     * Get the url to the given issue at the given project
     *
     * @param      $projectId
     * @param      $issueId
     * @param bool $isMergeRequest ignored, GitHub routes the requests correctly by itself
     *
     * @return string
     */
    public static function getIssueURL($projectId, $issueId, $isMergeRequest);

    /**
     * Decide whether the provided issue is valid
     *
     * @param Issue $issue
     *
     * @return bool
     */
    public static function isIssueValid(Issue $issue);

    /**
     * Provide the character separation the project name from the issue number, may be different for merge requests
     *
     * @param bool $isMergeRequest
     *
     * @return string
     */
    public static function getProjectIssueSeparator($isMergeRequest);

    /**
     * @param string $issueSyntax
     *
     * @return Issue
     */
    public function parseIssueSyntax($issueSyntax);

    /**
     * @return bool
     */
    public function isConfigured();

    /**
     * @param Form $configForm
     *
     * @return void
     */
    public function hydrateConfigForm(Form $configForm);

    public function handleAuthorization();

    public function getUserString();

    public function retrieveIssue(Issue $issue);

    public function retrieveAllIssues($projectKey, &$startat = 0);

    /**
     * Get the total of issues currently imported by retrieveAllIssues()
     *
     * This may be an estimated number
     *
     * @return int
     */
    public function getTotalIssuesBeingImported();

    /**
     * Get a list of all organisations a user is member of
     *
     * @return string[] the identifiers of the organisations
     */
    public function getListOfAllUserOrganisations();

    /**
     * @param $organisation
     *
     * @return Repository[]
     */
    public function getListOfAllReposAndHooks($organisation);

    /**
     * Create a webhook at the repository
     *
     * @param string $organisation the organisation/group where a repository is located
     * @param string $repo         the name of the repository
     *
     * @return array
     */
    public function createWebhook($organisation, $repo);

    /**
     * Delete our webhook in a source repository
     *
     * @param string $organisation the organisation/group where a repository is located
     * @param string $repo         the name of the repository
     * @param int    $hookid       the numerical id of the hook to be deleted
     *
     * @return array
     */
    public function deleteWebhook($organisation, $repo, $hookid);

    /**
     * Do all checks to verify that the webhook is expected and actually ours
     *
     * @param $webhookBody
     *
     * @return true|RequestResult true if the the webhook is our and should be processed RequestResult with explanation otherwise
     */
    public function validateWebhook($webhookBody);

    /**
     * Handle the contents of the webhooks body
     *
     * @param $webhookBody
     *
     * @return RequestResult
     */
    public function handleWebhook($webhookBody);

}
