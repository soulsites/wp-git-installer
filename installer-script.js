jQuery(document).ready(function() {
    var versionTimer;

    // Show/hide access token field based on private checkbox
    jQuery('#is_private').change(function() {
        if(this.checked) {
            jQuery('#access_token_section').slideDown();
        } else {
            jQuery('#access_token_section').slideUp();
            jQuery('#access_token').val('');
        }
        // Reload versions when private status changes
        loadVersions();
    });

    // Show/hide project name field based on save as project checkbox
    jQuery('#save_as_project').change(function() {
        if(this.checked) {
            jQuery('#project_name_section').slideDown();
            // Pre-fill project name from repo URL if empty
            if(!jQuery('#project_name').val()) {
                var repoUrl = jQuery('#repo_url').val();
                if(repoUrl) {
                    var repoName = repoUrl.split('/').pop().replace('.git', '');
                    jQuery('#project_name').val(repoName.charAt(0).toUpperCase() + repoName.slice(1));
                }
            }
        } else {
            jQuery('#project_name_section').slideUp();
        }
    });

    // Load versions when repo URL or access token changes
    jQuery('#repo_url, #access_token').on('input', function() {
        clearTimeout(versionTimer);
        versionTimer = setTimeout(loadVersions, 800);
    });

    // Load versions function
    function loadVersions() {
        var repoUrl = jQuery('#repo_url').val();
        var isPrivate = jQuery('#is_private').is(':checked');
        var accessToken = jQuery('#access_token').val();

        // Clear previous state
        jQuery('#version_section').hide();
        jQuery('#version').html('<option value="">-- Bitte warten, lade Versionen... --</option>');

        if (!repoUrl || repoUrl.length < 10) {
            return;
        }

        // Show loading
        jQuery('#version_section').slideDown();
        jQuery('#version-loading').show();

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
                jQuery('#version-loading').hide();

                if (response.success && response.data.length > 0) {
                    var versions = response.data;
                    var versionSelect = jQuery('#version');
                    versionSelect.empty();
                    versionSelect.append(jQuery('<option></option>').attr('value', '').text('-- Wählen Sie eine Version --'));
                    jQuery.each(versions, function(index, version) {
                        versionSelect.append(jQuery('<option></option>').attr('value', version).text(version));
                    });
                    jQuery('#version_section').slideDown();
                } else {
                    jQuery('#version').html('<option value="">Keine Versionen/Tags gefunden</option>');
                    console.error('Keine Versionen gefunden:', response.data);
                }
            },
            error: function(xhr, status, error) {
                jQuery('#version-loading').hide();
                jQuery('#version').html('<option value="">Fehler beim Laden der Versionen</option>');
                console.error('Fehler beim Laden der Versionen:', error);
            }
        });
    }

    // Form validation before submit
    jQuery('#github-project-form').on('submit', function(e) {
        var saveAsProject = jQuery('#save_as_project').is(':checked');
        var projectName = jQuery('#project_name').val();
        var repoUrl = jQuery('#repo_url').val();
        var version = jQuery('#version').val();

        // Check if save as project is checked but no project name
        if (saveAsProject && !projectName) {
            e.preventDefault();
            alert('Bitte geben Sie einen Projektnamen ein oder deaktivieren Sie "Als Projekt speichern".');
            jQuery('#project_name').focus();
            return false;
        }

        // Check if repo URL is provided
        if (!repoUrl) {
            e.preventDefault();
            alert('Bitte geben Sie eine GitHub Repository URL ein.');
            jQuery('#repo_url').focus();
            return false;
        }

        // Warning if no version selected
        if (!version) {
            return confirm('Sie haben keine Version ausgewählt. Es wird die neueste Version vom Hauptbranch verwendet. Fortfahren?');
        }

        return true;
    });

    // Sync project button handler
    jQuery(document).on('click', '.github-sync-btn', function() {
        var projectId = jQuery(this).data('project-id');
        var button = jQuery(this);
        var statusRow = jQuery('.github-sync-status-row[data-project-id="' + projectId + '"]');
        var statusDiv = jQuery('.github-sync-status[data-project-id="' + projectId + '"]');

        button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Lädt...');
        statusRow.show();
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
                button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Update');
                if (response.success) {
                    statusDiv.removeClass('loading error').addClass('success').html('✓ ' + response.data);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    var errorData = response.data;
                    var message, debug;
                    if (errorData && typeof errorData === 'object') {
                        message = errorData.message || 'Unbekannter Fehler';
                        debug = errorData.debug || null;
                    } else {
                        message = errorData || 'Unbekannter Fehler';
                        debug = null;
                    }
                    console.error('[WP-Git-Installer] Sync-Fehler:', message, debug ? '\nDebug:\n' + debug : '');
                    var html = '✗ Fehler: ' + message;
                    if (debug) {
                        html += '<details style="margin-top:6px;font-size:0.85em;white-space:pre-wrap;"><summary>Details anzeigen</summary>' + debug + '</details>';
                    }
                    statusDiv.removeClass('loading success').addClass('error').html(html);
                }
            },
            error: function(xhr, textStatus, errorThrown) {
                button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Update');
                var detail = 'HTTP ' + xhr.status + ' ' + (errorThrown || textStatus);
                console.error('[WP-Git-Installer] AJAX-Fehler beim Synchronisieren:', detail, xhr.responseText);
                statusDiv.removeClass('loading success').addClass('error').html('✗ Verbindungsfehler: ' + detail);
            }
        });
    });

    // Delete project button handler
    jQuery(document).on('click', '.github-delete-btn', function() {
        var projectId = jQuery(this).data('project-id');

        if (!confirm('Möchten Sie dieses Projekt wirklich löschen?\n\nDas Plugin selbst wird nicht deinstalliert, nur die Projektverwaltung wird entfernt.')) {
            return;
        }

        var button = jQuery(this);
        button.prop('disabled', true);

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
                    // Fade out the row and reload
                    jQuery('tr[data-project-id="' + projectId + '"]').fadeOut(300, function() {
                        location.reload();
                    });
                } else {
                    alert('Fehler beim Löschen: ' + response.data);
                    button.prop('disabled', false);
                }
            },
            error: function() {
                alert('Ein Fehler ist beim Löschen des Projekts aufgetreten.');
                button.prop('disabled', false);
            }
        });
    });

    // Edit project button handler
    jQuery(document).on('click', '.github-edit-btn', function() {
        var projectId = jQuery(this).data('project-id');
        openEditModal(projectId);
    });

    // Modal close handlers
    jQuery(document).on('click', '.github-modal-close, .github-modal-overlay', function() {
        closeEditModal();
    });

    // Prevent closing when clicking inside modal content
    jQuery(document).on('click', '.github-modal-content', function(e) {
        e.stopPropagation();
    });

    // Edit form private checkbox handler
    jQuery('#edit_is_private').change(function() {
        if(this.checked) {
            jQuery('#edit_access_token_section').slideDown();
        } else {
            jQuery('#edit_access_token_section').slideUp();
        }
        // Reload versions when private status changes
        loadEditVersions();
    });

    // Load versions when repo URL or access token changes in edit form
    jQuery('#edit_repo_url, #edit_access_token').on('input', function() {
        clearTimeout(versionTimer);
        versionTimer = setTimeout(loadEditVersions, 800);
    });

    // Edit form submit handler
    jQuery('#edit-project-form').on('submit', function(e) {
        e.preventDefault();

        var projectId = jQuery('#edit_project_id').val();
        var formData = {
            action: 'update_github_project',
            nonce: github_installer.nonce,
            project_id: projectId,
            name: jQuery('#edit_project_name').val(),
            repo_url: jQuery('#edit_repo_url').val(),
            is_private: jQuery('#edit_is_private').is(':checked'),
            access_token: jQuery('#edit_access_token').val(),
            version: jQuery('#edit_version').val()
        };

        var saveBtn = jQuery('#save-project-btn');
        saveBtn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Speichert...');

        jQuery.ajax({
            url: github_installer.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    closeEditModal();
                    location.reload();
                } else {
                    alert('Fehler beim Speichern: ' + response.data);
                    saveBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Änderungen speichern');
                }
            },
            error: function() {
                alert('Ein Fehler ist beim Speichern des Projekts aufgetreten.');
                saveBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Änderungen speichern');
            }
        });
    });

    // Function to open edit modal
    function openEditModal(projectId) {
        jQuery.ajax({
            url: github_installer.ajax_url,
            type: 'POST',
            data: {
                action: 'get_github_project',
                nonce: github_installer.nonce,
                project_id: projectId
            },
            success: function(response) {
                if (response.success) {
                    var project = response.data;

                    // Populate form fields
                    jQuery('#edit_project_id').val(project.id);
                    jQuery('#edit_project_name').val(project.name);
                    jQuery('#edit_repo_url').val(project.repo_url);
                    jQuery('#edit_is_private').prop('checked', project.is_private);
                    jQuery('#edit_access_token').val(''); // Clear token field for security

                    // Show/hide access token section
                    if (project.is_private) {
                        jQuery('#edit_access_token_section').show();
                    } else {
                        jQuery('#edit_access_token_section').hide();
                    }

                    // Load versions
                    loadEditVersions(project.version);

                    // Show modal
                    jQuery('#edit-project-modal').fadeIn(300);
                } else {
                    alert('Fehler beim Laden des Projekts: ' + response.data);
                }
            },
            error: function() {
                alert('Ein Fehler ist beim Laden des Projekts aufgetreten.');
            }
        });
    }

    // Function to close edit modal
    function closeEditModal() {
        jQuery('#edit-project-modal').fadeOut(300);
        jQuery('#edit-project-form')[0].reset();
        jQuery('#save-project-btn').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Änderungen speichern');
    }

    // Function to load versions for edit modal
    function loadEditVersions(selectedVersion) {
        var repoUrl = jQuery('#edit_repo_url').val();
        var isPrivate = jQuery('#edit_is_private').is(':checked');
        var accessToken = jQuery('#edit_access_token').val();

        // Clear previous state
        jQuery('#edit_version').html('<option value="">-- Bitte warten, lade Versionen... --</option>');

        if (!repoUrl || repoUrl.length < 10) {
            return;
        }

        // Show loading
        jQuery('#edit-version-loading').show();

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
                jQuery('#edit-version-loading').hide();

                if (response.success && response.data.length > 0) {
                    var versions = response.data;
                    var versionSelect = jQuery('#edit_version');
                    versionSelect.empty();
                    versionSelect.append(jQuery('<option></option>').attr('value', '').text('-- Wählen Sie eine Version --'));
                    jQuery.each(versions, function(index, version) {
                        var option = jQuery('<option></option>').attr('value', version).text(version);
                        if (selectedVersion && version === selectedVersion) {
                            option.attr('selected', 'selected');
                        }
                        versionSelect.append(option);
                    });
                } else {
                    jQuery('#edit_version').html('<option value="">Keine Versionen/Tags gefunden</option>');
                    console.error('Keine Versionen gefunden:', response.data);
                }
            },
            error: function(xhr, status, error) {
                jQuery('#edit-version-loading').hide();
                jQuery('#edit_version').html('<option value="">Fehler beim Laden der Versionen</option>');
                console.error('Fehler beim Laden der Versionen:', error);
            }
        });
    }
});