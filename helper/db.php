<?php
/**
 * DokuWiki Plugin issuelinks (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
use dokuwiki\plugin\issuelinks\classes\Issue;

if(!defined('DOKU_INC')) die();

class helper_plugin_issuelinks_db extends DokuWiki_Plugin
{
    private $db = null;

    /**
     * Gives access to the sqlite DB.
     *
     * Returns null on error
     *
     * @return helper_plugin_sqlite|null
     * @throws Exception Only thrown in unittests
     */
    public function getDB() {
        if(null === $this->db) {
            /** @var helper_plugin_sqlite $sqlite */
            $sqlite = plugin_load('helper', 'sqlite');
            if(!$sqlite) {
                msg('This plugin requires the sqlite plugin. Please install it', -1);
                return null;
            }

            if($sqlite->getAdapter()->getName() !== DOKU_EXT_PDO) {
                if(defined('DOKU_UNITTEST')) throw new \Exception('Couldn\'t load PDO sqlite.');
                return null;
            }
            $sqlite->getAdapter()->setUseNativeAlter(true);

            // initialize the database connection
            if(!$sqlite->init('issuelinks', DOKU_PLUGIN . 'issuelinks/db/')) {
                return null;
            }

            $this->db = $sqlite;
        }
        return $this->db;
    }



    /**
     * Save a key value pair to the database
     *
     * @param $key
     * @param $value
     * @return bool|null Returns false on error, nothing otherwise
     */
    public function saveKeyValuePair($key, $value) {
        $db = $this->getDB();
        if(!$db) return false;
        $sql = 'REPLACE INTO opts VALUES (?, ?)';
        $db->query($sql, array($key, $value));
    }

    /**
     * Get a value to a stored key from the database
     *
     * @param $key
     * @return bool|string
     */
    public function getKeyValue($key) {
        $db = $this->getDB();
        if(!$db) return false;
        $sql = 'SELECT val FROM opts WHERE opt = ?';
        $res = $db->query($sql, array($key));
        $value = $db->res2single($res);
        $db->res_close($res);
        return $value;
    }


    /**
     * @param string $service   The name of the repository management service
     * @param string $repo      The repository
     * @param string $id        The id of the webhook
     * @param string $secret    The secret to use when authenicationg incoming webhooks
     */
    public function saveWebhook($service, $repo, $id, $secret) {
        $entity = array(
            'service' => $service,
            'repository_id' => $repo,
            'id' => $id,
            'secret' => $secret
        );
        $this->saveEntity('webhooks', $entity);
    }

    /**
     * Get the stored secret used to authenticate an incoming webhook
     *
     * @param string $rmservice
     * @param string $repo
     * @return array
     */
    public function getWebhookSecrets($service, $repo) {
        $sql = "SELECT secret FROM webhooks WHERE service = ? AND repository_id = ?";
        $secrets = $this->sqlArrayQuery($sql, array($service, $repo));
        return $secrets;
    }

    /**
     * @param string $service
     * @param string $repo
     * @param string $id
     */
    public function deleteWebhook($service, $repo, $id) {
        $entity = array(
            'service' => $service,
            'repository_id' => $repo,
            'id' => $id
        );
        $this->deleteEntity('webhooks', $entity);
    }

    public function getWebhooks($service, $repo = null, $id = null)
    {
        $sql = 'SELECT * FROM webhooks WHERE service = ?';
        $params = [$service];
        if ($repo) {
            $sql .= ' AND repository_id = ?';
            $params[] = $repo;
        }
        if ($id) {
            $sql .= ' AND id = ?';
            $params[] = $id;
        }

        $webhooks = $this->sqlArrayQuery($sql, $params);
        return $webhooks;
    }


    /**
     * Save an issue into the database
     *
     * @param Issue $issue
     * @return bool
     */
    public function saveIssue(Issue $issue) {
        $ok = $this->saveEntity('issues', array (
            'service' => $issue->getServiceName(),
            'project' => $issue->getProject(),
            'id' => $issue->getKey(),
            'is_mergerequest' => $issue->isMergeRequest() ? '1' : '0',
            'summary' => $issue->getSummary(),
            'description' => $issue->getDescription(),
            'type' => $issue->getType(),
            'status' => $issue->getStatus(),
            'parent' => $issue->getParent(),
            'components' => implode(',',$issue->getComponents()),
            'labels' => implode(',', $issue->getLabels()),
            'priority' => $issue->getPriority(),
            'duedate' => $issue->getDuedate(),
            'versions' => implode(',', $issue->getVersions()),
            'updated' => $issue->getUpdated()
        ));
        return (bool)$ok;
    }


    /**
     * Query the database for the issue corresponding to the given project and issueId
     *
     * @param string $serviceName The name of the project management service
     * @param string $projectKey  The short-key of a project, e.g. SPR
     * @param int    $issueId     The id of an issue e.g. 42
     *
     * @return bool|array
     */
    public function loadIssue($serviceName, $projectKey, $issueId, $isMergeRequest) {
        $sql = 'SELECT * FROM issues WHERE service = ? AND project = ? AND id = ? AND is_mergerequest = ?';
        $issues = $this->sqlArrayQuery($sql, array($serviceName, $projectKey, $issueId, $isMergeRequest ? 1 : 0));
        return blank($issues[0]) ? false : $issues[0];
    }

    public function saveIssueIssues(Issue $issue, array $issues) {
        $this->deleteEntity('issue_issues', array(
            'service' => $issue->getServiceName(),
            'project' => $issue->getProject(),
            'id' => $issue->getKey(),
            'is_mergerequest' => $issue->isMergeRequest() ? 1 : 0,
        ));
        foreach ($issues as $issueData) {
            $this->saveEntity('issue_issues', array(
                'service' => $issue->getServiceName(),
                'project' => $issue->getProject(),
                'id' => $issue->getKey(),
                'is_mergerequest' => $issue->isMergeRequest() ? 1 : 0,
                'referenced_service' => $issueData['service'],
                'referenced_project' => $issueData['project'],
                'referenced_id' => $issueData['issueId'],
                'referenced_is_mergerequest' => 0,
            ));
        }
    }

    public function getMergeRequestsReferencingIssue($serviceName, $project, $issueId, $isMergeRequest) {
        $sql = '
        SELECT service, project as project_id, id as issue_id, is_mergerequest
        FROM issue_issues
        WHERE referenced_service = ?
        AND referenced_project = ?
        AND referenced_id = ?
        AND referenced_is_mergerequest = ?
        AND is_mergerequest = 1
        ';
        return $this->sqlArrayQuery($sql, array($serviceName, $project, $issueId, $isMergeRequest ? 1 : 0));
    }

    /**
     * Query the database for pages with link-syntax to the given issue
     *
     * @param string $serviceName The name of the project management service
     * @param string $projectKey  The project short-key
     * @param int    $issue_id    The ID of the issue, e.g. 42
     *
     * @return array
     */
    public function getAllPageLinkingToIssue($serviceName, $projectKey, $issue_id, $isMergeRequest) {
        $sql = "SELECT page, rev
                FROM pagerev_issues
                WHERE service = ?
                AND project_id = ?
                AND issue_id = ?
                AND is_mergerequest = ?
                AND type = 'link'
                ORDER BY rev DESC ";
        return $this->sqlArrayQuery($sql, array($serviceName, $projectKey, $issue_id, $isMergeRequest ? 1 : 0));
    }

    /**
     * Delete "Link"-references to old revisions from database
     *
     * @param string $serviceName The name of the project management service
     * @param string $projectKey  The short-key for the project, e.g. SPR
     * @param int    $issue_id    The id of the issue, e.g. 42
     * @param array  $pages
     *
     * @return array
     */
    public function removeOldLinks($serviceName, $projectKey, $issue_id, $isMergeRequest, $pages) {
        $activeLinks = array();

        foreach($pages as $linkingPage) {
            $changelog = new PageChangelog($linkingPage['page']);
            $currentRev = $changelog->getRelativeRevision(time(), -1);
            if($linkingPage['rev'] < $currentRev) {
                $entity = array(
                    'page'     => $linkingPage['page'],
                    'issue_id' => $issue_id,
                    'project_id' => $projectKey,
                    'service' => $serviceName,
                    'is_mergerequest' => $isMergeRequest ? '1' : '0',
                    'type'     => 'link',
                );
                $this->deleteEntity('pagerev_issues', $entity);
            } else {
                $activeLinks[] = $linkingPage;
            }
        }
        return $activeLinks;
    }

    /**
     * Save the connection between a Jira issue and a revision of a page.
     *
     * @param string $page
     * @param int    $rev
     * @param string $serviceName The name of the project management service
     * @param string $project
     * @param int    $issue_id
     * @param string $type
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     */
    public function savePageRevIssues($page, $rev, $serviceName, $project, $issue_id, $isMergeRequest, $type) {
        /** @var helper_plugin_issuelinks_util $util */
        $util = plugin_load('helper', 'issuelinks_util');
        if (!$util->isValidTimeStamp($rev)) {
            throw new InvalidArgumentException("Second parameter must be a valid timestamp!");
        }
        if ((int)$rev === 0) {
            $rev = filemtime(wikiFN($page));
            $changelog = new PageChangelog($page);
            $rev_info = $changelog->getRevisionInfo($rev);
            $user = $rev_info['user'] ? $rev_info['user'] : $rev_info['ip'];
            $this->savePageRev($page, $rev, $rev_info['sum'], $user);
        }
        /** @noinspection TypeUnsafeComparisonInspection this is done to ensure $issue_id is a natural number */
        if (!is_numeric($issue_id) || (int)$issue_id != $issue_id) {
            throw new InvalidArgumentException("IssueId must be an integer!");
        }
        $ok = $this->saveEntity('pagerev_issues', array (
            'page' => $page,
            'rev' => $rev,
            'service' => $serviceName,
            'project_id' => $project,
            'issue_id' => $issue_id,
            'is_mergerequest' => $isMergeRequest ? '1' : '0',
            'type' => $type
        ));

        return (bool)$ok;
    }

    /**
     * Save the data about a pagerevision
     *
     * @param string $page
     * @param int    $rev
     * @param string $summary
     * @param string $user
     * @return bool
     */
    public function savePageRev($page, $rev, $summary, $user) {
        if (blank($page) || blank($rev) || blank($user)) {
            throw new InvalidArgumentException("No empty values allowed!");
        }
        /** @var helper_plugin_issuelinks_util $util */
        $util = plugin_load('helper', 'issuelinks_util');
        if (!$util->isValidTimeStamp($rev)) {
            throw new InvalidArgumentException("Second parameter must be a valid timestamp!");
        }
        $ok = $this->saveEntity('pagerevs', array (
            'page' => $page,
            'rev' => $rev,
            'summary' => $summary,
            'user' => $user,
        ));
        return (bool)$ok;
    }

    /**
     * Delete ALL entries from the database that correspond to the given page, issue and type.
     *
     * @param string $page        the wikipage
     * @param string $serviceName The name of the project management service
     * @param string $projectKey  the key of the project, e.g. SPR
     * @param int    $issueId     the id of the issue, e.g. 42
     * @param bool   $isMergeRequest
     * @param string $type        either 'context' or 'link'
     */
    public function deleteAllIssuePageRevisions($page, $serviceName, $projectKey, $issueId, $isMergeRequest, $type) {
        // todo: validation
        $this->deleteEntity('pagerev_issues', array(
            'page' => $page,
            'service' => $serviceName,
            'project_id' => $projectKey,
            'issue_id' => $issueId,
            'is_mergerequest' => $isMergeRequest ? 1 : 0,
            'type' => $type
        ));
    }

    /**
     * Deletes the given key-value array to the given table
     *
     * @param string $table
     * @param array $entity associative array holding the key/value pairs for the where clause
     */
    private function deleteEntity($table, $entity) {
        $db = $this->getDB();
        if(!$db) return;

        $where = implode(' = ? AND ', array_keys($entity)) . ' = ?';
        $vals = array_values($entity);

        $sql = "DELETE FROM $table WHERE $where";
        $db->query($sql, $vals);
    }

    /**
     * Saves the given key-value array to the given table
     *
     * @param string $table
     * @param array $entity associative array holding the key/value pairs
     * @return bool|\SQLiteResult
     */
    private function saveEntity($table, $entity) {
        $db = $this->getDB();
        if(!$db) return false;

        $keys = implode(', ', array_keys($entity));
        $vals = array_values($entity);
        $wlds = implode(', ', array_fill(0, count($vals), '?'));

        $sql = "REPLACE INTO $table ($keys) VALUES ($wlds)";
        $ok = $db->query($sql, $vals);
        if (empty($ok)) {
            global $conf;
            msg("Saving into table $table failed!", -1);
            msg(print_r($entity, true), -1);
            if ($conf['debug']) {
                msg(dbg_backtrace(), -1);
            }
        }
        return $ok;
    }

    /**
     * make a provided sql query and return the resulting lines as an array of associative arrays
     *
     * @param string        $sql          the query
     * @param string|array  $conditional  the parameters of the query
     *
     * @return array|bool
     */
    private function sqlArrayQuery($sql, $conditional) {
        if (substr(trim($sql),0,strlen('SELECT')) !== 'SELECT') {
            throw new InvalidArgumentException("SQL-Statement must be a SELECT statement! \n" . $sql);
        }
        if (strpos(trim($sql,';'), ';') !== false) {
            throw new InvalidArgumentException("SQL-Statement must be one single statement! \n" . $sql);
        }
        $db = $this->getDB();
        if(!$db) return false;

        $res = $db->query($sql,$conditional);
        $result = $db->res2arr($res);
        $db->res_close($res);
        return $result;
    }

}
