<?php
/**
 * Plugin Name: GitHub Plugin Installer
 * Description: Install or update WordPress plugins directly from GitHub repositories with multi-project support and modern Material Design 3 UI
 * Version: 2.1.2
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
add_action('wp_ajax_update_github_project', 'update_github_project');
add_action('wp_ajax_get_github_project', 'get_github_project');

function github_plugin_installer_menu() {
    add_plugins_page('GitHub Plugin Installer', 'GitHub Installer', 'manage_options', 'github-plugin-installer', 'github_plugin_installer_page');
}

function github_plugin_installer_scripts($hook) {
    if ($hook != 'plugins_page_github-plugin-installer') {
        return;
    }
    wp_enqueue_style('github-plugin-installer-admin', plugin_dir_url(__FILE__) . 'admin.css', array(), '2.0');
    wp_enqueue_script('github-plugin-installer', plugin_dir_url(__FILE__) . 'installer-script.js', array('jquery'), '2.0', true);
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
        'respect_gitignore' => !empty($project_data['respect_gitignore']),
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
        $respect_gitignore = isset($_POST['respect_gitignore']) ? true : false;

        // Install/Update the plugin
        install_update_github_plugin($repo_url, $access_token, $selected_version, $respect_gitignore);

        // Save as project if checkbox was checked
        if ($save_as_project && !empty($project_name)) {
            $project_data = array(
                'name' => $project_name,
                'repo_url' => $repo_url,
                'is_private' => $is_private,
                'access_token' => $access_token,
                'version' => $selected_version,
                'respect_gitignore' => $respect_gitignore
            );
            save_project($project_data);
            echo '<div class="updated"><p>Projekt erfolgreich gespeichert!</p></div>';
        }
    }

    $saved_projects = get_saved_projects();

    ?>
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

                <div class="form-section">
                    <label class="checkbox-label">
                        <input type="checkbox" id="respect_gitignore" name="respect_gitignore">
                        <span>.gitignore beachten (vendor, node_modules etc. entfernen)</span>
                    </label>
                    <p class="description">Nach dem Download werden Verzeichnisse und Dateien entfernt, die in der <code>.gitignore</code> des Plugins aufgeführt sind. Spart Speicherplatz, wenn der Vendor-Ordner im Repository eingecheckt ist.</p>
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
                        <th style="width: 15%;">Projektname</th>
                        <th style="width: 20%;">Repository</th>
                        <th style="width: 12%;">Version</th>
                        <th style="width: 10%;">Typ</th>
                        <th style="width: 15%;">Letzte Aktualisierung</th>
                        <th style="width: 28%;">Aktionen</th>
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
                                <button class="button button-small github-edit-btn" data-project-id="<?php echo esc_attr($project['id']); ?>" title="Projekt bearbeiten">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button class="button button-small button-primary github-sync-btn" data-project-id="<?php echo esc_attr($project['id']); ?>" title="Projekt aktualisieren">
                                    <span class="dashicons dashicons-update"></span>
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

        <!-- Edit Project Modal -->
        <div id="edit-project-modal" class="github-modal" style="display: none;">
            <div class="github-modal-overlay"></div>
            <div class="github-modal-content">
                <div class="github-modal-header">
                    <h2>Projekt bearbeiten</h2>
                    <button class="github-modal-close" title="Schließen">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="github-modal-body">
                    <form id="edit-project-form">
                        <input type="hidden" id="edit_project_id" name="project_id">

                        <div class="form-section">
                            <label for="edit_project_name">Projektname *</label>
                            <input type="text" id="edit_project_name" name="name" class="regular-text" required>
                        </div>

                        <div class="form-section">
                            <label for="edit_repo_url">GitHub Repository URL *</label>
                            <input type="text" id="edit_repo_url" name="repo_url" class="regular-text" required>
                            <p class="description">Die vollständige URL zu Ihrem GitHub Repository</p>
                        </div>

                        <div class="form-section">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit_is_private" name="is_private">
                                <span>Privates Repository (benötigt Access Token)</span>
                            </label>
                        </div>

                        <div class="form-section" id="edit_access_token_section" style="display: none;">
                            <label for="edit_access_token">GitHub Access Token</label>
                            <input type="password" id="edit_access_token" name="access_token" class="regular-text" placeholder="Token eingeben oder leer lassen, um das bestehende zu behalten">
                            <p class="description">
                                Leer lassen, um das bestehende Token zu behalten. Token erstellen unter:
                                <a href="https://github.com/settings/tokens" target="_blank">GitHub Settings → Personal access tokens</a>
                            </p>
                        </div>

                        <div class="form-section" id="edit_version_section">
                            <label for="edit_version">Version / Tag auswählen</label>
                            <select id="edit_version" name="version" class="regular-text">
                                <option value="">-- Bitte warten, lade Versionen... --</option>
                            </select>
                            <span class="loading-indicator" id="edit-version-loading">Lade verfügbare Versionen...</span>
                        </div>

                        <div class="form-section">
                            <label class="checkbox-label">
                                <input type="checkbox" id="edit_respect_gitignore" name="respect_gitignore">
                                <span>.gitignore beachten (vendor, node_modules etc. entfernen)</span>
                            </label>
                            <p class="description">Bei jedem Update werden Verzeichnisse und Dateien entfernt, die in der <code>.gitignore</code> des Plugins aufgeführt sind.</p>
                        </div>

                        <div class="github-modal-footer">
                            <button type="button" class="button github-modal-close">Abbrechen</button>
                            <button type="submit" class="button button-primary" id="save-project-btn">
                                <span class="dashicons dashicons-yes"></span> Änderungen speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Führt eine Shell-/Git-Befehlskette aus und fängt dabei stdout UND stderr ein.
 *
 * Die komplette Kette wird in eine Subshell geklammert, damit die Umleitung für
 * jeden Befehl darin gilt: "a && b 2>&1" leitet nur den stderr von b um - und
 * Git schreibt praktisch alle Fehlermeldungen nach stderr. Ohne die Klammer
 * lieferte eine fehlgeschlagene Kette (z. B. abgebrochenes "git fetch") eine
 * leere Ausgabe, und die Fehlermeldung im Backend endete nach "Error: ".
 *
 * @param string $command    Die auszuführende Befehlskette (ohne eigene Umleitung).
 * @param array  $output     Wird mit den Ausgabezeilen befüllt (vorher geleert).
 * @param int    $exit_code  Exit-Code des letzten ausgeführten Befehls.
 *
 * @return bool True, wenn der Exit-Code 0 war.
 */
