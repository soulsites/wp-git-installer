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

        install_update_github_plugin($repo_url, $access_token, $selected_version);
    }

    $saved_projects = get_saved_projects();

    ?>
    <style>
        .github-projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .github-project-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            transition: box-shadow 0.2s ease;
        }
        .github-project-card:hover {
            box-shadow: 0 2px 6px rgba(0,0,0,.1);
        }
        .github-project-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
            color: #1d2327;
        }
        .github-project-info {
            font-size: 13px;
            color: #50575e;
            margin-bottom: 15px;
        }
        .github-project-info p {
            margin: 5px 0;
            word-break: break-all;
        }
        .github-project-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .github-sync-btn {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            border: 1px solid;
            font-size: 13px;
            transition: background 0.2s ease;
        }
        .github-sync-btn:hover {
            background: #135e96;
            border-color: #135e96;
        }
        .github-sync-btn:disabled {
            background: #c3c4c7;
            border-color: #c3c4c7;
            cursor: not-allowed;
        }
        .github-delete-btn {
            background: #fff;
            border-color: #c3c4c7;
            color: #b32d2e;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            border: 1px solid;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .github-delete-btn:hover {
            background: #b32d2e;
            border-color: #b32d2e;
            color: #fff;
        }
        .github-sync-status {
            font-size: 12px;
            margin-top: 10px;
            padding: 8px;
            border-radius: 3px;
            display: none;
        }
        .github-sync-status.success {
            background: #d7f0db;
            color: #00631e;
            border: 1px solid #00631e;
        }
        .github-sync-status.error {
            background: #fcf0f1;
            color: #b32d2e;
            border: 1px solid #b32d2e;
        }
        .github-sync-status.loading {
            background: #f0f6fc;
            color: #1d2327;
            border: 1px solid #2271b1;
        }
        .github-add-project-section {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .github-save-project-btn {
            background: #00a32a;
            border-color: #00a32a;
            color: #fff;
            margin-left: 10px;
        }
        .github-save-project-btn:hover {
            background: #008a20;
            border-color: #008a20;
        }
    </style>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div class="github-add-project-section">
            <h2>Neues Projekt hinzufügen</h2>
            <form method="post" action="" id="github-project-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="project_name">Projektname</label></th>
                        <td><input type="text" id="project_name" name="project_name" class="regular-text" placeholder="Mein GitHub Plugin"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="repo_url">GitHub Repository URL</label></th>
                        <td><input type="text" id="repo_url" name="repo_url" class="regular-text" required placeholder="https://github.com/username/repo.git"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="is_private">Privates Repository?</label></th>
                        <td><input type="checkbox" id="is_private" name="is_private"></td>
                    </tr>
                    <tr id="access_token_row" style="display: none;">
                        <th scope="row"><label for="access_token">GitHub Access Token</label></th>
                        <td>
                            <input type="password" id="access_token" name="access_token" class="regular-text">
                            <p class="description">
                                To generate a Personal Access Token, go to
                                <a href="https://github.com/settings/tokens" target="_blank">GitHub Settings > Developer settings > Personal access tokens</a>.
                                Create a new token with the 'repo' scope for private repositories.
                            </p>
                        </td>
                    </tr>
                    <tr id="version_row" style="display: none;">
                        <th scope="row"><label for="version">Version</label></th>
                        <td><select id="version" name="version"></select></td>
                    </tr>
                </table>
                <div id="plugin_status"></div>
                <?php submit_button('Installieren/Aktualisieren', 'primary', 'install_update_plugin', false); ?>
                <button type="button" id="save_project_btn" class="button github-save-project-btn" style="display:none;">Als Projekt speichern</button>
            </form>
            <div id="repo_preview" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; display: none;">
                <h3>Repository Preview</h3>
                <div id="repo_content"></div>
            </div>
        </div>

        <?php if (!empty($saved_projects)): ?>
            <h2>Gespeicherte Projekte</h2>
            <div class="github-projects-grid">
                <?php foreach ($saved_projects as $project): ?>
                    <div class="github-project-card" data-project-id="<?php echo esc_attr($project['id']); ?>">
                        <h3><?php echo esc_html($project['name']); ?></h3>
                        <div class="github-project-info">
                            <p><strong>Repository:</strong> <?php echo esc_html($project['repo_url']); ?></p>
                            <p><strong>Version:</strong> <?php echo esc_html($project['version']); ?></p>
                            <p><strong>Status:</strong> <?php echo $project['is_private'] ? 'Privat' : 'Öffentlich'; ?></p>
                            <p><strong>Zuletzt synchronisiert:</strong> <?php echo esc_html($project['last_synced']); ?></p>
                        </div>
                        <div class="github-project-actions">
                            <button class="github-sync-btn" data-project-id="<?php echo esc_attr($project['id']); ?>">
                                Synchronisieren
                            </button>
                            <button class="github-delete-btn" data-project-id="<?php echo esc_attr($project['id']); ?>">
                                Löschen
                            </button>
                        </div>
                        <div class="github-sync-status" data-project-id="<?php echo esc_attr($project['id']); ?>"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="notice notice-info">
                <p>Keine gespeicherten Projekte vorhanden. Fügen Sie oben ein neues Projekt hinzu.</p>
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
        $update_command = "cd " . escapeshellarg($plugin_dir) . " && git fetch --all && git checkout " . escapeshellarg($selected_version);
        exec($update_command, $output, $return_var);

        if ($return_var !== 0) {
            wp_die('Failed to update the plugin. Error: ' . implode("\n", $output));
        }
    } else {
        // Install new plugin
        $clone_command = "git clone ";
        if (!empty($access_token)) {
            $repo_url = str_replace('https://', "https://{$access_token}@", $repo_url);
        }
        $clone_command .= escapeshellarg($repo_url) . " " . escapeshellarg($plugin_dir);

        exec($clone_command, $output, $return_var);

        if ($return_var !== 0) {
            wp_die('Failed to clone the repository. Error: ' . implode("\n", $output));
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
        'is_private' => isset($_POST['is_private']) && $_POST['is_private'] === 'true',
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

        // Update existing plugin
        $update_command = "cd " . escapeshellarg($plugin_dir) . " && git fetch --all && git checkout " . escapeshellarg($version) . " 2>&1";
        exec($update_command, $output, $return_var);

        if ($return_var !== 0) {
            wp_send_json_error('Synchronisierung fehlgeschlagen: ' . implode("\n", $output));
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