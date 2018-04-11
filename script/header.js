window.magicMatcherUtil = window.magicMatcherUtil || {};

window.magicMatcherUtil.showAjaxMessages = function showAjaxMessages(response) {
    'use strict';

    var messages;
    var $msgArea;
    if (jQuery('body.tpl_sprintdoc').length) {
        $msgArea = jQuery('#dokuwiki__content').find('.msg-area');
    } else {
        $msgArea = jQuery('#dokuwiki__content').find('> div').first();
    }
    if (!Object.prototype.hasOwnProperty.call(response, 'msg')) {
        $msgArea.prepend(jQuery('<div>').addClass('error').html(response));
        return;
    }
    messages = response.msg;
    if (!messages) {
        return;
    }
    messages.forEach(function printMessagesToMessageArea(msg) {
        $msgArea.prepend(jQuery('<div>').addClass(msg.lvl).html(msg.msg));
    });
};

window.magicMatcherUtil.handleFailedAjax = function handleFailedAjax(jqXHR) {
    'use strict';

    var HIGHEST_OK_STATUS = 206;
    window.magicMatcherUtil.showAjaxMessages(jqXHR.responseJSON);
    if (jqXHR.status && jqXHR.status > HIGHEST_OK_STATUS) {
        console.error(jqXHR.status + ' ' + jqXHR.statusText);
    }
};

jQuery(function initializeTooltips() {
    'use strict';

    function getLinkData($issueLink) {
        return {
            'issuelinks-service': $issueLink.data('service'),
            'issuelinks-project': $issueLink.data('project'),
            'issuelinks-issueid': $issueLink.data('issueid'),
            'issuelinks-ismergerequest': $issueLink.data('ismergerequest'),
            call: 'plugin_issuelinks',
            'issuelinks-action': 'issueToolTip',
            sectok: jQuery('input[name=sectok]').val(),
        };
    }

    window.addTooltip('.issuelink', undefined, getLinkData, function getAdditionalIssueData($issueLink, $tooltip) {
        jQuery.get(window.DOKU_BASE + 'lib/exe/ajax.php', {
            'issuelinks-service': $issueLink.data('service'),
            'issuelinks-project': $issueLink.data('project'),
            'issuelinks-issueid': $issueLink.data('issueid'),
            'issuelinks-ismergerequest': $issueLink.data('ismergerequest'),
            call: 'plugin_issuelinks',
            'issuelinks-action': 'getAdditionalIssueData',
            sectok: jQuery('input[name=sectok]').val(),
        }).done(function updateIssueTooltip(response) {
            var data = response.data;
            window.magicMatcherUtil.showAjaxMessages(response);
            $tooltip.find('.waiting').removeClass('waiting');
            if (typeof data.avatarHTML === 'string') {
                $tooltip.find('.assigneeAvatar').html(data.avatarHTML);
            }
            if (typeof data.fancyLabelsHTML === 'string') {
                $tooltip.find('.labels').html(data.fancyLabelsHTML);
            }
        }).fail(function handleFailedState(jqXHR) {
            window.magicMatcherUtil.showAjaxMessages(jqXHR.responseJSON);
        });
    });
});