function agpi_run_command($command, &$output, &$exit_code) {
    // exec() hängt an ein bereits gefülltes Array an - ohne Reset würden sich
    // Ausgaben mehrerer Aufrufe vermischen.
    $output    = array();
    $exit_code = 0;

    exec('( ' . $command . ' ) 2>&1', $output, $exit_code);

    return $exit_code === 0;
}

/**
 * Entfernt Zugangsdaten aus Git-Ausgaben.
 *
 * Solange die Remote-URL den Access-Token enthält, gibt Git ihn in
 * Fehlermeldungen mit aus ("fatal: unable to access 'https://TOKEN@github.com/…'").
 * Diese Ausgaben landen im Backend und im Error-Log, deshalb wird der Token
 * vorher ersetzt - sowohl der konkret bekannte als auch generisch jede
 * "user:pass@host"-Form.
 */
function agpi_redact_credentials($text, $access_token = '') {
    if (!empty($access_token)) {
        $text = str_replace($access_token, '***', $text);
    }

    return preg_replace('~(https?://)[^/\s:@]+(:[^/\s@]*)?@~i', '$1***@', $text);
}

/**
 * Baut eine lesbare Fehlermeldung aus Exit-Code und Befehlsausgabe.
 */
function agpi_format_command_error($message, $output, $exit_code, $access_token = '') {
    $detail = trim(implode("\n", array_filter(array_map('trim', (array) $output))));

    if ($detail === '') {
        $detail = 'Keine Ausgabe.';
    }

    return $message . ' (Exit-Code ' . (int) $exit_code . ')' . "\n"
        . agpi_redact_credentials($detail, $access_token);
}

