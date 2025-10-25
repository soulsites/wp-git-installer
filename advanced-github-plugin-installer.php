<?php
/**
 * Plugin Name: GitHub Plugin Installer
 * Description: Install or update WordPress plugins directly from GitHub repositories with multi-project support
 * Version: 2.0
 * Author: Christian Wedel
 */

// Add menu item under "Plugins"
add_action('admin_menu', 'github_plugin_installer_menu');
add_action('admin_enqueue_scripts', 'github_plugin_installer_scripts');
add_action('wp_ajax_preview_github_repo', 'preview_github_repo');
add_action('wp_ajax_get_github_versions', 'get_github_versions');
add_action('wp_ajax_check_plugin_status', 'check_plugin_status');
add_action('wp_ajax_save_github_project', 'save_github_project');
add_action('wp_ajax_delete_github_project', 'delete_github_project');
add_action('wp_ajax_sync_github_project', 'sync_github_project');

function github_plugin_installer_menu() {
    add_plugins_page('GitHub Plugin Installer', 'GitHub Installer', 'manage_options', 'github-plugin-installer', 'github_plugin_installer_page');
}

function github_plugin_installer_scripts($hook) {
    if ($hook != 'plugins_page_github-plugin-installer') {
        return;
    }
    wp_enqueue_script('github-plugin-installer', plugin_dir_url(__FILE__) . 'installer-script.js', array('jquery'), '1.3', true);
    wp_localize_script('github-plugin-installer', 'github_installer', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('github_installer_nonce')
    ));
}

// Helper functions for project management
function get_saved_projects() {
    $projects = get_option('github_installer_projects', array());
    return is_array($projects) ? $projects : array();
}

function save_project($project_data) {
    $projects = get_saved_projects();
    $project_id = sanitize_title($project_data['name']);
    $projects[$project_id] = array(
        'id' => $project_id,
        'name' => sanitize_text_field($project_data['name']),
        'repo_url' => esc_url_raw($project_data['repo_url']),
        'is_private' => (bool) $project_data['is_private'],
        'access_token' => !empty($project_data['access_token']) ? sanitize_text_field($project_data['access_token']) : '',
        'version' => sanitize_text_field($project_data['version']),
        'last_synced' => current_time('mysql')
    );
    update_option('github_installer_projects', $projects);
    return $project_id;
}

function delete_project($project_id) {
    $projects = get_saved_projects();
    if (isset($projects[$project_id])) {
        unset($projects[$project_id]);
        update_option('github_installer_projects', $projects);
        return true;
    }
    return false;
}

