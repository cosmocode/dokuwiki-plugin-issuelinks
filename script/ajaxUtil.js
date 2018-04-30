window.magicMatcherUtil = window.magicMatcherUtil || {};

window.magicMatcherUtil.showAjaxMessages = function showAjaxMessages(response) {
    'use strict';

    let $msgArea;
    if (jQuery('body.tpl_sprintdoc').length) {
        $msgArea = jQuery('#dokuwiki__content').find('.msg-area');
    } else {
        $msgArea = jQuery('#dokuwiki__content').find('> div').first();
    }
    if (!Object.prototype.hasOwnProperty.call(response, 'msg')) {
        $msgArea.prepend(jQuery('<div>').addClass('error').html(response));
        return;
    }
    const messages = response.msg;
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