/**
 * Bricht mit einer formatierten, mehrzeiligen Git-Fehlermeldung ab.
 *
 * nl2br() nach esc_html(): Git-Fehler sind oft mehrzeilig, und wp_die() gibt die
 * Meldung als HTML aus - ohne <br> würde alles zu einer Zeile zusammenlaufen.
 */
function agpi_die_command_error($message, $output, $exit_code, $access_token = '') {
    wp_die(nl2br(esc_html(agpi_format_command_error($message, $output, $exit_code, $access_token))));
}

function apply_gitignore_cleanup($plugin_dir) {
    $plugin_dir = realpath($plugin_dir);
    if (!$plugin_dir) {
        return;
    }

    // Remove untracked files that are ignored by .gitignore
    exec("cd " . escapeshellarg($plugin_dir) . " && git clean -fdX 2>&1");

    // Also remove tracked directories/files explicitly listed in .gitignore
    $gitignore_file = $plugin_dir . '/.gitignore';
    if (!file_exists($gitignore_file)) {
        return;
    }

    $lines = file($gitignore_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments, empty lines, and negations
        if (empty($line) || $line[0] === '#' || $line[0] === '!') {
            continue;
        }
        // Skip patterns with wildcards or shell glob characters (too broad to handle safely)
        if (strpos($line, '*') !== false || strpos($line, '?') !== false || strpos($line, '[') !== false) {
            continue;
        }
        $path = rtrim($line, '/');
        // Skip absolute paths or path traversal attempts
        if ($path[0] === '/' || strpos($path, '..') !== false) {
            continue;
        }
        $full_path = $plugin_dir . '/' . $path;
        // Resolve and verify the path stays within the plugin directory
        $real_parent = realpath(dirname($full_path));
        if (!$real_parent || strpos($real_parent . '/', $plugin_dir . '/') !== 0 && $real_parent !== $plugin_dir) {
            continue;
        }
        if (is_dir($full_path)) {
            exec("rm -rf " . escapeshellarg($full_path));
        } elseif (is_file($full_path)) {
            unlink($full_path);
        }
    }
}