function github_plugin_installer_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['install_update_plugin'])) {
        $repo_url = sanitize_text_field($_POST['repo_url']);
        $is_private = isset($_POST['is_private']) ? true : false;
        $access_token = $is_private ? sanitize_text_field($_POST['access_token']) : '';
        $selected_version = sanitize_text_field($_POST['version']);
        $save_as_project = isset($_POST['save_as_project']) ? true : false;
        $project_name = sanitize_text_field($_POST['project_name']);

        // Install/Update the plugin
        install_update_github_plugin($repo_url, $access_token, $selected_version);

        // Save as project if checkbox was checked
        if ($save_as_project && !empty($project_name)) {
            $project_data = array(
                'name' => $project_name,
                'repo_url' => $repo_url,
                'is_private' => $is_private,
                'access_token' => $access_token,
                'version' => $selected_version
            );
            save_project($project_data);
            echo '<div class="updated"><p>Projekt erfolgreich gespeichert!</p></div>';
        }
    }

    $saved_projects = get_saved_projects();

    ?>
    <style>
        .github-installer-container {
            max-width: 1200px;
        }
        .github-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .github-card h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .form-section {
            margin-bottom: 15px;
        }
        .form-section label {
            display: inline-block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .form-section input[type="text"],
        .form-section input[type="password"],
        .form-section select {
            width: 100%;
            max-width: 500px;
        }
        .form-section .description {
            color: #646970;
            font-size: 13px;
            margin-top: 5px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
        }
        .checkbox-label input[type="checkbox"] {
            margin: 0;
        }
        .button-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .github-sync-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .github-delete-btn {
            color: #b32d2e;
        }
        .github-delete-btn:hover {
            background: #b32d2e;
            border-color: #b32d2e;
            color: #fff;
        }
        .projects-table {
            margin-top: 15px;
        }
        .projects-table td {
            vertical-align: middle;
        }
        .project-actions {
            white-space: nowrap;
        }
        .github-sync-status {
            font-size: 12px;
            padding: 8px;
            border-radius: 3px;
            margin-top: 8px;
            display: none;
        }
        .github-sync-status.success {
            background: #d7f0db;
            color: #00631e;
            border: 1px solid #00631e;
            display: block;
        }
        .github-sync-status.error {
            background: #fcf0f1;
            color: #b32d2e;
            border: 1px solid #b32d2e;
            display: block;
        }
        .github-sync-status.loading {
            background: #f0f6fc;
            color: #1d2327;
            border: 1px solid #2271b1;
            display: block;
        }
        .info-box {
            background: #f0f6fc;
            border-left: 4px solid #2271b1;
            padding: 12px;
            margin-top: 15px;
        }
        .loading-indicator {
            display: none;
            color: #646970;
            font-style: italic;
        }
    </style>
    <div class="wrap github-installer-container">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <!-- Installation/Update Form -->
        <div class="github-card">
            <h2>Plugin installieren oder aktualisieren</h2>
            <form method="post" action="" id="github-project-form">
                <div class="form-section">
                    <label for="repo_url">GitHub Repository URL *</label>
                    <input type="text" id="repo_url" name="repo_url" class="regular-text" required placeholder="https://github.com/username/repository.git">
                    <p class="description">Die vollständige URL zu Ihrem GitHub Repository</p>
                </div>

                <div class="form-section">
                    <label class="checkbox-label">
                        <input type="checkbox" id="is_private" name="is_private">
                        <span>Privates Repository (benötigt Access Token)</span>
                    </label>
                </div>

                <div class="form-section" id="access_token_section" style="display: none;">
                    <label for="access_token">GitHub Access Token</label>
                    <input type="password" id="access_token" name="access_token" class="regular-text">
                    <p class="description">
                        Token erstellen unter: <a href="https://github.com/settings/tokens" target="_blank">GitHub Settings → Personal access tokens</a>
                    </p>
                </div>

                <div class="form-section" id="version_section" style="display: none;">
                    <label for="version">Version / Tag auswählen</label>
                    <select id="version" name="version" class="regular-text">
                        <option value="">-- Bitte warten, lade Versionen... --</option>
                    </select>
                    <span class="loading-indicator" id="version-loading">Lade verfügbare Versionen...</span>
                </div>

                <div class="form-section">
                    <label class="checkbox-label">
                        <input type="checkbox" id="save_as_project" name="save_as_project">
                        <span>Als Projekt speichern (für spätere Updates)</span>
                    </label>
                    <p class="description">Wenn aktiviert, können Sie dieses Plugin später einfach über die Projektliste aktualisieren</p>
                </div>

                <div class="form-section" id="project_name_section" style="display: none;">
                    <label for="project_name">Projektname</label>
                    <input type="text" id="project_name" name="project_name" class="regular-text" placeholder="z.B. Mein WordPress Plugin">
                    <p class="description">Ein einprägsamer Name für dieses Projekt</p>
                </div>

                <div class="button-group">
                    <?php submit_button('Installieren / Aktualisieren', 'primary large', 'install_update_plugin', false); ?>
                </div>

                <div id="plugin_status"></div>
            </form>
        </div>

        <!-- Saved Projects -->
        <?php if (!empty($saved_projects)): ?>
        <div class="github-card">
            <h2>Gespeicherte Projekte</h2>
            <p class="description">Diese Projekte können mit einem Klick aktualisiert werden</p>

            <table class="wp-list-table widefat fixed striped projects-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Projektname</th>
                        <th style="width: 30%;">Repository</th>
                        <th style="width: 12%;">Version</th>
                        <th style="width: 10%;">Typ</th>
                        <th style="width: 15%;">Letzte Aktualisierung</th>
                        <th style="width: 13%;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($saved_projects as $project): ?>
                        <tr data-project-id="<?php echo esc_attr($project['id']); ?>">
                            <td><strong><?php echo esc_html($project['name']); ?></strong></td>
                            <td><code><?php echo esc_html($project['repo_url']); ?></code></td>
                            <td><?php echo esc_html($project['version']); ?></td>
                            <td>
                                <span class="<?php echo $project['is_private'] ? 'dashicons dashicons-lock' : 'dashicons dashicons-unlock'; ?>" title="<?php echo $project['is_private'] ? 'Privat' : 'Öffentlich'; ?>"></span>
                                <?php echo $project['is_private'] ? 'Privat' : 'Öffentlich'; ?>
                            </td>
                            <td><?php echo esc_html(date('d.m.Y H:i', strtotime($project['last_synced']))); ?></td>
                            <td class="project-actions">
                                <button class="button button-small button-primary github-sync-btn" data-project-id="<?php echo esc_attr($project['id']); ?>" title="Projekt aktualisieren">
                                    <span class="dashicons dashicons-update"></span> Update
                                </button>
                                <button class="button button-small github-delete-btn" data-project-id="<?php echo esc_attr($project['id']); ?>" title="Projekt löschen">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        </tr>
                        <tr class="github-sync-status-row" data-project-id="<?php echo esc_attr($project['id']); ?>" style="display: none;">
                            <td colspan="6">
                                <div class="github-sync-status" data-project-id="<?php echo esc_attr($project['id']); ?>"></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="github-card">
            <div class="info-box">
                <p><strong>💡 Tipp:</strong> Aktivieren Sie "Als Projekt speichern" beim Installieren eines Plugins, um es hier zur einfachen Verwaltung zu speichern.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

function install_update_github_plugin($repo_url, $access_token, $selected_version) {
    if (!filter_var($repo_url, FILTER_VALIDATE_URL)) {
        wp_die('Invalid GitHub URL provided.');
    }

    // Extract repository name from URL and convert to lowercase
    $repo_name = strtolower(basename(parse_url($repo_url, PHP_URL_PATH), '.git'));
    $plugin_dir = WP_PLUGIN_DIR . '/' . $repo_name;

    $is_update = file_exists($plugin_dir);

    if ($is_update) {
        // Update existing plugin
        // Update remote URL with access token if provided
        if (!empty($access_token)) {
            $auth_repo_url = str_replace('https://', "https://{$access_token}@", $repo_url);
            $set_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($auth_repo_url) . " 2>&1";
            exec($set_url_command, $url_output, $url_return);
        }

        // Validate version before checkout
        if (!empty($selected_version)) {
            $update_command = "cd " . escapeshellarg($plugin_dir) . " && git fetch --all && git checkout " . escapeshellarg($selected_version) . " 2>&1";
            exec($update_command, $output, $return_var);

            if ($return_var !== 0) {
                // Reset remote URL to original (without token) for security
                if (!empty($access_token)) {
                    $reset_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($repo_url) . " 2>&1";
                    exec($reset_url_command, $reset_output, $reset_return);
                }
                wp_die('Failed to update the plugin. Error: ' . implode("\n", $output));
            }
        } else {
            // If no version specified, just fetch and pull the default branch
            $update_command = "cd " . escapeshellarg($plugin_dir) . " && git fetch --all && git pull 2>&1";
            exec($update_command, $output, $return_var);

            if ($return_var !== 0) {
                // Reset remote URL to original (without token) for security
                if (!empty($access_token)) {
                    $reset_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($repo_url) . " 2>&1";
                    exec($reset_url_command, $reset_output, $reset_return);
                }
                wp_die('Failed to update the plugin. Error: ' . implode("\n", $output));
            }
        }

        // Reset remote URL to original (without token) for security
        if (!empty($access_token)) {
            $reset_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($repo_url) . " 2>&1";
            exec($reset_url_command, $reset_output, $reset_return);
        }
    } else {
        // Install new plugin
        $clone_command = "git clone ";
        if (!empty($access_token)) {
            $repo_url_with_token = str_replace('https://', "https://{$access_token}@", $repo_url);
            $clone_command .= escapeshellarg($repo_url_with_token) . " " . escapeshellarg($plugin_dir);
        } else {
            $clone_command .= escapeshellarg($repo_url) . " " . escapeshellarg($plugin_dir);
        }

        exec($clone_command, $output, $return_var);

        if ($return_var !== 0) {
            wp_die('Failed to clone the repository. Error: ' . implode("\n", $output));
        }

        // Reset remote URL to original (without token) for security
        if (!empty($access_token)) {
            $reset_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($repo_url) . " 2>&1";
            exec($reset_url_command, $reset_output, $reset_return);
        }

        // Checkout the selected version
        if (!empty($selected_version)) {
            $checkout_command = "cd " . escapeshellarg($plugin_dir) . " && git checkout " . escapeshellarg($selected_version);
            exec($checkout_command, $output, $return_var);

            if ($return_var !== 0) {
                wp_die('Failed to checkout version ' . $selected_version . '. Error: ' . implode("\n", $output));
            }
        }
    }

    // Find the main plugin file
    $plugin_file = find_main_plugin_file($plugin_dir);

    if (!$plugin_file) {
        wp_die('Plugin main file not found. Please check the repository structure.');
    }

    $relative_plugin_file = $repo_name . '/' . $plugin_file;

    // Provide a success message with a link to the plugins page
    $plugins_page_url = admin_url('plugins.php');
    $action = $is_update ? 'updated' : 'installed';
    echo '<div class="updated"><p>Plugin ' . $action . ' successfully! You can <a href="' . esc_url($plugins_page_url) . '">go to the Plugins page</a> to activate or manage it.</p></div>';
}

function find_main_plugin_file($plugin_dir) {
    $php_files = glob($plugin_dir . '/*.php');
    
    foreach ($php_files as $file) {
        $content = file_get_contents($file);
        if (preg_match('/Plugin Name:/i', $content)) {
            return basename($file);
        }
    }

    return !empty($php_files) ? basename($php_files[0]) : false;
}

function preview_github_repo() {
    check_ajax_referer('github_installer_nonce', 'nonce');

    $repo_url = $_POST['repo_url'];
    $is_private = isset($_POST['is_private']) && $_POST['is_private'] === 'true';
    $access_token = $is_private ? $_POST['access_token'] : '';

    if (!filter_var($repo_url, FILTER_VALIDATE_URL)) {
        wp_send_json_error('Invalid GitHub URL provided.');
    }

    $api_url = str_replace('github.com', 'api.github.com/repos', $repo_url);
    $api_url = rtrim($api_url, '.git') . '/contents';

    $args = array(
        'headers' => array(
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/GitHub Plugin Installer'
        )
    );

    if ($is_private && !empty($access_token)) {
        $args['headers']['Authorization'] = 'token ' . $access_token;
    }

    $response = wp_remote_get($api_url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error('Failed to fetch repository content: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (isset($data->message) && $data->message === 'Not Found') {
        wp_send_json_error('Repository not found or access denied.');
    }

    $content = '<ul>';
    foreach ($data as $item) {
        $content .= '<li>' . esc_html($item->name) . ' (' . esc_html($item->type) . ')</li>';
    }
    $content .= '</ul>';

    wp_send_json_success($content);
}

function get_github_versions() {
    check_ajax_referer('github_installer_nonce', 'nonce');

    $repo_url = $_POST['repo_url'];
    $is_private = isset($_POST['is_private']) && $_POST['is_private'] === 'true';
    $access_token = $is_private ? $_POST['access_token'] : '';

    if (!filter_var($repo_url, FILTER_VALIDATE_URL)) {
        wp_send_json_error('Invalid GitHub URL provided.');
    }

    $api_url = str_replace('github.com', 'api.github.com/repos', $repo_url);
    $api_url = rtrim($api_url, '.git') . '/tags';

    $args = array(
        'headers' => array(
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/GitHub Plugin Installer'
        )
    );

    if ($is_private && !empty($access_token)) {
        $args['headers']['Authorization'] = 'token ' . $access_token;
    }

    $response = wp_remote_get($api_url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error('Failed to fetch repository tags: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $tags = json_decode($body);

    if (empty($tags)) {
        wp_send_json_error('No tags found in the repository.');
    }

    $versions = array();
    foreach ($tags as $tag) {
        $versions[] = $tag->name;
    }

    wp_send_json_success($versions);
}

function check_plugin_status() {
    check_ajax_referer('github_installer_nonce', 'nonce');

    $repo_url = $_POST['repo_url'];

    if (!filter_var($repo_url, FILTER_VALIDATE_URL)) {
        wp_send_json_error('Invalid GitHub URL provided.');
    }

    $repo_name = strtolower(basename(parse_url($repo_url, PHP_URL_PATH), '.git'));
    $plugin_dir = WP_PLUGIN_DIR . '/' . $repo_name;

    if (file_exists($plugin_dir)) {
        $status = 'installed';
        // Get current version
        $current_version = 'Unknown';
        if (is_dir($plugin_dir . '/.git')) {
            $version_command = "cd " . escapeshellarg($plugin_dir) . " && git describe --tags --abbrev=0";
            exec($version_command, $output, $return_var);
            if ($return_var === 0 && !empty($output)) {
                $current_version = $output[0];
            }
        }
        wp_send_json_success(array('status' => $status, 'version' => $current_version));
    } else {
        wp_send_json_success(array('status' => 'not_installed'));
    }
}

function save_github_project() {
    check_ajax_referer('github_installer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions.');
    }

    $project_data = array(
        'name' => sanitize_text_field($_POST['name']),
        'repo_url' => esc_url_raw($_POST['repo_url']),
        'is_private' => !empty($_POST['is_private']) && $_POST['is_private'] !== 'false' && $_POST['is_private'] !== '0',
        'access_token' => isset($_POST['access_token']) ? sanitize_text_field($_POST['access_token']) : '',
        'version' => sanitize_text_field($_POST['version'])
    );

    if (empty($project_data['name']) || empty($project_data['repo_url'])) {
        wp_send_json_error('Name und Repository URL sind erforderlich.');
    }

    $project_id = save_project($project_data);
    wp_send_json_success(array('message' => 'Projekt erfolgreich gespeichert!', 'project_id' => $project_id));
}

function delete_github_project() {
    check_ajax_referer('github_installer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions.');
    }

    $project_id = sanitize_text_field($_POST['project_id']);

    if (delete_project($project_id)) {
        wp_send_json_success('Projekt erfolgreich gelöscht!');
    } else {
        wp_send_json_error('Projekt konnte nicht gelöscht werden.');
    }
}

function sync_github_project() {
    check_ajax_referer('github_installer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions.');
    }

    $project_id = sanitize_text_field($_POST['project_id']);
    $projects = get_saved_projects();

    if (!isset($projects[$project_id])) {
        wp_send_json_error('Projekt nicht gefunden.');
    }

    $project = $projects[$project_id];

    // Perform the sync
    try {
        $repo_url = $project['repo_url'];
        $access_token = $project['access_token'];
        $version = $project['version'];

        $repo_name = strtolower(basename(parse_url($repo_url, PHP_URL_PATH), '.git'));
        $plugin_dir = WP_PLUGIN_DIR . '/' . $repo_name;

        if (!file_exists($plugin_dir)) {
            wp_send_json_error('Plugin ist nicht installiert. Bitte installieren Sie es zuerst über das Formular oben.');
        }

        // Update remote URL with access token if private repository
        if ($project['is_private'] && !empty($access_token)) {
            $auth_repo_url = str_replace('https://', "https://{$access_token}@", $repo_url);
            $set_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($auth_repo_url) . " 2>&1";
            exec($set_url_command, $url_output, $url_return);
        }

        // Update existing plugin - validate version before checkout
        if (!empty($version)) {
            $update_command = "cd " . escapeshellarg($plugin_dir) . " && git fetch --all && git checkout " . escapeshellarg($version) . " 2>&1";
            exec($update_command, $output, $return_var);

            if ($return_var !== 0) {
                // Reset remote URL to original (without token) for security
                if ($project['is_private'] && !empty($access_token)) {
                    $reset_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($repo_url) . " 2>&1";
                    exec($reset_url_command, $reset_output, $reset_return);
                }
                wp_send_json_error('Synchronisierung fehlgeschlagen: ' . implode("\n", $output));
            }
        } else {
            // If no version specified, just fetch and pull the default branch
            $update_command = "cd " . escapeshellarg($plugin_dir) . " && git fetch --all && git pull 2>&1";
            exec($update_command, $output, $return_var);

            if ($return_var !== 0) {
                // Reset remote URL to original (without token) for security
                if ($project['is_private'] && !empty($access_token)) {
                    $reset_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($repo_url) . " 2>&1";
                    exec($reset_url_command, $reset_output, $reset_return);
                }
                wp_send_json_error('Synchronisierung fehlgeschlagen: ' . implode("\n", $output));
            }
        }

        // Reset remote URL to original (without token) for security
        if ($project['is_private'] && !empty($access_token)) {
            $reset_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($repo_url) . " 2>&1";
            exec($reset_url_command, $reset_output, $reset_return);
        }

        // Update last_synced timestamp
        $project['last_synced'] = current_time('mysql');
        $projects[$project_id] = $project;
        update_option('github_installer_projects', $projects);

        wp_send_json_success('Projekt erfolgreich synchronisiert!');
    } catch (Exception $e) {
        wp_send_json_error('Fehler bei der Synchronisierung: ' . $e->getMessage());
    }
}