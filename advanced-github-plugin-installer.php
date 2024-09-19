<?php
/**
 * Plugin Name: GitHub Plugin Installer
 * Description: Install or update WordPress plugins directly from GitHub repositories
 * Version: 1.4
 * Author: Christian Wedel
 */

// Add menu item under "Plugins"
add_action('admin_menu', 'github_plugin_installer_menu');
add_action('admin_enqueue_scripts', 'github_plugin_installer_scripts');
add_action('wp_ajax_preview_github_repo', 'preview_github_repo');
add_action('wp_ajax_get_github_versions', 'get_github_versions');
add_action('wp_ajax_check_plugin_status', 'check_plugin_status');

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

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="repo_url">GitHub Repository URL</label></th>
                    <td><input type="text" id="repo_url" name="repo_url" class="regular-text" required placeholder="https://github.com/username/repo.git"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="is_private">Private Repository?</label></th>
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
            <?php submit_button('Install/Update Plugin', 'primary', 'install_update_plugin'); ?>
        </form>
        <div id="repo_preview" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; display: none;">
            <h2>Repository Preview</h2>
            <div id="repo_content"></div>
        </div>
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