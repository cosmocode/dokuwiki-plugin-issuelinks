window.issuelinksUtil = window.issuelinksUtil || {};
jQuery(function initializeRepoAdminInterface() {
    'use strict';

    const $repoadmin = jQuery('#plugin__issuelinks_repoadmin');
    const $tabs = $repoadmin.find('.tabs a');

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
        let data;
        const HTTP_STATUS_FORBIDDEN = 403;
        console.error(jqXHR);
        try {
            const response = JSON.parse(jqXHR.responseText);
            window.issuelinksUtil.showAjaxMessages(response);
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
        const $this = jQuery(this);
        const servicename = $this.parents('.repo_area').data('service');
        if ($this.hasClass('pulse')) {
            return;
        }

        const settings = {
            url: DOKU_BASE + 'lib/exe/ajax.php',
            data: {
                call: 'issuelinks_repo_admin_toggle',
                sectok: jQuery('input[name="sectok"]').val(),
                project: $this.data('project'),
                hookid: $this.data('id'),
                hooktype: 'issue',
                servicename: servicename,
            },
        };
        jQuery.post(settings)
            .done(function adjustHookDisplay(response) {
                const data = response.data;
                window.issuelinksUtil.showAjaxMessages(response);
                toggleHookIndicator(data, $this);
            })
            .fail(function showErrorOnHook(jqXHR) {
                showError(jqXHR, $this);
            })
            .always(function disablePulse() {
                $this.removeClass('pulse');
            })
        ;
        $this.addClass('pulse');
    }

    $tabs.first().closest('li').addClass('active');
    $repoadmin.find('.service_wrapper').not('[data-service="' + $tabs.data('service') + '"]').hide();
    $tabs.click(function switchTab() {
        const $this = jQuery(this);
        const servicename = $this.data('service');
        $tabs.closest('li').removeClass('active');
        $this.closest('li').addClass('active');
        $repoadmin.find('.service_wrapper[data-service="' + servicename + '"]').show();
        $repoadmin.find('.service_wrapper').not('[data-service="' + servicename + '"]').hide();
    });

    jQuery('select[name="mm_organisation"]').change(function organisationChanged() {
        const $this = jQuery(this);
        const servicename = $this.closest('form').data('service');
        const $reposDiv = jQuery('.repo_area[data-service="' + servicename + '"]');
        if (!$this.val()) {
            $reposDiv.html('');
            return;
        }
        const settings = {
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
                const data = response.data;
                window.issuelinksUtil.showAjaxMessages(response);
                $reposDiv.html(data);
                $reposDiv.find('span.repohookstatus:not(.forbidden)').click(requestHookToogle);
            })
            .fail(function showErrorOnRepoArea(jqXHR) {
                showError(jqXHR, $reposDiv);
            })
            .always(function enableThisSelectAgain() {
                $this.prop('disabled', false);
            })
        ;
        $reposDiv.html(jQuery('<span>').addClass('pulse').css('padding', '5px'));
        $this.prop('disabled', 'disabled');
    });
    const CHECK_IMPORT_STATUS_TIMEOUT = 1000;

    function checkImportStatus(servicename, project, $importStatusElement) {
        const checkImportSettings = {
            url: DOKU_BASE + 'lib/exe/ajax.php',
            data: {
                call: 'plugin_issuelinks',
                'issuelinks-action': 'checkImportStatus',
                'issuelinks-service': servicename,
                'issuelinks-project': project,
            },
        };
        jQuery.post(checkImportSettings)
            .done(function (response) {
                const data = response.data;
                window.issuelinksUtil.showAjaxMessages(response);

                let total = '?';
                let percent = '?';
                const count = jQuery.isNumeric(data.count) ? data.count : 0;
                if (jQuery.isNumeric(data.total) && data.total > 0) {
                    total = data.total;
                    percent = Math.round(count / total * 100);
                }
                const statusText = LANG.plugins.issuelinks['status:' + data.status];
                const progressText = '' + count + '/' + total + ' (' + percent + ' %) ' + statusText;
                $importStatusElement
                    .text(progressText)
                    .css('background-color', '#ff9')
                    .animate({ backgroundColor: 'transparent' }, CHECK_IMPORT_STATUS_TIMEOUT / 2)
                ;
                if (data.status && data.status === 'done') {
                    return;
                }
                window.setTimeout(
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
        const $this = jQuery(this);
        const servicename = $this.closest('[data-service]').data('service');
        const project = $this.data('project');

        const settings = {
            url: DOKU_BASE + 'lib/exe/ajax.php',
            data: {
                call: 'issuelinks_import_all_issues_async',
                project: project,
                servicename: servicename,
            },
        };

        jQuery.post(settings)
            .done(function (response) {
                window.issuelinksUtil.showAjaxMessages(response);
                const $importStatusElement = jQuery('<span class="js-importRunning importRunning">Import started</span>');
                $this.replaceWith($importStatusElement);
                window.setTimeout(
                    checkImportStatus,
                    CHECK_IMPORT_STATUS_TIMEOUT,
                    servicename,
                    project,
                    $importStatusElement
                );
            });
    });
});
