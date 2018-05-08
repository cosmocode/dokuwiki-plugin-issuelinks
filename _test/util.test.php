<?php

/**
 * Tests for the class helper_plugin_issuelinks_util of the issuelinks plugin
 *
 * @group plugin_issuelinks
 * @group plugins
 *
 */
class util_plugin_issuelinks_test extends DokuWikiTest {

    protected $pluginsEnabled = array('issuelinks');


    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
    }

    /**
     * Testdata for @see util_plugin_issuelinks_test::test_parseHTTPLinkHeaders
     *
     * @return array
     */
    public static function parseHTTPLinkHeaders_testdata() {
        return array(
            array(
                '<https://api.github.com/repositories/48178744/commits?page=2>; rel="next", <https://api.github.com/repositories/48178744/commits?page=23>; rel="last"',
                array(
                    'next' => 'https://api.github.com/repositories/48178744/commits?page=2',
                    'last' => 'https://api.github.com/repositories/48178744/commits?page=23'
                ),
                'github header'
            ),
            array(
                '<https://gitlab.cosmocode.de/api/v3/groups/dokuwiki/projects?id=dokuwiki&page=2&per_page=20&private_token=TGVvcy_D6MMCXxnZALTc>; rel="next", <https://gitlab.cosmocode.de/api/v3/groups/dokuwiki/projects?id=dokuwiki&page=1&per_page=20&private_token=TGVvcy_D6MMCXxnZALTc>; rel="first", <https://gitlab.cosmocode.de/api/v3/groups/dokuwiki/projects?id=dokuwiki&page=2&per_page=20&private_token=TGVvcy_D6MMCXxnZALTc>; rel="last"',
                array(
                    'next' => 'https://gitlab.cosmocode.de/api/v3/groups/dokuwiki/projects?id=dokuwiki&page=2&per_page=20&private_token=TGVvcy_D6MMCXxnZALTc',
                    'first' => 'https://gitlab.cosmocode.de/api/v3/groups/dokuwiki/projects?id=dokuwiki&page=1&per_page=20&private_token=TGVvcy_D6MMCXxnZALTc',
                    'last' => 'https://gitlab.cosmocode.de/api/v3/groups/dokuwiki/projects?id=dokuwiki&page=2&per_page=20&private_token=TGVvcy_D6MMCXxnZALTc'
                ),
                'gitlab header'
            )
        );
    }

    /**
     * @dataProvider parseHTTPLinkHeaders_testdata
     *
     * @param string $linkHeader
     * @param array $expected_links
     * @param string $msg
     */
    public function test_parseHTTPLinkHeaders($linkHeader, $expected_links, $msg) {
        /** @var helper_plugin_issuelinks_util $helper*/
        $helper = plugin_load('helper', 'issuelinks_util');

        $actual_links = $helper->parseHTTPLinkHeaders($linkHeader);

        $this->assertSame($expected_links, $actual_links, $msg);
    }
}
