<?php

namespace dokuwiki\plugin\issuelinks\services;

use dokuwiki\Form\Form;
use dokuwiki\plugin\issuelinks\classes\Issue;
use dokuwiki\plugin\issuelinks\classes\Repository;

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

}
