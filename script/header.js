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