//
// window.magicMatcherUtil.annotateIssueOptions = function annotateIssueOptions(index, elem) {
//     'use strict';
//
//     var $option = jQuery(elem);
//     $option.html('<span data-status="' + $option.data('status') + '">' + $option.html() + '</span>');
// };
//
// window.magicMatcherUtil.switchIssues = function switchIssues($issueSelect, $projectSelect) {
//     'use strict';
//
//     var pmService = window.magicMatcherUtil.getPMService($projectSelect);
//     var projectKey = window.magicMatcherUtil.getProject($projectSelect);
//     var sectok = $issueSelect.closest('form').find('input[name=sectok]').val();
//     $issueSelect.prop('disabled', false);
//     jQuery.post(
//         DOKU_BASE + 'lib/exe/ajax.php',
//         {
//             call: 'plugin_magicmatcher',
//             'magicmatcher-action': 'getProjectIssues',
//             'magicmatcher-project': projectKey,
//             'magicmatcher-pmservice': pmService,
//             sectok: sectok,
//         }
//     ).done(function updateIssueSelectOptions(response) {
//         var data = response.data;
//         window.magicMatcherUtil.showAjaxMessages(response);
//         $issueSelect.html(data.issue_options);
//         $issueSelect.find('option').each(window.magicMatcherUtil.annotateIssueOptions);
//         $issueSelect.trigger('chosen:updated');
//         $issueSelect.change();
//     }).fail(window.magicMatcherUtil.handleFailedAjax);
// };
//
// window.magicMatcherUtil.getProject = function getProject($projectSelect) {
//     'use strict';
//
//     return $projectSelect.val();
// };
//
// window.magicMatcherUtil.getPMService = function getPMService($projectSelect) {
//     'use strict';
//
//     return $projectSelect.find('option:selected').closest('optgroup').data('pmservice');
// };
//
// window.magicMatcherUtil.initializeChosen = function initializeChosen($select, options) {
//     'use strict';
//
//     $select.chosen(options).addClass('a11y').show();
//     jQuery('#' + $select.attr('id') + '_chosen').attr('aria-hidden', 'true').attr('data-name', $select.attr('name'));
// };
//
// jQuery(function initializeHeader() {
//     'use strict';
//
//     var MM = window.magicMatcherUtil;
//     var $headerForm = jQuery('#magicmatcher__context');
//     var $suggestionsButton = $headerForm.find('button[name=toggleSuggestions]');
//     var $projectSelect = $headerForm.find('select[name="mmproject"]');
//     var $issueSelect = $headerForm.find('select[name="mmissues"]');
//     var TOGGLE_ANIMATION_SPEED = 600;
//     function getLinkData($issueLink) {
//         return {
//             'magicmatcher-pmservice': $issueLink.data('service'),
//             'magicmatcher-project': $issueLink.data('project'),
//             'magicmatcher-issueid': $issueLink.data('issueid'),
//             'magicmatcher-ismergerequest': $issueLink.data('ismergerequest'),
//             call: 'plugin_magicmatcher',
//             'magicmatcher-action': 'issueToolTip',
//             sectok: jQuery('input[name=sectok]').val(),
//         };
//     }
//
//     window.addTooltip('.issuelink', undefined, getLinkData, function getAdditionalIssueData($issueLink, $tooltip) {
//         jQuery.get(window.DOKU_BASE + 'lib/exe/ajax.php', {
//             'magicmatcher-pmservice': $issueLink.data('service'),
//             'magicmatcher-project': $issueLink.data('project'),
//             'magicmatcher-issueid': $issueLink.data('issueid'),
//             'magicmatcher-ismergerequest': $issueLink.data('ismergerequest'),
//             call: 'plugin_magicmatcher',
//             'magicmatcher-action': 'getAdditionalIssueData',
//             sectok: jQuery('input[name=sectok]').val(),
//         }).done(function updateIssueTooltip(response) {
//             var data = response.data;
//             window.magicMatcherUtil.showAjaxMessages(response);
//             $tooltip.find('.waiting').removeClass('waiting');
//             if (typeof data.avatarHTML === 'string') {
//                 $tooltip.find('.assigneeAvatar').html(data.avatarHTML);
//             }
//             if (typeof data.fancyLabelsHTML === 'string') {
//                 $tooltip.find('.labels').html(data.fancyLabelsHTML);
//             }
//         }).fail(function handleFailedState(jqXHR) {
//             window.magicMatcherUtil.showAjaxMessages(jqXHR.responseJSON);
//         });
//     });
//     window.magicMatcherUtil.initializeChosen($projectSelect, {});
//     $issueSelect.find('option').each(window.magicMatcherUtil.annotateIssueOptions);
//
//     window.magicMatcherUtil.initializeChosen($issueSelect, {
//         allow_single_deselect: true,
//         search_contains: true,
//     });
//     function setNewIssue(response) {
//         var data = response.data;
//         var $status = $headerForm.find('span.mm__status');
//
//         var $issuelist = jQuery('ul.mmissuelist');
//         MM.showAjaxMessages(response);
//         $headerForm.addClass('visible');
//         $status.show();
//         $status.text(data.issue.status.toUpperCase());
//         $status.removeClass();
//
//         $status.addClass('mm__status');
//         $status.addClass(data.issue.status.split(' ').join('_').toLowerCase());
//         $issuelist.find('li.newIssue').hide();
//         $issuelist.append(jQuery(data.issueListItems).filter('li.newIssue'));
//
//         jQuery('ul.mmissuelist button').prop('disabled', '');
//         jQuery('#edbtn__save_noissue').show();
//         jQuery('#edbtn__save').text(LANG.plugins.magicmatcher['btn:savewithissue'] + ' ' + data.issue.id);
//     }
//
//     function unsetIssue(response) {
//         var title;
//         var data = response.data;
//         var $savebtn = jQuery('#edbtn__save');
//         MM.showAjaxMessages(response);
//
//         $headerForm.removeClass('visible');
//         jQuery('ul.mmissuelist').html(data.issueListItems);
//         if ($savebtn.length) {
//             title = $savebtn.prop('title');
//             $savebtn.text(title.substr(0, title.indexOf('[')));
//             jQuery('#edbtn__save_noissue').hide();
//         }
//     }
//
//     function decorateSuggestionHeadings() {
//         var $sectionHeaders = $headerForm.find('h2');
//         $sectionHeaders.append('<span class="opener"></span>');
//         $sectionHeaders.click(function toggleSections() {
//             var QUARTER_TURN_RIGHT = 90;
//             var QUARTER_TURN_LEFT = -90;
//             var rotation;
//             if (MM.getRotationDegrees(jQuery(this).find('span.opener')) === 0) {
//                 rotation = QUARTER_TURN_LEFT;
//                 localStorage.setItem(jQuery(this).attr('id'), 'closed');
//                 jQuery(this).find('span.opener').addClass('closed');
//             } else {
//                 rotation = QUARTER_TURN_RIGHT;
//                 localStorage.setItem(jQuery(this).attr('id'), 'open');
//                 jQuery(this).find('span.opener').removeClass('closed');
//             }
//             jQuery(this).find('span.opener').animateRotate(rotation, { duration: TOGGLE_ANIMATION_SPEED });
//             jQuery(this).next().slideToggle(TOGGLE_ANIMATION_SPEED);
//         });
//
//         $sectionHeaders.each(function rememberSectionStates(index, elem) {
//             if (localStorage.getItem(jQuery(elem).attr('id')) === 'closed') {
//                 jQuery(elem).click();
//             }
//         });
//     }
//
//     $issueSelect.change(function handleIsseSelectChange() {
//         var pmService = MM.getPMService($projectSelect);
//         var project = MM.getProject($projectSelect);
//         var issueid = $issueSelect.val();
//         var sectok = $headerForm.find('input[name=sectok]').val();
//         var functionDone;
//
//         jQuery('ul.mmissuelist button').prop('disabled', 'disabled');
//         if (issueid) {
//             functionDone = setNewIssue;
//         } else {
//             functionDone = unsetIssue;
//         }
//
//         jQuery.post(
//             window.DOKU_BASE + 'lib/exe/ajax.php',
//             {
//                 call: 'plugin_magicmatcher',
//                 'magicmatcher-action': 'updateIssue',
//                 'magicmatcher-pmservice': pmService,
//                 'magicmatcher-project': project,
//                 'magicmatcher-issueid': issueid,
//                 'magicmatcher-page': JSINFO.id,
//                 sectok: sectok,
//             }
//         ).done(functionDone)
//             .fail(window.magicMatcherUtil.handleFailedAjax)
//             .always(function cleanUpAfterIssueChange() {
//                 $headerForm.find('#mm_suggestions').html('');
//                 $headerForm.find('#mm_suggestions').slideUp();
//                 jQuery('#mm_issue_loading').hide();
//                 MM.handleIssueListButtons();
//             });
//         $headerForm.find('span.mm__status').hide();
//         jQuery('#mm_issue_loading').show();
//     });
//     if ($projectSelect.val() && !$issueSelect.val()) {
//         MM.switchIssues($issueSelect, $projectSelect);
//     }
//
//     $projectSelect.change(function handleNewProjectSelected() {
//         $issueSelect.val('');
//         MM.switchIssues($issueSelect, $projectSelect);
//     });
//
//     $suggestionsButton.click(function suggestionButtonClicked() {
//         var sectok;
//         var issue;
//         var $suggestions = $headerForm.find('#mm_suggestions');
//         if ($suggestions.html()) {
//             $suggestions.slideToggle(TOGGLE_ANIMATION_SPEED);
//             return;
//         }
//         issue = $issueSelect.val();
//         sectok = $headerForm.find('input[name=sectok]').val();
//
//         jQuery.post(
//             DOKU_BASE + 'lib/exe/ajax.php',
//             {
//                 call: 'plugin_magicmatcher',
//                 'magicmatcher-action': 'getSuggestions',
//                 'magicmatcher-issue': issue,
//                 'magicmatcher-page': JSINFO.id,
//                 sectok: sectok,
//             }
//         ).done(function showSuggestionData(response) {
//             var data = response.data;
//             var $suggestionsWrapper = $suggestions.find('#magicmatcher__suggestions_page');
//             var pmServiceName = $suggestionsWrapper.data('service');
//             var projectKey = $suggestionsWrapper.data('project');
//             var issueId = $suggestionsWrapper.data('issueid');
//
//             MM.showAjaxMessages(response);
//             $suggestions.html(data);
//             decorateSuggestionHeadings();
//             jQuery('#mm_toggle_loading').hide();
//             $suggestions.slideToggle(TOGGLE_ANIMATION_SPEED);
//             $suggestions.find('span.issueMatchScore').each(function annotateRelatedIssueMatchScore(index, element) {
//                 var $elem = jQuery(element);
//                 var $issueLink = $elem.next();
//                 var tooltipData = {
//                     call: 'plugin_magicmatcher',
//                     'magicmatcher-action': 'relatedIssueScoreDetails',
//                     'magicmatcher-pmservice': pmServiceName,
//                     'magicmatcher-project': projectKey,
//                     'magicmatcher-issueid': issueId,
//                     relatedService: $issueLink.data('service'),
//                     relatedProject: $issueLink.data('project'),
//                     relatedIssueID: $issueLink.data('issueid'),
//                     sectok: sectok,
//                 };
//                 window.addTooltip($elem, undefined, tooltipData);
//             });
//
//             $suggestions.find('a.wikilink1').mouseenter(function highlightSameLinks() {
//                 var $this = jQuery(this);
//                 var href = $this.attr('href');
//                 var $sameLinks;
//                 $this.addClass('baseLink');
//                 $sameLinks = $suggestions.find('a[href=\'' + href + '\']:not(.baseLink)');
//                 $this.removeClass('baseLink');
//                 $sameLinks.addClass('sameLinkHighlight');
//                 $this.mouseleave(function returnLinksToNormal() {
//                     $sameLinks.removeClass('sameLinkHighlight');
//                 });
//             });
//         }).fail(window.magicMatcherUtil.handleFailedAjax);
//         jQuery('#mm_toggle_loading').show();
//     });
// });
