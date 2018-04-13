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
     * @param $projectId
     * @param $issueId
     * @param bool $isMergeRequest ignored, GitHub routes the requests correctly by itself
     *
     * @return string
     */
    public static function getIssueURL($projectId, $issueId, $isMergeRequest);

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

    /**
     * @param $organisation
     *
     * @return Repository[]
     */
    public function getListOfAllReposAndHooks($organisation);

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
