jQuery(document).ready(function() {
    var previewTimer;
    var versionTimer;

    jQuery('#is_private').change(function() {
        if(this.checked) {
            jQuery('#access_token_row').show();
        } else {
            jQuery('#access_token_row').hide();
        }
        updatePreviewAndVersions();
    });

    jQuery('#repo_url, #access_token').on('input', function() {
        clearTimeout(previewTimer);
        clearTimeout(versionTimer);
        previewTimer = setTimeout(updatePreviewAndVersions, 500);
    });

    // Show "Save as Project" button when version is selected
    jQuery('#version').on('change', function() {
        if(jQuery(this).val()) {
            jQuery('#save_project_btn').show();
        }
    });

    function updatePreviewAndVersions() {
        previewRepo();
        getVersions();
    }

    function previewRepo() {
        var repoUrl = jQuery('#repo_url').val();
        var isPrivate = jQuery('#is_private').is(':checked');
        var accessToken = jQuery('#access_token').val();

        if (repoUrl) {
            jQuery.ajax({
                url: github_installer.ajax_url,
                type: 'POST',
                data: {
                    action: 'preview_github_repo',
                    nonce: github_installer.nonce,
                    repo_url: repoUrl,
                    is_private: isPrivate,
                    access_token: accessToken
                },
                success: function(response) {
                    if (response.success) {
                        jQuery('#repo_content').html(response.data);
                        jQuery('#repo_preview').show();
                    } else {
                        jQuery('#repo_content').html('<p style="color: red;">' + response.data + '</p>');
                        jQuery('#repo_preview').show();
                    }
                },
                error: function() {
                    jQuery('#repo_content').html('<p style="color: red;">An error occurred while fetching the repository content.</p>');
                    jQuery('#repo_preview').show();
                }
            });
        } else {
            jQuery('#repo_preview').hide();
        }
    }

    function getVersions() {
        var repoUrl = jQuery('#repo_url').val();
        var isPrivate = jQuery('#is_private').is(':checked');
        var accessToken = jQuery('#access_token').val();

        if (repoUrl) {
            jQuery.ajax({
                url: github_installer.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_github_versions',
                    nonce: github_installer.nonce,
                    repo_url: repoUrl,
                    is_private: isPrivate,
                    access_token: accessToken
                },
                success: function(response) {
                    if (response.success) {
                        var versions = response.data;
                        var versionSelect = jQuery('#version');
                        versionSelect.empty();
                        jQuery.each(versions, function(index, version) {
                            versionSelect.append(jQuery('<option></option>').attr('value', version).text(version));
                        });
                        jQuery('#version_row').show();
                    } else {
                        jQuery('#version_row').hide();
                        console.error('Failed to fetch versions:', response.data);
                    }
                },
                error: function() {
                    jQuery('#version_row').hide();
                    console.error('An error occurred while fetching the repository versions.');
                }
            });
        } else {
            jQuery('#version_row').hide();
        }
    }

    // Save project button handler
    jQuery('#save_project_btn').on('click', function(e) {
        e.preventDefault();

        var projectName = jQuery('#project_name').val();
        var repoUrl = jQuery('#repo_url').val();
        var isPrivate = jQuery('#is_private').is(':checked');
        var accessToken = jQuery('#access_token').val();
        var version = jQuery('#version').val();

        if (!projectName) {
            alert('Bitte geben Sie einen Projektnamen ein.');
            return;
        }

        if (!repoUrl || !version) {
            alert('Bitte füllen Sie alle erforderlichen Felder aus.');
            return;
        }

        jQuery.ajax({
            url: github_installer.ajax_url,
            type: 'POST',
            data: {
                action: 'save_github_project',
                nonce: github_installer.nonce,
                name: projectName,
                repo_url: repoUrl,
                is_private: isPrivate,
                access_token: accessToken,
                version: version
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Fehler: ' + response.data);
                }
            },
            error: function() {
                alert('Ein Fehler ist beim Speichern des Projekts aufgetreten.');
            }
        });
    });

    // Sync project button handler
    jQuery(document).on('click', '.github-sync-btn', function() {
        var projectId = jQuery(this).data('project-id');
        var button = jQuery(this);
        var statusDiv = jQuery('.github-sync-status[data-project-id="' + projectId + '"]');

        button.prop('disabled', true);
        statusDiv.removeClass('success error').addClass('loading').text('Synchronisierung läuft...').show();

        jQuery.ajax({
            url: github_installer.ajax_url,
            type: 'POST',
            data: {
                action: 'sync_github_project',
                nonce: github_installer.nonce,
                project_id: projectId
            },
            success: function(response) {
                button.prop('disabled', false);
                if (response.success) {
                    statusDiv.removeClass('loading error').addClass('success').text(response.data);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    statusDiv.removeClass('loading success').addClass('error').text('Fehler: ' + response.data);
                }
            },
            error: function() {
                button.prop('disabled', false);
                statusDiv.removeClass('loading success').addClass('error').text('Ein Fehler ist aufgetreten.');
            }
        });
    });

    // Delete project button handler
    jQuery(document).on('click', '.github-delete-btn', function() {
        var projectId = jQuery(this).data('project-id');

        if (!confirm('Möchten Sie dieses Projekt wirklich löschen?')) {
            return;
        }

        jQuery.ajax({
            url: github_installer.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_github_project',
                nonce: github_installer.nonce,
                project_id: projectId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert('Fehler: ' + response.data);
                }
            },
            error: function() {
                alert('Ein Fehler ist beim Löschen des Projekts aufgetreten.');
            }
        });
    });
});