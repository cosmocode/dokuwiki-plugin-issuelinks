<?php
/**
 * DokuWiki Plugin issuelinks (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\issuelinks\classes\Issue;
use dokuwiki\plugin\issuelinks\classes\ServiceProvider;

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class helper_plugin_issuelinks_data extends DokuWiki_Plugin
{

    /** @var helper_plugin_issuelinks_db */
    private $db = null;

    /**
     * constructor. loads helpers
     */
    public function __construct()
    {
        $this->db = $this->loadHelper('issuelinks_db');
    }


    /**
     * Import all Jira issues starting at given paging offset
     *
     * @param string $serviceName The name of the project management service
     * @param string $projectKey  The short-key of the project to be imported
     *
     * @throws Exception
     */
    public function importAllIssues($serviceName, $projectKey)
    {
        $lockfileKey = $this->getImportLockID($serviceName, $projectKey);
        if ($this->isImportLocked($lockfileKey)) {
            throw new RuntimeException('Import of Issues is already locked!');
        }
        dbglog('start import. $lockfileKey: ' . $lockfileKey);
        $this->lockImport($lockfileKey, json_encode(['user' => $_SERVER['REMOTE_USER'], 'status' => 'started']));

        $serviceProvider = ServiceProvider::getInstance();
        $services = $serviceProvider->getServices();
        dbglog($services);
        dbglog($serviceName);
        $serviceClass = $services[$serviceName];
        dbglog($serviceClass);
        $service = $serviceClass::getInstance();

        $total = 0;
        $counter = 0;
        $startAt = 0;

        try {
            while ($issues = $service->retrieveAllIssues($projectKey, $startAt)) {
                if (!$total) {
                    $total = $service->getTotalIssuesBeingImported();
                }

                if ($counter > $total) {
                    break;
                }

                if (!$this->isImportLockedByMe($lockfileKey)) {
                    throw new RuntimeException('Import of Issues aborted because lock removed');
                }

                $counter += count($issues);
                $this->lockImport($lockfileKey, json_encode([
                    'user' => $_SERVER['REMOTE_USER'],
                    'total' => $total,
                    'count' => $counter,
                    'status' => 'running',
                ]));


            }
        } catch (\Throwable $e) {
            dbglog("Downloading all issues from $serviceName fpr project $projectKey failed ",
                __FILE__ . ': ' . __LINE__);
            if (is_a($e, \dokuwiki\plugin\issuelinks\classes\HTTPRequestException::class)) {
                /** @var \dokuwiki\plugin\issuelinks\classes\HTTPRequestException $e */
                dbglog($e->getUrl());
                dbglog($e->getHttpError());
                dbglog($e->getMessage());
                dbglog($e->getCode());
                dbglog($e->getResponseBody());
            }
            $this->lockImport($lockfileKey, json_encode(['status' => 'failed']));
            throw $e;
        }
        $this->unlockImport($lockfileKey);
    }


    public function getImportLockID($serviceName, $projectKey)
    {
        return "_plugin__issuelinks_import_$serviceName-$projectKey";
    }

    /**
     * This checks the lock for the import process it behaves differently from the dokuwiki-core checklock() function!
     *
     * It returns false if the lock does not exist. It returns **boolean true** if the lock exists and is mine.
     * It returns the username/ip if the lock exists and is not mine.
     * It is therefore important to use strict (===) checking for true!
     *
     * @param $id
     *
     * @return bool|string
     */
    public function isImportLocked($id)
    {
        global $conf;
        $lockFN = $conf['lockdir'] . '/' . md5('_' . $id) . '.lock';
        if (!file_exists($lockFN)) {
            return false;
        }

        clearstatcache($lockFN);
        if ((time() - filemtime($lockFN)) > 120) { // todo: decide if we want this to be configurable?
            @unlink($lockFN);
            dbglog('issuelinks: stale lock timeout');
            return false;
        }

        $lockData = json_decode(io_readFile($lockFN), true);
        if (!empty($lockData['status']) && $lockData['status'] === 'done') {
            return false;
        }

        return true;
    }

    /**
     * Generate lock file for import of issues/commits
     *
     * This is mostly a reimplementation of @see lock()
     * However we do not clean the id and prepent a underscore to avoid conflicts with locks of existing pages.
     *
     * @param $id
     * @param $jsonData
     */
    public function lockImport($id, $jsonData)
    {
        global $conf;

        $lock = $conf['lockdir'] . '/' . md5('_' . $id) . '.lock';
        dbglog('lock import: ' . $jsonData, __FILE__ . ': ' . __LINE__);
        io_saveFile($lock, $jsonData);
    }

    public function isImportLockedByMe($id)
    {
        if (!$this->isImportLocked($id)) {
            return false;
        }

        global $conf, $INPUT;
        $lockFN = $conf['lockdir'] . '/' . md5('_' . $id) . '.lock';
        $lockData = json_decode(io_readFile($lockFN), true);
        if ($lockData['user'] !== $INPUT->server->str('REMOTE_USER')) {
            return false;
        }

        touch($lockFN);
        return true;
    }

    /**
     * Marks the import as unlocked / done
     *
     * @param $id
     */
    public function unlockImport($id)
    {
        global $conf;
        $lockFN = $conf['lockdir'] . '/' . md5('_' . $id) . '.lock';
        $lockData = json_decode(io_readFile($lockFN), true);
        $lockData['status'] = 'done';
        $lockData['total'] = $lockData['count'];
        io_saveFile($lockFN, json_encode($lockData));
    }

    public function getLockContent($id)
    {
        global $conf;
        $lockFN = $conf['lockdir'] . '/' . md5('_' . $id) . '.lock';
        if (!file_exists($lockFN)) {
            return false;
        }
        return json_decode(io_readFile($lockFN), true);
    }

    public function removeLock($lockID)
    {
        global $conf;
        $lockFN = $conf['lockdir'] . '/' . md5('_' . $lockID) . '.lock';
        unlink($lockFN);
    }

    /**
     * Get an issue either from local DB or attempt to import it
     *
     * @param string $pmServiceName The name of the project management service
     * @param string $project
     * @param int    $issueid
     * @param bool   $isMergeRequest
     *
     * @return bool|Issue
     */
    public function getIssue($pmServiceName, $project, $issueid, $isMergeRequest)
    {
        $issue = Issue::getInstance($pmServiceName, $project, $issueid, $isMergeRequest);
        if (!$issue->isValid()) {
            try {
                $issue->getFromService();
                $issue->saveToDB();
            } catch (Exception $e) {
                // that's fine
            }
        }
        return $issue;
    }

    public function getMergeRequestsForIssue($serviceName, $projectKey, $issueId, $isMergeRequest)
    {
        /** @var helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $issues = $db->getMergeRequestsReferencingIssue($serviceName, $projectKey, $issueId, $isMergeRequest);
        foreach ($issues as &$issueData) {
            $issue = Issue::getInstance($issueData['service'], $issueData['project_id'], $issueData['issue_id'],
                $issueData['is_mergerequest']);
            $issue->getFromDB();
            $issueData['summary'] = $issue->getSummary();
            $issueData['status'] = $issue->getStatus();
            $issueData['url'] = $issue->getIssueURL();
        }
        unset($issueData);

        return $issues;
    }

    /**
     * Get Pages with links to issues
     *
     * @param string $pmServiceName The name of the project management service
     * @param string $projectKey
     * @param int    $issueId       the issue id
     * @param bool   $isMergeRequest
     *
     * @return array
     */
    public function getLinkingPages($pmServiceName, $projectKey, $issueId, $isMergeRequest)
    {
        $pages = $this->db->getAllPageLinkingToIssue($pmServiceName, $projectKey, $issueId, $isMergeRequest);
        $pages = $this->db->removeOldLinks($pmServiceName, $projectKey, $issueId, $isMergeRequest, $pages);

        if (empty($pages)) {
            return [];
        }

        $pages = $this->keepNewest($pages);
        $pages = $this->filterPagesForACL($pages);
        $pages = $this->addUserToPages($pages);
        return $pages;
    }

    /**
     * remove duplicate revisions of a page and keep only the newest
     *
     * @param array $pages Array of pages sorted(!) from newest to oldest
     *
     * @return array
     */
    public function keepNewest($pages)
    {
        $uniquePages = [];
        foreach ($pages as $page) {
            if (!array_key_exists($page['page'], $uniquePages) || $uniquePages[$page['page']]['rev'] < $page['rev']) {
                $uniquePages[$page['page']] = $page;
            }
        }
        return array_values($uniquePages);
    }

    /**
     * Filter the given pages for at least AUTH_READ
     *
     * @param array $pages
     *
     * @return array
     */
    private function filterPagesForACL($pages)
    {
        $allowedPagegs = [];
        foreach ($pages as $page) {
            if (auth_quickaclcheck($page['page']) >= AUTH_READ) {
                $allowedPagegs[] = $page;
            }
        }
        return $allowedPagegs;
    }

    /**
     * add the corresponding user to each revision
     *
     * @param array $pages
     *
     * @return array
     */
    public function addUserToPages($pages)
    {
        foreach ($pages as &$page) {
            $changelog = new PageChangelog($page['page']);
            $revision = $changelog->getRevisionInfo($page['rev']);
            $page['user'] = $revision['user'];
        }
        return $pages;
    }
}

// vim:ts=4:sw=4:et:
