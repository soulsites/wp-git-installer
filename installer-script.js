jQuery(document).ready(function($) {
    var versionTimer;

    // Show/hide access token field based on private checkbox
    $('#is_private').change(function() {
        if(this.checked) {
            $('#access_token_section').slideDown();
        } else {
            $('#access_token_section').slideUp();
            $('#access_token').val('');
        }
        // Reload versions when private status changes
        loadVersions();
    });

    // Show/hide project name field based on save as project checkbox
    $('#save_as_project').change(function() {
        if(this.checked) {
            $('#project_name_section').slideDown();
            // Pre-fill project name from repo URL if empty
            if(!$('#project_name').val()) {
                var repoUrl = $('#repo_url').val();
                if(repoUrl) {
                    var repoName = repoUrl.split('/').pop().replace('.git', '');
                    $('#project_name').val(repoName.charAt(0).toUpperCase() + repoName.slice(1));
                }
            }
        } else {
            $('#project_name_section').slideUp();
        }
    });

    // Load versions when repo URL or access token changes
    $('#repo_url, #access_token').on('input', function() {
        clearTimeout(versionTimer);
        versionTimer = setTimeout(loadVersions, 800);
    });

    // Load versions function
    function loadVersions() {
        var repoUrl = $('#repo_url').val();
        var isPrivate = $('#is_private').is(':checked');
        var accessToken = $('#access_token').val();

        // Clear previous state
        $('#version_section').hide();
        $('#version').html('<option value="">-- Bitte warten, lade Versionen... --</option>');

        if (!repoUrl || repoUrl.length < 10) {
            return;
        }

        // Show loading
        $('#version_section').slideDown();
        $('#version-loading').show();

        $.ajax({
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
                $('#version-loading').hide();

                if (response.success && response.data.length > 0) {
                    var versions = response.data;
                    var versionSelect = $('#version');
                    versionSelect.empty();
                    versionSelect.append($('<option></option>').attr('value', '').text('-- Wählen Sie eine Version --'));
                    $.each(versions, function(index, version) {
                        versionSelect.append($('<option></option>').attr('value', version).text(version));
                    });
                    $('#version_section').slideDown();
                } else {
                    $('#version').html('<option value="">Keine Versionen/Tags gefunden</option>');
                    console.error('Keine Versionen gefunden:', response.data);
                }
            },
            error: function(xhr, status, error) {
                $('#version-loading').hide();
                $('#version').html('<option value="">Fehler beim Laden der Versionen</option>');
                console.error('Fehler beim Laden der Versionen:', error);
            }
        });
    }

    // Form validation before submit
    $('#github-project-form').on('submit', function(e) {
        var saveAsProject = $('#save_as_project').is(':checked');
        var projectName = $('#project_name').val();
        var repoUrl = $('#repo_url').val();
        var version = $('#version').val();

        // Check if save as project is checked but no project name
        if (saveAsProject && !projectName) {
            e.preventDefault();
            alert('Bitte geben Sie einen Projektnamen ein oder deaktivieren Sie "Als Projekt speichern".');
            $('#project_name').focus();
            return false;
        }

        // Check if repo URL is provided
        if (!repoUrl) {
            e.preventDefault();
            alert('Bitte geben Sie eine GitHub Repository URL ein.');
            $('#repo_url').focus();
            return false;
        }

        // Warning if no version selected
        if (!version) {
            return confirm('Sie haben keine Version ausgewählt. Es wird die neueste Version vom Hauptbranch verwendet. Fortfahren?');
        }

        return true;
    });

    // Sync project button handler
    $(document).on('click', '.github-sync-btn', function() {
        var projectId = $(this).data('project-id');
        var button = $(this);
        var statusRow = $('.github-sync-status-row[data-project-id="' + projectId + '"]');
        var statusDiv = $('.github-sync-status[data-project-id="' + projectId + '"]');

        button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Lädt...');
        statusRow.show();
        statusDiv.removeClass('success error').addClass('loading').text('Synchronisierung läuft...').show();

        $.ajax({
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
                    statusDiv.removeClass('loading success').addClass('error').html('✗ Fehler: ' + response.data);
                }
            },
            error: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Update');
                statusDiv.removeClass('loading success').addClass('error').html('✗ Ein Fehler ist aufgetreten.');
            }
        });
    });

    // Delete project button handler
    $(document).on('click', '.github-delete-btn', function() {
        var projectId = $(this).data('project-id');

        if (!confirm('Möchten Sie dieses Projekt wirklich löschen?\n\nDas Plugin selbst wird nicht deinstalliert, nur die Projektverwaltung wird entfernt.')) {
            return;
        }

        var button = $(this);
        button.prop('disabled', true);

        $.ajax({
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
                    $('tr[data-project-id="' + projectId + '"]').fadeOut(300, function() {
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
});