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
});