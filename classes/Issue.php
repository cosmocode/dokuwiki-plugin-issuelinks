<?php

namespace dokuwiki\plugin\issuelinks\classes;

/**
 *
 * Class Issue
 */
class Issue extends \DokuWiki_Plugin implements \JsonSerializable
{

    /** @var Issue[] */
    private static $instances = [];
    protected $issueId;
    protected $projectId;
    protected $isMergeRequest;
    protected $files = [];
    protected $serviceID = '';
    private $summary = '';
    private $description = '';
    private $status = '';
    private $type = 'unknown';
    private $components = [];
    private $labels = [];
    private $priority = '';
    private $assignee = [];
    private $labelData = [];
    private $versions = [];
    private $duedate;
    private $updated;
    private $parent;
    private $errors = [];
    private $isValid;

    /**
     * @param        $serviceName
     * @param string $projectKey The shortkey of the project, e.g. SPR
     * @param int    $issueId    The id of the issue, e.g. 42
     *
     * @param        $isMergeRequest
     */
    private function __construct($serviceName, $projectKey, $issueId, $isMergeRequest)
    {
        if (empty($serviceName) || empty($projectKey) || empty($issueId) || !is_numeric($issueId)) {
            throw new \InvalidArgumentException('Empty value passed to Issue constructor');
        }

        $this->issueId = $issueId;
        $this->projectId = $projectKey;
        $this->isMergeRequest = $isMergeRequest;
        $this->serviceID = $serviceName;

//        $this->getFromDB();
    }

    /**
     * Get the singleton instance of a issue
     *
     * @param      $serviceName
     * @param      $projectKey
     * @param      $issueId
     * @param bool $isMergeRequest
     * @param bool $forcereload create a new instace
     *
     * @return Issue
     */
    public static function getInstance(
        $serviceName,
        $projectKey,
        $issueId,
        $isMergeRequest = false,
        $forcereload = false
    ) {
        $issueHash = $serviceName . $projectKey . $issueId . '!' . $isMergeRequest;
        if (empty(self::$instances[$issueHash]) || $forcereload) {
            self::$instances[$issueHash] = new Issue($serviceName, $projectKey, $issueId, $isMergeRequest);
        }
        return self::$instances[$issueHash];
    }

    /**
     * @return bool true if issue was found in database, false otherwise
     */
    public function getFromDB()
    {
        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $issue = $db->loadIssue($this->serviceID, $this->projectId, $this->issueId, $this->isMergeRequest);
        if (empty($issue)) {
            return false;
        }
        $this->summary = $issue['summary'] ?: '';
        $this->status = $issue['status'];
        $this->type = $issue['type'];
        $this->description = $issue['description'];
        $this->setComponents($issue['components']);
        $this->setLabels($issue['labels']);
        $this->priority = $issue['priority'];
        $this->duedate = $issue['duedate'];
        $this->setVersions($issue['versions']);
        $this->setUpdated($issue['updated']);
        return true;
    }

    public function __toString()
    {
        $sep = $this->pmService->getProjectIssueSeparator($this->isMergeRequest);
        return $this->projectId . $sep . $this->issueId;
    }

    /**
     * @return \Exception|null
     */
    public function getLastError()
    {
        if (!end($this->errors)) {
            return null;
        }
        return end($this->errors);
    }

