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
if(!defined('DOKU_INC')) die();

class helper_plugin_issuelinks_data extends DokuWiki_Plugin {

    /** @var helper_plugin_issuelinks_db */
    private $db = null;

    /**
     * constructor. loads helpers
     */
    public function __construct() {
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
    public function importAllIssues($serviceName, $projectKey) {
        $lockfileKey = $this->getImportLockID($serviceName, $projectKey);
//        if ($this->isImportLocked($lockfileKey)) {
//            throw new RuntimeException('Import of Issues is already locked!');
//        }
        dbglog('start import. $lockfileKey: ' . $lockfileKey);
        $this->lockImport($lockfileKey, json_encode(['user' => $_SERVER['REMOTE_USER']]));

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

        while($issues = $service->retrieveAllIssues($projectKey, $startAt)) {
            if (!$total) {
                $total = $service->getTotal();
            }

            if (!$this->isImportLockedByMe($lockfileKey)) {
                throw new RuntimeException('Import of Issues aborted because lock removed');
            }

            $counter += count($issues);
            $this->lockImport($lockfileKey, json_encode([
                'user' => $_SERVER['REMOTE_USER'],
                'total' => $total,
                'count' => $counter,
            ]));
        }
        $this->unlockImport($lockfileKey);
    }


    public function getImportLockID($serviceName, $projectKey)
    {
        return "_plugin__issuelinks_import_$serviceName-$projectKey";
    }

    public function getLockContent($id)
    {
        global $conf;
        $lockFN = $conf['lockdir'].'/'.md5('_' . $id).'.lock';
        if (!file_exists($lockFN)) {
            return false;
        }
        return json_decode(io_readFile($lockFN), true);
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
    public function lockImport($id, $jsonData) {
        global $conf;

        $lock = $conf['lockdir'].'/'.md5('_' . $id).'.lock';
        dbglog('lock import: ' . $jsonData, __FILE__ . ': ' . __LINE__);
        io_saveFile($lock, $jsonData);
    }

    /**
     * This checks the lock for the import process it behaves differently from the dokuwiki-core checklock() function!
     *
     * It returns false if the lock does not exist. It returns **boolean true** if the lock exists and is mine.
     * It returns the username/ip if the lock exists and is not mine.
     * It is therefore important to use strict (===) checking for true!
     *
     * @param $id
     * @return bool|string
     */
    public function isImportLocked($id) {
        global $conf;
        $lockFN = $conf['lockdir'].'/'.md5('_' . $id).'.lock';
        if(!file_exists($lockFN)) {
            return false;
        }

        clearstatcache($lockFN);
        if((time() - filemtime($lockFN)) > 120) { // todo: decide if we want this to be configurable?
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

    public function isImportLockedByMe($id) {
        if (!$this->isImportLocked($id)) {
            return false;
        }

        global $conf, $INPUT;
        $lockFN = $conf['lockdir'].'/'.md5('_' . $id).'.lock';
        $lockData = json_decode(io_readFile($lockFN), true);
        if($lockData['user'] !== $INPUT->server->str('REMOTE_USER')) {
            return false;
        }

        touch($lockFN);
        return true;
    }


    /**
     * Removes the lock created with @see \helper_plugin_magicmatcher_data::checkImportLock
     *
     * @param $id
     */
    public function unlockImport($id) {
        global $conf;
        $lockFN = $conf['lockdir'].'/'.md5('_' . $id).'.lock';
        $lockData = json_decode(io_readFile($lockFN), true);
        $lockData['status'] = 'done';
        io_saveFile($lockFN, json_encode($lockData));
    }

    public function removeLock($lockID)
    {
        global $conf;
        $lockFN = $conf['lockdir'].'/'.md5('_' . $lockID).'.lock';
        unlink($lockFN);
    }

    /**
     * Assemble the issues of a project such that they can be given to a @see \dokuwiki\Form\DropdownElement
     *
     * @param string $pmServiceName
     * @param string $projectid
     * @return array the array of options
     */
    public function assembleProjectIssueOptions($pmServiceName, $projectid) {
        $options = array('');
        if (!$pmServiceName || !$projectid) {
            return $options;
        }
        /** @var helper_plugin_magicmatcher_db $dbHelper */
        $dbHelper = $this->loadHelper('magicmatcher_db');
        $issues = $dbHelper->getProjectIssues($pmServiceName, $projectid);

        foreach ($issues as $issue) {
            $mrPrefix = $issue['is_mergerequest'] ? '!' : '';
            $options[$mrPrefix . $issue['id']] = array(
                'label' => PMServiceBuilder::getProjectIssueSeparator($pmServiceName, $issue['is_mergerequest']) . $issue['id'] . ': ' . $issue['summary'],
                'attrs' => array(
                    'data-project' => $issue['project'],
                    'data-status' => strtolower($issue['status'])
                )
            );
        }
        return $options;
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
    public function getIssue($pmServiceName, $project, $issueid, $isMergeRequest) {
        $issue = Issue::getInstance($pmServiceName, $project, $issueid, $isMergeRequest);
        if(!$issue->isValid()) {
            try {
                $issue->getFromService();
                $issue->saveToDB();
            } catch (Exception $e) {
                // that's fine
            }
        }
        return $issue;
    }

    /**
     * Parse the keys to issues from a gitcommit-Message
     *
     * @param string $rmServiceName The repository management service from which we are handling the commits
     * @param string $repoId        The name of the repo
     * @param string $message       The git commit-message
     * @return array
     */
    public function parseIssueKeysFromText($rmServiceName, $repoId, $message) {
        $issues = array();

        $jiraMatches = array();
        $jiraPattern = '/[A-Z0-9]+-[1-9]\d*/';
        preg_match_all($jiraPattern, $message, $jiraMatches);
        foreach ($jiraMatches[0] as $match) {
            list($project, $issueId) = explode('-', $match);
            $issues[] = array('pmService' => 'jira',
                'project' => $project,
                'issueId' => $issueId,
            );
        }

        $repoMatches = array();
        $repoPattern = '/(\w+\/)?([\w\.\-_]+)?([#!])(\d+)(?:[\.,\s]|$)/';
        preg_match_all($repoPattern, $message, $repoMatches,PREG_SET_ORDER);
        list($currentNamespace, $currentRepo) = explode('/', $repoId);
        foreach ($repoMatches as $match) {
            if ($rmServiceName !== 'gitlab' && $match[3] === '!') {
                continue; // only gitlab has `!` has separator
            }
            $namespace = empty($match[1]) ? $currentNamespace : trim($match[1],'/');
            $repo = empty($match[2]) ? $currentRepo : $match[2];
            $issues[] = array('pmService' => $rmServiceName,
                'project' => "$namespace/$repo",
                'issueId' => $match[4],
                'isMergeRequest' => $match[3] === '!',
            );
        }

        return $issues;
    }


    public function getMergeRequestsForIssue($serviceName, $projectKey, $issueId, $isMergeRequest) {
        /** @var helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $issues = $db->getMergeRequestsReferencingIssue($serviceName, $projectKey, $issueId, $isMergeRequest);
        foreach ($issues as &$issueData) {
            $issue = Issue::getInstance($issueData['service'], $issueData['project_id'], $issueData['issue_id'], $issueData['is_mergerequest']);
            $issue->getFromDB();
            $issueData['summary'] = $issue->getSummary();
            $issueData['status'] = $issue->getStatus();
            $issueData['url'] = $issue->getIssueURL();
        }
        unset($issueData);

        return $issues;
    }

    /**
     * remove duplicate revisions of a page and keep only the newest
     *
     * @param array $pages Array of pages sorted(!) from newest to oldest
     *
     * @return array
     */
    public function keepNewest($pages) {
        $uniquePages = array();
        foreach ($pages as $page) {
            if (!array_key_exists($page['page'],$uniquePages) || $uniquePages[$page['page']]['rev'] < $page['rev'] ) {
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
    private function filterPagesForACL($pages) {
        $allowedPagegs = array();
        foreach ($pages as $page) {
            if(auth_quickaclcheck($page['page']) >= AUTH_READ) {
                $allowedPagegs[] = $page;
            }
        }
        return $allowedPagegs;
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
    public function getLinkingPages($pmServiceName, $projectKey, $issueId, $isMergeRequest) {
        $pages = $this->db->getAllPageLinkingToIssue($pmServiceName, $projectKey, $issueId, $isMergeRequest);
        $pages = $this->db->removeOldLinks($pmServiceName, $projectKey, $issueId, $isMergeRequest, $pages);

        if (false && plugin_load('helper', 'struct_db')) { // FIXME
            $structPages = $this->getLinkingPageFromStruct($pmServiceName, $projectKey, $issueId, $isMergeRequest);
            $pages = array_merge($pages, $structPages);
        }

        if (empty($pages)) {
            return array();
        }

        $pages = $this->keepNewest($pages);
        $pages = $this->filterPagesForACL($pages);
        $pages = $this->addUserToPages($pages);
        return $pages;
    }

    protected function getLinkingPageFromStruct($pmServiceName, $projectKey, $issueId, $isMergeRequest) {
        $sep = PMServiceBuilder::getProjectIssueSeparator($pmServiceName, $isMergeRequest);
        $sep = $sep === '#' ? '\#' : $sep;
        if ($pmServiceName === 'github') {
            $pmServiceName = 'gh';
        }
        if ($pmServiceName === 'gitlab') {
            $pmServiceName = 'gl';
        }
        $issueSyntax = "[[$pmServiceName>$projectKey$sep$issueId]]";
        /** @var helper_plugin_sqlite $sqlite */
        $sqlite = plugin_load('helper', 'sqlite');
        $sqlColumns = '
            SELECT tbl, label, MAX(ts) as ts
            FROM schemas
            JOIN (
                SELECT sid, label
                FROM schema_cols
                JOIN (
                    SELECT id, label
                    FROM types
                    WHERE class=\'Issue\'
                )
                ON tid=id
                WHERE enabled = 1
            )
            ON id=sid
            WHERE islookup = 0
            GROUP BY tbl, label;';
        $sqlite->init('struct', DOKU_PLUGIN . 'struct/db/');
        $res = $sqlite->query($sqlColumns);
        $columns = $sqlite->res2arr($res);
        $sqlite->res_close($res);

        $sqlTables = 'SELECT tbl, MAX(ts) AS ts FROM schemas GROUP BY tbl;';
        $res = $sqlite->query($sqlTables);
        $tables = array_reduce($sqlite->res2arr($res), function($carry, $element) {
            $carry[$element['tbl']] = $element['ts'];
            return $carry;
        }, array());
        $sqlite->res_close($res);

        $configLines = array(
            'cols: %pageid%',
        );
        $schemas = array();
        foreach ($columns as $column) {
            if ($tables[$column['tbl']] !== $column['ts']) {
                continue;
            }
            $schemas[] = $column['tbl'];
            $configLines[] = "filteror: $column[tbl].$column[label]=$issueSyntax";
        }

        if (empty($schemas)) {
            return array();
        }
        $schemas = array_unique($schemas);
        array_unshift($configLines, 'schema: ' . implode(', ', $schemas));

        $config = new dokuwiki\plugin\struct\meta\ConfigParser($configLines);
        $search = new dokuwiki\plugin\struct\meta\SearchConfig($config->getConfig());
        $results = $search->execute();
        $pages = array_map(function($result) { return array('page' => $result[0]->getRawValue(), 'rev' => 0);}, $results);
        return $pages;
    }

    /**
     * add the corresponding user to each revision
     *
     * @param array $pages
     *
     * @return array
     */
    public function addUserToPages($pages) {
        foreach ($pages as &$page) {
            $changelog = new PageChangelog($page['page']);
            $revision = $changelog->getRevisionInfo($page['rev']);
            $page['user'] = $revision['user'];
        }
        return $pages;
    }
}

// vim:ts=4:sw=4:et:
