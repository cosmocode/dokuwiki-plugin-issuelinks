<?php

/**
 * Tests for the handling the syntax of a link to an jira issue in the issuelinks plugin
 *
 * @group plugin_issuelinks
 * @group plugins
 *
 */
class linksyntax_plugin_issuelinks_test extends DokuWikiTest {

    protected $pluginsEnabled = array('issuelinks', 'sqlite',);

    public function setUp() {
        parent::setUp();
    }

    public function tearDown() {
        parent::tearDown();
        /** @var helper_plugin_issuelinks_db $db */
        $db = plugin_load('helper', 'issuelinks_db');
        $db->reset();
    }

    public function test_jiralink() {
        // arrange
        global $ID;
        $ID = 'testpage';
        saveWikiText('testpage','[[jira>SPR-281]] [[jira>TW-7]]','test summary');

        // act
        p_get_instructions('[[jira>SPR-281]]');
        p_get_instructions('[[jira>TW-7]]');

        // assert
        /** @var \helper_plugin_sqlite $sqliteHelper */
        $sqliteHelper = plugin_load('helper', 'sqlite');
        if (!$sqliteHelper->init('issuelinks', DOKU_PLUGIN . 'issuelinks/db/')) {
            throw new \Exception('sqlite init failed');
        }

        $sql = 'SELECT page, project_id, issue_id, type FROM pagerev_issues';
        $pagerev_issues = $sqliteHelper->res2arr($sqliteHelper->query($sql, array()));

        $this->assertEquals(array(
            array(
                'page' => 'testpage',
                'project_id' => 'SPR',
                'issue_id' => '281',
                'type' => 'link',
            ),
            array(
                'page' => 'testpage',
                'project_id' => 'TW',
                'issue_id' => '7',
                'type' => 'link',
            )), $pagerev_issues);
    }

    public function test_jiralink_oldrev() {
        // arrange
        global $ID, $REV;
        $ID = 'testpage_oldnew';
        saveWikiText($ID,'{{jira>SPR-281}} {{jira>TW-7}}','test summary');
        $REV = filemtime(wikiFN($ID))+1;

        // act
        p_get_instructions('{{jira>SPR-281}}');
        p_get_instructions('{{jira>TW-7}}');

        // assert
        /** @var \helper_plugin_sqlite $sqliteHelper */
        $sqliteHelper = plugin_load('helper', 'sqlite');
        if (!$sqliteHelper->init('issuelinks', DOKU_PLUGIN . 'issuelinks/db/')) {
            throw new \Exception('sqlite init failed');
        }

        $sql = 'SELECT page, project_id, issue_id, type FROM pagerev_issues';
        $pagerev_issues = $sqliteHelper->res2arr($sqliteHelper->query($sql, array()));

        $this->assertEquals(array(), $pagerev_issues);
    }

    public function test_jiralink_moresyntax() {
        // arrange
        global $ID;
        $ID = 'testpage2';
        saveWikiText($ID,'page must exist for m_filetime','test summary');

        // act
        p_get_instructions('[[gh>cosmocode/dokuwiki-plugin-issuelinks#1]]');
        p_get_instructions('[[jira>TW-7]]');
        p_get_instructions('[[gl>grosse/project-with-merge-request!1]]');
        p_get_instructions('[[gl>grosse/project-with-issue#1]]');

        // assert
        /** @var \helper_plugin_sqlite $sqliteHelper */
        $sqliteHelper = plugin_load('helper', 'sqlite');
        if (!$sqliteHelper->init('issuelinks', DOKU_PLUGIN . 'issuelinks/db/')) {
            throw new \Exception('sqlite init failed');
        }

        $sql = 'SELECT page, project_id, issue_id, type FROM pagerev_issues';
        $pagerev_issues = $sqliteHelper->res2arr($sqliteHelper->query($sql, array()));

        $this->assertEquals(array(
            array(
                'page' => 'testpage2',
                'project_id' => 'cosmocode/dokuwiki-plugin-issuelinks',
                'issue_id' => '1',
                'type' => 'link',
            ),
            array(
                'page' => 'testpage2',
                'project_id' => 'TW',
                'issue_id' => '7',
                'type' => 'link',
            ),
            array(
                'page' => 'testpage2',
                'project_id' => 'grosse/project-with-merge-request',
                'issue_id' => '1',
                'type' => 'link',
            ),
            array(
                'page' => 'testpage2',
                'project_id' => 'grosse/project-with-issue',
                'issue_id' => '1',
                'type' => 'link',
            )), $pagerev_issues);
    }
}
