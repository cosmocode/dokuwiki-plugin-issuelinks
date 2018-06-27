<?php
/**
 * English language file for issuelinks plugin
 *
 * @author Andreas Gohr <dokuwiki@cosmocode.de>
 */

$lang['gitlab'] = 'GitLab';
$lang['github'] = 'GitHub';

$lang['btn:addIssueContextToPage'] = 'Add issue %s';
$lang['btn:Save without Issue'] = 'Save without Issue';
$lang['btn:Save with Issue'] = 'Save with Issue';
$lang['btn:Import'] = 'Import';
$lang['btn:Abort'] = 'Abort';

$lang['label:issuenumber'] = 'Number of the issue to import, e.g. "27". Import all, if empty';
$lang['label:project for import'] = 'Project from which to import the issues';
$lang['label:issue import offest'] = '(optional) Offset number of issues from the end';
$lang['label:repository for import'] = 'Repository from which to import the commits';
$lang['label:commit hash'] = 'Hash of the commit to import (0-9, a-f, at least eight digit)';
$lang['label github:choose organisation'] = 'Choose which organisation\'s repository-webhooks to manage';
$lang['label gitlab:choose organisation'] = 'Choose which group\'s project-webhooks to manage';
$lang['label:project dropdown'] = 'Select the project';
$lang['label:issue dropdown'] = 'Select the issue';
$lang['label:is merge request'] = 'Is Merge Request';
$lang['label: reconfigure service'] = 'Click here to reconfigure this service';
$lang['label: authorized with user'] = 'Authorized with user %s.';

$lang['placeholder:project'] = 'e.g.: SPR';
$lang['placeholder:repository'] = 'groupname/projectname';
$lang['placeholder:git commit hash'] = 'edbd3278';

$lang['legend:import issues'] = 'Import issues of a project';
$lang['legend:import commits'] = 'Import commits of a repository';
$lang['legend:user'] = 'User';
$lang['legend:group github'] = 'Organisation';
$lang['legend:group gitlab'] = 'Group';

$lang['tab:issueimport'] = 'Issue-Import';
$lang['tab:commitimport'] = 'Commit-Import';

$lang['headline:import'] = 'Import issues and commits';

$lang['info:import abort notification'] = 'Last import has been aborted by %s.';
$lang['info:commit import in progress'] = 'Commit import by %s in progress!';
$lang['info:commit import in progress no user'] = 'Commit import in progress!';
$lang['info:issue import in progress'] = 'Issue import by %s in progress!';
$lang['info:Issue import in progress no user'] = 'Issue import in progress!';

$lang['success:issue imported'] = 'Issue imported:';

$lang['error:issue import'] = 'There was an error importing Issue ';
$lang['error:system too many requests'] = 'This system has made too many requests to GitHub. Please try again/continue after %s. Please note that this time may be either UTC or server-time or your local time depending on your setup.';

$lang['menu:repo-admin'] = 'Issuelinks: setup project management services';

$lang['message:needs configuration'] = 'Please configure %s!';
$lang['message:github needs authorization'] = 'Please authorize SprintDoc on GitHub!';
$lang['message:gitlab needs authorization'] = 'The GitLab user-token has become invalid. Please enter a valid token.';

$lang['text:repo admin'] = 'Below are the repositories of the organisation/group to which the authorized user has access to. Click on the icon to create/delete the webhook.';
$lang['text github:no orgs'] = 'No organisation has allowed this application access.';
$lang['text gitlab:no orgs'] = 'This user has no access to any organisations.';
$lang['text:no issues match search'] = 'No issues match that search';
$lang['text gitlab: generate api key'] = 'Please go to %s and generate a new token for this plugin with the <b>api</b> scope.';

$lang['title:issue hook'] = 'Toggle the hook for issue-events';
$lang['title:forbidden'] = 'The associated account has insufficient rights for this action';

$lang['suggestions'] = 'Page Suggestions';
$lang['end_session'] = 'End Edit Session for this Ticket';
$lang['jira_browse'] = 'View the issue in Jira';
$lang['jira_issue'] = 'Jira Issue';
$lang['last changed'] = 'last edited %s by %s';
$lang['changed'] = 'edited %s by %s';
$lang['youarehere'] = 'Shown for namespace: ';

// table headings
$lang['page'] = 'Page';
$lang['time'] = 'Time';
$lang['summary'] = 'Summary';
$lang['issue'] = 'Issue';

$lang['error_issue'] = 'Given issue id wasn\'t found';

$lang['error: upstream issue not found'] = 'The given issue or repository does not exist.';
$lang['error: upstream forbidden'] = 'The currently associated user does not have the rights to access this issue or repository';

$lang['Exception: request error'] = 'The Request failed with status %s %s';

$lang['no changed pages'] = 'No pages within this namespace have been changed in the context of this issue.';
$lang['no suggestions'] = 'We currently have no suggestions for this issue.';
$lang['no related issues'] = 'No related issues have been found.';
$lang['no linking issues'] = 'No pages with links to this issue have been found.';
$lang['no git commits'] = 'There are no git commits associated with this issue in jira. Hence there can be no suggestions.';
$lang['no issue description'] = 'This issue has no description. 😞';

// headlines
$lang['suggestions title'] = 'Suggestions for issue %s';
$lang['changedPages'] = 'Pages changed for this issue';
$lang['source suggestions'] = 'Suggestions based on source files';
$lang['related Issues'] = 'Related issues';
$lang['linking Issues'] = 'Pages containing links to this issue';
$lang['headline:issue description'] = 'Issue Description';
$lang['headline:issue files'] = 'Files changed for this issue';

$lang['noIssueContextForPage'] = 'No issues context for this page';

$lang['related pages'] = 'related pages';
$lang['linking pages'] = 'linking pages';

$lang['upstream issue: pages edited'] = 'The following [documentation](%s) pages have been edited in context with this issue:' . "\n";
$lang['upstream issue: no pages edited'] = 'Currently no [documentation](%s) changes have been marked as related to this issue.' . "\n";
$lang['upstream issue: auto update'] = "This comment has been created by a bot and will be automatically updated. See [www.sprintdoc.de](https://www.sprintdoc.de) for more information.";

$lang['js']['btn:savewithissue'] = $lang['btn:Save with Issue'];
$lang['js']['status:started'] = 'Started...';
$lang['js']['status:running'] = 'Running...';
$lang['js']['status:done'] = 'Done!';

$lang['jira:webhook settings link'] = 'All webhooks currently active in your Jira can be found at Jira\'s advanced system settings page in the webhooks subsection.';

//Setup VIM: ex: et ts=4 :
