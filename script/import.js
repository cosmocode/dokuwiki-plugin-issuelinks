window.magicMatcherUtil = window.magicMatcherUtil || {};

window.magicMatcherUtil.abortImport = function abortImport($button, action) {
    'use strict';

    var $form = $button.closest('form');
    var sectok = $form.find('input[name=sectok]').val();
    var $msgArea;
    if (jQuery('body.tpl_sprintdoc').length) {
        $msgArea = jQuery('#dokuwiki__content').find('.msg-area');
    } else {
        $msgArea = jQuery('#dokuwiki__content').find('> div').first();
    }
    jQuery.post(DOKU_BASE + 'lib/exe/ajax.php',
        {
            call: 'plugin_magicmatcher',
            'magicmatcher-action': action,
            sectok: sectok,
        }).done(function hideAbortForm(response) {
            window.magicMatcherUtil.showAjaxMessages(response);
            $form.hide();
            $msgArea.prepend(jQuery('<div class="info">The import has been successfully aborted.</div>'));
        }).fail(function reportFailure(jqXHR, textStatus, errorThrown) {
            window.magicMatcherUtil.showAjaxMessages(jqXHR.responseJSON);
            $msgArea.prepend(jQuery('<div class="error">There was an error aborting the import: ' + errorThrown + ' ' + textStatus + '</div>'));
        });
};

jQuery(document).on('click', '#issueImportAbort, #commitImportAbort', function triggerAbortingImport(event) {
    'use strict';

    var action = jQuery(this).attr('id') === '#issueImportAbort' ? 'abortIssueImport' : 'abortCommitImport';
    event.preventDefault();
    event.stopPropagation();
    window.magicMatcherUtil.abortImport(jQuery(this), action);
});

jQuery(function initializeImportView() {
    'use strict';

    var $adminImport = jQuery('#magicmatcher_adminimport');
    var $issueImportDiv = jQuery('#plugin__magicmatcher_issueimport');
    var toggleMRCheckbox = function toggleMRCheckbox() {
        var $pmServiceSelect = $issueImportDiv.find('select[name="pmService"]');
        var $issueIdInput = $issueImportDiv.find('input[name="issueid"]');
        var $isMRCheckbox = $issueImportDiv.find('input[name="ismr"]');
        var $importSingleGitLabIssue = $pmServiceSelect.find(':selected').val() === 'gitlab' && $issueIdInput.val();

        if ($importSingleGitLabIssue) {
            $isMRCheckbox.closest('label').show();
        } else {
            $isMRCheckbox.closest('label').hide();
            $isMRCheckbox.prop('checked', false);
        }
    };

    if ($adminImport.find('form.importAbort').length) {
        $adminImport.find('form.importAbort').remove();
        $adminImport.prepend(jQuery('<p>Import Done! <a href="' + DOKU_BASE + 'doku.php?do=admin&page=magicmatcher_import">Back to MagicMatcher import interface</a>'));
        return;
    }

    jQuery('a[data-tab="#plugin__magicmatcher_commitimport"]').click(function switchToCommitImportTab() {
        jQuery(this).closest('ul').find('li').removeClass('active');
        jQuery(this).closest('li').addClass('active');
        jQuery('#plugin__magicmatcher_commitimport').show();
        $issueImportDiv.hide();
    });

    jQuery('a[data-tab="#plugin__magicmatcher_issueimport"]').click(function switchToIssueImportTab() {
        jQuery(this).closest('ul').find('li').removeClass('active');
        jQuery(this).closest('li').addClass('active');
        jQuery('#plugin__magicmatcher_commitimport').hide();
        $issueImportDiv.show();
    });

    $issueImportDiv.find('select[name="pmService"]').change(toggleMRCheckbox);
    $issueImportDiv.find('input[name="issueid"]').on('change input', toggleMRCheckbox);
    toggleMRCheckbox();
});
