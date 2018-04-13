window.magicMatcherUtil = window.magicMatcherUtil || {};
jQuery(function initializeRepoAdminInterface() {
    'use strict';

    var $repoadmin = jQuery('#plugin__issuelinks_repoadmin');
    var $tabs = $repoadmin.find('.tabs a');

    function toggleHookIndicator(data, $this) {
        $this.toggleClass('active inactive');
        if ('id' in $this.data()) {
            $this.removeData('id');
            $this.removeAttr('data-id');
            return;
        }

        if (typeof data.id !== 'undefined') {
            $this.data('id', data.id);
        }
    }

    function showError(jqXHR, $this) {
        var response;
        var data;
        var HTTP_STATUS_FORBIDDEN = 403;
        console.error(jqXHR);
        try {
            response = JSON.parse(jqXHR.responseText);
            window.magicMatcherUtil.showAjaxMessages(response);
            if (typeof response.data !== 'undefined') {
                data = response.data;
            } else {
                data = jqXHR.responseText;
            }
        } catch (e) {
            data = jqXHR.responseText;
        }
        switch (jqXHR.status) {
        case HTTP_STATUS_FORBIDDEN:
            $this.text(jqXHR.status + ' ' + jqXHR.statusText + '!');
            break;
        default:
            $this.text(data);
        }
        $this.removeClass('active inactive').addClass('error');
        $this.off('click');
    }

    function requestHookToogle() {
        var settings;
        var $this = jQuery(this);
        var servicename = $this.parents('.repo_area').data('service');
        if ($this.hasClass('pulse')) {
            return;
        }

        settings = {
            url: DOKU_BASE + 'lib/exe/ajax.php',
            data: {
                call: 'issuelinks_repo_admin_toggle',
                sectok: jQuery('input[name="sectok"]').val(),
                org: $this.data('org'),
                repo: $this.data('repo'),
                hookid: $this.data('id'),
                hooktype: 'issue',
                servicename: servicename,
            },
        };
        jQuery.post(settings)
            .done(function adjustHookDisplay(response) {
                var data = response.data;
                window.magicMatcherUtil.showAjaxMessages(response);
                toggleHookIndicator(data, $this);
            })
            .fail(function showErrorOnHook(jqXHR) { showError(jqXHR, $this); })
            .always(function disablePulse() { $this.removeClass('pulse'); })
        ;
        $this.addClass('pulse');
    }

    $tabs.first().closest('li').addClass('active');
    $repoadmin.find('.service_wrapper').not('[data-service="' + $tabs.data('service') + '"]').hide();
    $tabs.click(function switchTab() {
        var $this = jQuery(this);
        var servicename = $this.data('service');
        $tabs.closest('li').removeClass('active');
        $this.closest('li').addClass('active');
        $repoadmin.find('.service_wrapper[data-service="' + servicename + '"]').show();
        $repoadmin.find('.service_wrapper').not('[data-service="' + servicename + '"]').hide();
    });

    jQuery('select[name="mm_organisation"]').change(function organisationChanged() {
        var settings;
        var $this = jQuery(this);
        var servicename = $this.closest('form').data('service');
        var $reposDiv = jQuery('.repo_area[data-service="' + servicename + '"]');
        if (!$this.val()) {
            $reposDiv.html('');
            return;
        }
        settings = {
            url: DOKU_BASE + 'lib/exe/ajax.php',
            data: {
                call: 'issuelinks_repo_admin_getorg',
                sectok: $this.parents('form').find('input[name="sectok"]').val(),
                org: $this.val(),
                servicename: servicename,
            },
        };
        jQuery.post(settings)
            .done(function updateReposForOrganisation(response) {
                var data = response.data;
                window.magicMatcherUtil.showAjaxMessages(response);
                $reposDiv.html(data);
                $reposDiv.find('span.repohookstatus:not(.forbidden)').click(requestHookToogle);
            })
            .fail(function showErrorOnRepoArea(jqXHR) { showError(jqXHR, $reposDiv); })
            .always(function enableThisSelectAgain() {
                $this.prop('disabled', false);
            })
        ;
        $reposDiv.html(jQuery('<span>').addClass('pulse').css('padding', '5px'));
        $this.prop('disabled', 'disabled');
    });
    var CHECK_IMPORT_STATUS_TIMEOUT = 1000;
    var checkImportStatusTimeoutID;
    function checkImportStatus(servicename, project, $importStatusElement) {
        var checkImportSettings = {
            url: DOKU_BASE + 'lib/exe/ajax.php',
            data: {
                call: 'plugin_issuelinks',
                'issuelinks-action': 'checkImportStatus',
                'issuelinks-service': servicename,
                'issuelinks-project': project,
            }
        };
        jQuery.post(checkImportSettings)
            .done(function (response) {
                var data = response.data;
                window.magicMatcherUtil.showAjaxMessages(response);

                var total = '?';
                var percent = '?';
                var count = jQuery.isNumeric(data.count) ? data.count : 0;
                if (jQuery.isNumeric(data.total) && data.total > 0) {
                    total = data.total;
                    percent = count/total*100;
                }
                var statusText = LANG.plugins.issuelinks['status:' + data.status];
                var progressText = '' + count + '/' + total + ' (' + percent + ' %) ' + statusText;
                $importStatusElement
                    .text(progressText)
                    .css('background-color','#ff9')
                    .animate({backgroundColor: 'transparent'}, CHECK_IMPORT_STATUS_TIMEOUT/2)
                ;
                if (data.status && data.status === 'done') {
                    return;
                }
                checkImportStatusTimeoutID = window.setTimeout(
                    checkImportStatus,
                    CHECK_IMPORT_STATUS_TIMEOUT,
                    servicename,
                    project,
                    $importStatusElement
                );
            })
            .fail(function (jqXHR) {
                $importStatusElement.text('Check failed!');
                showError(jqXHR, $importStatusElement.parents('.repo_area'));
            });
    }

    $repoadmin.on('click', '.js-importIssues', function (event) {
        console.log('I ran!');
        var $this = jQuery(this);
        var servicename = $this.closest('[data-service]').data('service');
        var project = $this.data('project');

        var settings = {
            url: DOKU_BASE + 'lib/exe/ajax.php',
            data: {
                call: 'issuelinks_import_all_issues_async',
                project: project,
                servicename: servicename,
            },
        };

        jQuery.post(settings)
            .done(function (response) {
                window.magicMatcherUtil.showAjaxMessages(response);
                var $importStatusElement = jQuery('<span class="js-importRunning importRunning">Import started</span>');
                $this.replaceWith($importStatusElement);
                checkImportStatusTimeoutID = window.setTimeout(
                    checkImportStatus,
                    CHECK_IMPORT_STATUS_TIMEOUT,
                    servicename,
                    project,
                    $importStatusElement
                );
            });
    });
});