function install_update_github_plugin($repo_url, $access_token, $selected_version, $respect_gitignore = false) {
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
            $set_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($auth_repo_url);
            agpi_run_command($set_url_command, $url_output, $url_return);
        }

        $reset_remote_url = function () use ($plugin_dir, $repo_url, $access_token) {
            if (empty($access_token)) {
                return;
            }
            $reset_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($repo_url);
            agpi_run_command($reset_url_command, $reset_output, $reset_return);
        };

        // Validate version before checkout
        if (!empty($selected_version)) {
            // Fetch all refs, discard local changes, and checkout specific version
            $update_command = "cd " . escapeshellarg($plugin_dir)
                . " && git fetch --all --tags"
                . " && git checkout -f " . escapeshellarg($selected_version)
                . " && git clean -fd";

            if (!agpi_run_command($update_command, $output, $return_var)) {
                // Reset remote URL to original (without token) for security
                $reset_remote_url();
                agpi_die_command_error(
                    'Failed to update the plugin.',
                    $output,
                    $return_var,
                    $access_token
                );
            }
        } else {
            // If no version specified, get default branch and pull latest changes.
            // The default branch is asked directly from the remote (git ls-remote)
            // instead of relying on the locally cached refs/remotes/origin/HEAD,
            // which is only set once at clone time and does not update when the
            // remote's default branch is changed (e.g. switched away and back).
            $branch_command = "cd " . escapeshellarg($plugin_dir) . " && git ls-remote --symref origin HEAD 2>/dev/null | sed -n 's@^ref:[[:space:]]*refs/heads/\\([^[:space:]]*\\)[[:space:]]*HEAD@\\1@p'";
            exec($branch_command, $branch_output, $branch_return);

            $default_branch = !empty($branch_output) ? trim($branch_output[0]) : 'main';

            // Fetch and checkout default branch, overwriting local changes
            $update_command = "cd " . escapeshellarg($plugin_dir)
                . " && git fetch origin"
                . " && git checkout -f " . escapeshellarg($default_branch)
                . " && git reset --hard origin/" . escapeshellarg($default_branch)
                . " && git clean -fd";

            if (!agpi_run_command($update_command, $output, $return_var)) {
                // Reset remote URL to original (without token) for security
                $reset_remote_url();
                agpi_die_command_error(
                    'Failed to update the plugin (Branch "' . $default_branch . '").',
                    $output,
                    $return_var,
                    $access_token
                );
            }
        }

        // Reset remote URL to original (without token) for security
        $reset_remote_url();
    } else {
        // Install new plugin
        $clone_command = "git clone ";
        if (!empty($access_token)) {
            $repo_url_with_token = str_replace('https://', "https://{$access_token}@", $repo_url);
            $clone_command .= escapeshellarg($repo_url_with_token) . " " . escapeshellarg($plugin_dir);
        } else {
            $clone_command .= escapeshellarg($repo_url) . " " . escapeshellarg($plugin_dir);
        }

        if (!agpi_run_command($clone_command, $output, $return_var)) {
            agpi_die_command_error(
                'Failed to clone the repository.',
                $output,
                $return_var,
                $access_token
            );
        }

        // Reset remote URL to original (without token) for security
        if (!empty($access_token)) {
            $reset_url_command = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($repo_url);
            agpi_run_command($reset_url_command, $reset_output, $reset_return);
        }

        // Checkout the selected version
        if (!empty($selected_version)) {
            $checkout_command = "cd " . escapeshellarg($plugin_dir) . " && git checkout " . escapeshellarg($selected_version);

            if (!agpi_run_command($checkout_command, $output, $return_var)) {
                agpi_die_command_error(
                    'Failed to checkout version ' . $selected_version . '.',
                    $output,
                    $return_var,
                    $access_token
                );
            }
        }
    }

    // Remove gitignored files/directories if option is enabled
    if ($respect_gitignore) {
        apply_gitignore_cleanup($plugin_dir);
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
        'version' => sanitize_text_field($_POST['version']),
        'respect_gitignore' => !empty($_POST['respect_gitignore']) && $_POST['respect_gitignore'] !== 'false' && $_POST['respect_gitignore'] !== '0'
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

    $log_prefix = '[WP-Git-Installer] Sync project_id=' . $project_id . ' repo=' . ($project['repo_url'] ?? '?');

    // Perform the sync
    try {
        $repo_url = $project['repo_url'];
        $access_token = $project['access_token'];
        $version = $project['version'];

        $repo_name = strtolower(basename(parse_url($repo_url, PHP_URL_PATH), '.git'));
        $plugin_dir = WP_PLUGIN_DIR . '/' . $repo_name;

        error_log($log_prefix . ' | Plugin-Verzeichnis: ' . $plugin_dir . ' | Version: ' . ($version ?: 'latest'));

        if (!file_exists($plugin_dir)) {
            $msg = 'Plugin-Verzeichnis nicht gefunden: ' . $plugin_dir . '. Bitte installieren Sie es zuerst über das Formular oben.';
            error_log($log_prefix . ' | FEHLER: ' . $msg);
            wp_send_json_error($msg);
        }

        $debug_steps = [];

        // Update remote URL with access token if private repository
        if ($project['is_private'] && !empty($access_token)) {
            $auth_repo_url = str_replace('https://', "https://{$access_token}@", $repo_url);
            $set_url_cmd = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($auth_repo_url);
            if (!agpi_run_command($set_url_cmd, $set_url_result, $set_url_code)) {
                $detail = agpi_format_command_error('git remote set-url fehlgeschlagen.', $set_url_result, $set_url_code, $access_token);
                error_log($log_prefix . ' | FEHLER: ' . $detail);
                wp_send_json_error('Authentifizierung fehlgeschlagen. ' . $detail);
            }
        }

        $reset_url = function() use ($plugin_dir, $repo_url, $project, $access_token, $log_prefix) {
            if ($project['is_private'] && !empty($access_token)) {
                $cmd = "cd " . escapeshellarg($plugin_dir) . " && git remote set-url origin " . escapeshellarg($repo_url);
                if (!agpi_run_command($cmd, $out, $code)) {
                    error_log($log_prefix . ' | WARNUNG: ' . agpi_format_command_error('Remote-URL zurücksetzen fehlgeschlagen.', $out, $code, $access_token));
                }
            }
        };

        /**
         * Führt einen Git-Schritt im Plugin-Verzeichnis aus, protokolliert ihn
         * (Kurzform für die Oberfläche + Error-Log) und beendet die Anfrage mit
         * einer sprechenden Fehlermeldung, wenn der Schritt fehlschlägt.
         *
         * Zugangsdaten werden vor jeder Ausgabe entfernt: Solange die Remote-URL
         * den Token enthält, taucht er sonst in Git-Fehlermeldungen auf und damit
         * im Backend und im Error-Log.
         */
        $run_step = function ($label, $git_command, $failure_message)
            use ($plugin_dir, $access_token, $log_prefix, &$debug_steps, $reset_url) {

            $command = "cd " . escapeshellarg($plugin_dir) . " && " . $git_command;
            $ok = agpi_run_command($command, $out, $code);

            $detail = agpi_redact_credentials(
                trim(implode(' | ', array_filter(array_map('trim', $out)))),
                $access_token
            );

            $debug_steps[] = $label . ' — Exit ' . $code . ($detail !== '' ? ': ' . $detail : '');
            error_log($log_prefix . ' | ' . $label . ' — Exit ' . $code . ($detail !== '' ? ': ' . $detail : ''));

            if (!$ok) {
                $reset_url();
                error_log($log_prefix . ' | FEHLER bei ' . $label);
                wp_send_json_error([
                    'message' => $failure_message,
                    'debug'   => implode("\n", $debug_steps),
                ]);
            }
        };

        if (!empty($version)) {
            // --- Sync to specific version/tag ---
            $run_step(
                'git fetch --all --tags',
                'git fetch --all --tags',
                'Synchronisierung fehlgeschlagen beim Abrufen der Änderungen (git fetch).'
            );
            $run_step(
                'git checkout -f ' . $version,
                'git checkout -f ' . escapeshellarg($version),
                'Synchronisierung fehlgeschlagen beim Wechsel zur Version "' . $version . '" (git checkout).'
            );
            $run_step(
                'git clean -fd',
                'git clean -fd',
                'Synchronisierung fehlgeschlagen beim Bereinigen lokaler Dateien (git clean).'
            );
        } else {
            // --- Sync to default branch ---

            // Standard-Branch ermitteln. Wird direkt beim Remote per
            // "git ls-remote --symref" erfragt, statt aus dem lokal
            // gecachten refs/remotes/origin/HEAD gelesen zu werden: Dieser
            // lokale Cache wird nur beim ursprünglichen Klonen gesetzt und
            // von "git fetch" NICHT automatisch aktualisiert. Wurde der
            // Standard-Branch auf GitHub zwischenzeitlich gewechselt (auch
            // hin und wieder zurück), zeigt der Cache sonst dauerhaft auf den
            // alten Branch-Namen - schlägt dieser danach fehl (z. B. weil er
            // umbenannt oder gelöscht wurde), bricht die Synchronisierung ab.
            // Liefert der Befehl nichts (z. B. Netzwerkproblem), wird "main"
            // angenommen; schlägt der anschließende Checkout deshalb fehl,
            // steht der ermittelte Name in den Debug-Details.
            exec("cd " . escapeshellarg($plugin_dir) . " && git ls-remote --symref origin HEAD 2>/dev/null | sed -n 's@^ref:[[:space:]]*refs/heads/\\([^[:space:]]*\\)[[:space:]]*HEAD@\\1@p'", $branch_out, $branch_code);
            $default_branch = !empty($branch_out) ? trim($branch_out[0]) : 'main';
            $debug_steps[] = 'Ermittelter Standard-Branch: "' . $default_branch . '"';
            error_log($log_prefix . ' | Standard-Branch: ' . $default_branch);

            $run_step(
                'git fetch origin',
                'git fetch origin',
                'Synchronisierung fehlgeschlagen beim Abrufen der Änderungen (git fetch).'
            );
            $run_step(
                'git checkout -f ' . $default_branch,
                'git checkout -f ' . escapeshellarg($default_branch),
                'Synchronisierung fehlgeschlagen beim Wechsel zum Branch "' . $default_branch . '" (git checkout).'
            );
            $run_step(
                'git reset --hard origin/' . $default_branch,
                'git reset --hard origin/' . escapeshellarg($default_branch),
                'Synchronisierung fehlgeschlagen beim Zurücksetzen auf origin/' . $default_branch . ' (git reset --hard).'
            );
            $run_step(
                'git clean -fd',
                'git clean -fd',
                'Synchronisierung fehlgeschlagen beim Bereinigen lokaler Dateien (git clean).'
            );
        }

        // Reset remote URL to original (without token) for security
        $reset_url();

        // Remove gitignored files/directories if option is enabled
        if (!empty($project['respect_gitignore'])) {
            apply_gitignore_cleanup($plugin_dir);
        }

        // Update last_synced timestamp
        $project['last_synced'] = current_time('mysql');
        $projects[$project_id] = $project;
        update_option('github_installer_projects', $projects);

        error_log($log_prefix . ' | Synchronisierung erfolgreich.');
        wp_send_json_success('Projekt erfolgreich synchronisiert!');
    } catch (Exception $e) {
        error_log($log_prefix . ' | EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        wp_send_json_error([
            'message' => 'Unerwarteter Fehler bei der Synchronisierung: ' . $e->getMessage(),
            'debug'   => $e->getFile() . ':' . $e->getLine(),
        ]);
    }
}