    /**
     * @return bool|self
     */
    public function isMergeRequest($isMergeRequest = null)
    {
        if ($isMergeRequest === null) {
            return $this->isMergeRequest;
        }

        $this->isMergeRequest = $isMergeRequest;
        return $this;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     *
     * @link  http://stackoverflow.com/a/4697671/3293343
     */
    public function jsonSerialize()
    {
        return [
            'service' => $this->serviceID,
            'project' => $this->getProject(),
            'id' => $this->getKey(),
            'isMergeRequest' => $this->isMergeRequest ? '1' : '0',
            'summary' => $this->getSummary(),
            'description' => $this->getDescription(),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'parent' => $this->getParent(),
            'components' => $this->getComponents(),
            'labels' => $this->getLabels(),
            'priority' => $this->getPriority(),
            'duedate' => $this->getDuedate(),
            'versions' => $this->getVersions(),
            'updated' => $this->getUpdated(),
        ];
    }

    /**
     * @return string
     */
    public function getProject()
    {
        return $this->projectId;
    }

    /**
     * Returns the key, i.e. number, of the issue
     *
     * @param bool $annotateMergeRequest If true, prepends a `!` to the key of a merge requests
     *
     * @return int|string
     */
    public function getKey($annotateMergeRequest = false)
    {
        if ($annotateMergeRequest && $this->isMergeRequest) {
            return '!' . $this->issueId;
        }
        return $this->issueId;
    }

    public function getSummary()
    {
        return $this->summary;
    }

    /**
     * @param string $summary
     *
     * @return Issue
     *
     * todo: decide if we should test for non-empty string here
     */
    public function setSummary($summary)
    {
        $this->summary = $summary;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @return Issue
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return Issue
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     *
     * @return Issue
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getParent()
    {
        return $this->parent;
    }

    public function setParent($key)
    {
        $this->parent = $key;
        return $this;
    }

    /**
     * @return array
     */
    public function getComponents()
    {
        return $this->components;
    }

    /**
     * @param array|string $components
     *
     * @return Issue
     */
    public function setComponents($components)
    {
        if (is_string($components)) {
            $components = array_filter(array_map('trim', explode(',', $components)));
        }
        if (!empty($components[0]['name'])) {
            $components = array_column($components, 'name');
        }
        $this->components = $components;
        return $this;
    }

    /**
     * @return array
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * @param array|string $labels
     *
     * @return Issue
     */
    public function setLabels($labels)
    {
        if (!is_array($labels)) {
            $labels = array_filter(array_map('trim', explode(',', $labels)));
        }
        $this->labels = $labels;
        return $this;
    }

    /**
     * @return string
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param string $priority
     *
     * @return Issue
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return string
     */
    public function getDuedate()
    {
        return $this->duedate;
    }

    /**
     * Set the issues duedate
     *
     * @param string $duedate
     *
     * @return Issue
     */
    public function setDuedate($duedate)
    {
        $this->duedate = $duedate;
        return $this;
    }

    /**
     * @return array
     */
    public function getVersions()
    {
        return $this->versions;
    }

    /**
     * @param array|string $versions
     *
     * @return Issue
     */
    public function setVersions($versions)
    {
        if (!is_array($versions)) {
            $versions = array_map('trim', explode(',', $versions));
        }
        if (!empty($versions[0]['name'])) {
            $versions = array_map(function ($version) {
                return $version['name'];
            }, $versions);
        }
        $this->versions = $versions;
        return $this;
    }

    /**
     * @return int
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * @param string|int $updated
     *
     * @return Issue
     */
    public function setUpdated($updated)
    {
        /** @var \helper_plugin_issuelinks_util $util */
        $util = plugin_load('helper', 'issuelinks_util');
        if (!$util->isValidTimeStamp($updated)) {
            $updated = strtotime($updated);
        }
        $this->updated = (int)$updated;
        return $this;
    }

    /**
     * Get a fancy HTML-link to issue
     *
     * @param bool $addSummary add the issue's summary after it status
     *
     * @return string
     */
    public function getIssueLinkHTML($addSummary = false)
    {
        $serviceProvider = ServiceProvider::getInstance();
        $service = $serviceProvider->getServices()[$this->serviceID];
        $name = $this->projectId . $service::getProjectIssueSeparator($this->isMergeRequest) . $this->issueId;
        $url = $this->getIssueURL();

        $status = cleanID($this->getStatus());
        if ($status) {
            $name .= $this->getformattedIssueStatus();
        }
        if ($addSummary) {
            $name .= ' ' . $this->getSummary();
        }

        $target = 'target="_blank" rel="noopener"';
        $classes = 'issuelink ' . cleanID($this->getType()) . ($this->isMergeRequest ? ' mergerequest' : '');
        $dataAttributes = "data-service=\"$this->serviceID\" data-project=\"$this->projectId\" data-issueid=\"$this->issueId\"";
        $dataAttributes .= ' data-ismergerequest="' . ($this->isMergeRequest ? '1' : '0') . '"';
        return "<a href=\"$url\" class=\"$classes\" $dataAttributes $target>" . $this->getTypeHTML() . "$name</a>";
    }

    /**
     * @return string
     */
    public function getIssueURL()
    {
        $serviceProvider = ServiceProvider::getInstance();
        $service = $serviceProvider->getServices()[$this->serviceID]::getInstance();
        return $service->getIssueURL($this->projectId, $this->issueId, $this->isMergeRequest);
    }

    /**
     * get the status of the issue as HTML string
     *
     * @param string|null $status
     *
     * @return string
     */
    public function getformattedIssueStatus($status = null)
    {
        if ($status === null) {
            $status = $this->getStatus();
        }
        $status = strtolower($status);
        return "<span class='mm__status " . cleanID($status) . "'>$status</span>";
    }

    /**
     * @return string
     */
    public function getTypeHTML()
    {
        if ($this->isMergeRequest) {
            return inlineSVG(__DIR__ . '/../images/mdi-source-pull.svg');
        }
        $image = $this->getMaterialDesignTypeIcon();
        return "<img src='$image' alt='$this->type' />";
    }

    /**
     * ToDo: replace all with SVG
     *
     * @return string the path to the icon / base64 image if type unknown
     */
    protected function getMaterialDesignTypeIcon()
    {
        $typeIcon = [
            'bug' => 'mdi-bug.png',
            'story' => 'mdi-bookmark.png',
            'epic' => 'mdi-flash.png',
            'change_request' => 'mdi-plus.png',
            'improvement' => 'mdi-arrow-up-thick.png',
            'organisation_task' => 'mdi-calendar-text.png',
            'technical_task' => 'mdi-source-branch.png',
            'task' => 'mdi-check.png',
        ];

        if (!isset($typeIcon[cleanID($this->type)])) {
            return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAABDUlEQVQYGQXBLWuVcQDA0fM8272OIYLCmi+IOBBWhWEZohiHn0AQi/H3CQxaLVptgmmIacUwWLthsDiQBaOmIaYF+XsOgHb61N9Glx30qAkAtOigVbttttZGO31t1VUArXfeCwCg3S66Buhzr6Blb/rVeS+b6WEnTehuZ0206Gej0Wh0CH3pCXrXM2ijVW+bW3bS6Bbd6xiddQNogpadNrpDa40mXbYBQI+7bPS9CRotdN51gOZGo9dN0Nxo1vv2AFpr1RFAtztBD1oBtOhffwD62D7osH2gZaN/QNv9aAZd6XdPgZYtoPtdtAWgzY771nbrNHezD523BQCa2uuo0Wh02vNmAADQ1KIZAPgPQZt8UVJ7VXIAAAAASUVORK5CYII=';
        }

        return DOKU_URL . '/lib/plugins/issuelinks/images/' . $typeIcon[cleanID($this->type)];
    }

    public function setAssignee($name, $avatar_url)
    {
        $this->assignee['name'] = $name;
        $this->assignee['avatarURL'] = $avatar_url;
    }

    public function getAdditionalDataHTML()
    {
        $this->getFromService();
        $data = [];
        if (!empty($this->assignee)) {
            $data['avatarHTML'] = "<img src=\"{$this->assignee['avatarURL']}\" alt=\"{$this->assignee['name']}\">";
        }
        if (!empty($this->labelData)) {
            $labels = $this->getLabels();
            $data['fancyLabelsHTML'] = '';
            foreach ($labels as $label) {
                $colors = '';
                $classes = 'label';
                if (isset($this->labelData[$label])) {
                    $colors = "style=\"background-color: {$this->labelData[$label]['background-color']};";
                    $colors .= " color: {$this->labelData[$label]['color']};\"";
                    $classes .= ' color';
                }
                $data['fancyLabelsHTML'] .= "<span class=\"$classes\" $colors>$label</span>";
            }
        }
        return $data;
    }

    public function getFromService()
    {
        $serviceProvider = ServiceProvider::getInstance();
        $service = $serviceProvider->getServices()[$this->serviceID]::getInstance();

        try {
            $service->retrieveIssue($this);
            if ($this->isValid(true)) {
                $this->saveToDB();
            }
        } catch (IssueLinksException $e) {
            $this->errors[] = $e;
            $this->isValid = false;
            return false;
        }
        return true;
    }

    /**
     * Check if an issue is valid.
     *
     * The specific rules depend on the service and the cached value may also be set by other functions.
     *
     * @param bool $recheck force a validity check instead of using cached value if available
     *
     * @return bool
     */
    public function isValid($recheck = false)
    {
        if ($recheck || $this->isValid === null) {
            $serviceProvider = ServiceProvider::getInstance();
            $service = $serviceProvider->getServices()[$this->serviceID];
            $this->isValid = $service::isIssueValid($this);
        }
        return $this->isValid;
    }

    public function saveToDB()
    {
        /** @var \helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        return $db->saveIssue($this);
    }

    public function buildTooltipHTML()
    {
        $html = '<aside class="issueTooltip">';
        $html .= "<h1 class=\"issueTitle\">{$this->getSummary()}</h1>";
        $html .= "<div class='assigneeAvatar waiting'></div>";

        /** @var \helper_plugin_issuelinks_util $util */
        $util = plugin_load('helper', 'issuelinks_util');

        $components = $this->getComponents();
        if (!empty($components)) {
            $html .= '<p class="components">';
            foreach ($components as $component) {
                $html .= "<span class=\"component\">$component</span>";
            }
            $html .= '</p>';
        }

        $labels = $this->getLabels();
        if (!empty($labels)) {
            $html .= '<p class="labels">';
            foreach ($labels as $label) {
                $html .= "<span class=\"label\">$label</span>";
            }
            $html .= '</p>';
        }

        $html .= '<p class="descriptionTeaser">';
        $description = $this->getDescription();
        if ($description) {
            $lines = explode("\n", $description);
            $cnt = min(count($lines), 5);
            for ($i = 0; $i < $cnt; $i += 1) {
                $html .= hsc($lines[$i]) . "\n";
            }
        } else {
            $html .= $util->getLang('no issue description');
        }
        $html .= '</p>';

        /** @var \helper_plugin_issuelinks_data $data */
        $data = $this->loadHelper('issuelinks_data');

        if (!$this->isMergeRequest) {
            // show merge requests referencing this Issues
            $mrs = $data->getMergeRequestsForIssue($this->getServiceName(), $this->getProject(), $this->issueId,
                $this->isMergeRequest);
            if (!empty($mrs)) {
                $html .= '<div class="mergeRequests">';
                $html .= '<h2>Merge Requests</h2>';
                $html .= '<ul>';
                foreach ($mrs as $mr) {
                    $html .= '<li>';
                    $a = "<a href=\"$mr[url]\">$mr[summary]</a>";
                    $html .= $this->getformattedIssueStatus($mr['status']) . ' ' . $a;
                    $html .= '</li>';
                }
                $html .= '</ul>';
                $html .= '</div>';
            }
        }

        $linkingPages = $data->getLinkingPages($this->getServiceName(), $this->getProject(), $this->issueId,
            $this->isMergeRequest);
        if (count($linkingPages)) {
            $html .= '<div class="relatedPages📄">';
            $html .= '<h2>' . $util->getLang('linking pages') . '</h2>';
            $html .= '<ul>';
            foreach ($linkingPages as $linkingPage) {
                $html .= '<li>';
                $html .= html_wikilink($linkingPage['page']);
                $html .= '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        $html .= '</aside>';
        return $html;
    }

    public function getServiceName()
    {
        return $this->serviceID;
    }

    /**
     * @param string $labelName the background color without the leading #
     * @param string $color
     */
    public function setLabelData($labelName, $color)
    {
        $this->labelData[$labelName] = [
            'background-color' => $color,
            'color' => $this->calculateColor($color),
        ];
    }

    /**
     * Calculate if a white or black font-color should be with the given background color
     *
     * https://www.w3.org/TR/WCAG20/#relativeluminancedef
     * http://stackoverflow.com/a/3943023/3293343
     *
     * @param string $color the background-color, without leading #
     *
     * @return string
     */
    private function calculateColor($color)
    {
        /** @noinspection PrintfScanfArgumentsInspection */
        list($r, $g, $b) = array_map(function ($color8bit) {
            $c = $color8bit / 255;
            if ($c <= 0.03928) {
                $cl = $c / 12.92;
            } else {
                $cl = pow(($c + 0.055) / 1.055, 2.4);
            }
            return $cl;
        }, sscanf($color, "%02x%02x%02x"));
        if ($r * 0.2126 + $g * 0.7152 + $b * 0.0722 > 0.179) {
            return '#000000';
        }
        return '#FFFFFF';
    }
}
