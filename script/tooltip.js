/**
 * Tooltips populated by ajax request
 *
 * @author Michael Große
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 */

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

    function addTooltip(selectorOr$element, url, dataOrDataFunction, complete) {
        'use strict';

        const serverEndpoint = url || window.DOKU_BASE + 'lib/exe/ajax.php';
        const DELAY = 300;
        const HOVER_DETECTION_DELAY = 100;
        const TOOLTIP_PARENT_CLASS = 'hasTooltip';

        function hoverStart() {
            const $element = jQuery(this);
            $element.addClass('hover');
            if ($element.hasClass(TOOLTIP_PARENT_CLASS)) {
                if ($element.data('MMtooltipID')) {
                    const $tooltipDiv = jQuery('#' + $element.data('MMtooltipID'));
                    $tooltipDiv.show().position({
                        my: 'left top',
                        at: 'left bottom',
                        of: $element,
                    }).attr('aria-hidden', 'false');
                }
                return;
            }
            const payload = typeof dataOrDataFunction === 'function' ? dataOrDataFunction($element) : dataOrDataFunction;
            const timeOutReference = setTimeout(function getToolTip() {
                const $div = jQuery('<div class="serverToolTip">')
                    .uniqueId()
                    .mouseleave(function hideTooltip() {
                        $div.removeClass('hover');
                        $div.hide().attr('aria-hidden', 'true');
                    })
                    .mouseenter(function allowJSHoverDetection() {
                        $div.addClass('hover');
                    });
                $element.addClass(TOOLTIP_PARENT_CLASS);
                jQuery.get(serverEndpoint, payload).done(function injectTooltip(response) {
                    window.issuelinksUtil.showAjaxMessages(response);
                    $div.html(response.data);
                    $div.appendTo(jQuery('body'));
                    $div.show().position({
                        my: 'left top',
                        at: 'left bottom',
                        of: $element,
                    });
                    if (!$element.hasClass('hover')) {
                        $div.hide();
                    }
                    $element.data('MMtooltipID', $div.attr('id'));
                    if (typeof complete === 'function') {
                        complete($element, $div);
                    }
                }).fail(function handleFailedState(jqXHR) {
                    window.issuelinksUtil.showAjaxMessages(jqXHR.responseJSON);
                    $div.remove();
                });
            }, DELAY);
            $element.data('timeOutReference', timeOutReference);
        }

        function hoverEnd() {
            const $this = jQuery(this);
            $this.removeClass('hover');
            clearTimeout($this.data('timeOutReference'));
            if ($this.data('MMtooltipID')) {
                setTimeout(function conditionalHideTooltip() {
                    const $tooltip = jQuery('#' + $this.data('MMtooltipID'));
                    if (!$tooltip.hasClass('hover')) {
                        $tooltip.hide().attr('aria-hidden', 'true');
                    }
                }, HOVER_DETECTION_DELAY);
            }
        }

        if (typeof selectorOr$element === 'string') {
            jQuery(document).on('mouseenter', selectorOr$element, hoverStart);
            jQuery(document).on('mouseleave', selectorOr$element, hoverEnd);
        } else {
            selectorOr$element.hover(hoverStart, hoverEnd);
        }
    }

    addTooltip('.issuelink', undefined, getLinkData, function getAdditionalIssueData($issueLink, $tooltip) {
        jQuery.get(window.DOKU_BASE + 'lib/exe/ajax.php', {
            'issuelinks-service': $issueLink.data('service'),
            'issuelinks-project': $issueLink.data('project'),
            'issuelinks-issueid': $issueLink.data('issueid'),
            'issuelinks-ismergerequest': $issueLink.data('ismergerequest'),
            call: 'plugin_issuelinks',
            'issuelinks-action': 'getAdditionalIssueData',
            sectok: jQuery('input[name=sectok]').val(),
        }).done(function updateIssueTooltip(response) {
            const data = response.data;
            window.issuelinksUtil.showAjaxMessages(response);
            $tooltip.find('.waiting').removeClass('waiting');
            if (typeof data.avatarHTML === 'string') {
                $tooltip.find('.assigneeAvatar').html(data.avatarHTML);
            }
            if (typeof data.fancyLabelsHTML === 'string') {
                $tooltip.find('.labels').html(data.fancyLabelsHTML);
            }
        }).fail(function handleFailedState(jqXHR) {
            window.issuelinksUtil.showAjaxMessages(jqXHR.responseJSON);
        });
    });
});