function get_github_project() {
    check_ajax_referer('github_installer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions.');
    }

    $project_id = sanitize_text_field($_POST['project_id']);
    $projects = get_saved_projects();

    if (!isset($projects[$project_id])) {
        wp_send_json_error('Projekt nicht gefunden.');
    }

    wp_send_json_success($projects[$project_id]);
}

function update_github_project() {
    check_ajax_referer('github_installer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions.');
    }

    $project_id = sanitize_text_field($_POST['project_id']);
    $projects = get_saved_projects();

    if (!isset($projects[$project_id])) {
        wp_send_json_error('Projekt nicht gefunden.');
    }

    // Get updated project data
    $updated_data = array(
        'id' => $project_id,
        'name' => sanitize_text_field($_POST['name']),
        'repo_url' => esc_url_raw($_POST['repo_url']),
        'is_private' => !empty($_POST['is_private']) && $_POST['is_private'] !== 'false' && $_POST['is_private'] !== '0',
        'version' => sanitize_text_field($_POST['version']),
        'respect_gitignore' => !empty($_POST['respect_gitignore']) && $_POST['respect_gitignore'] !== 'false' && $_POST['respect_gitignore'] !== '0',
        'last_synced' => $projects[$project_id]['last_synced'] // Keep the existing last_synced time
    );

    // Handle access token - only update if a new one is provided
    if (!empty($_POST['access_token'])) {
        $updated_data['access_token'] = sanitize_text_field($_POST['access_token']);
    } else {
        $updated_data['access_token'] = $projects[$project_id]['access_token'];
    }

    // Validate required fields
    if (empty($updated_data['name']) || empty($updated_data['repo_url'])) {
        wp_send_json_error('Name und Repository URL sind erforderlich.');
    }

    // Update the project
    $projects[$project_id] = $updated_data;
    update_option('github_installer_projects', $projects);

    wp_send_json_success(array('message' => 'Projekt erfolgreich aktualisiert!', 'project' => $updated_data));
}
