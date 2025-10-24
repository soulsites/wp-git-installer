# GitHub Plugin Installer for WordPress

## Description

The GitHub Plugin Installer is a powerful WordPress plugin that allows administrators to install and update WordPress plugins directly from GitHub repositories. This tool bridges the gap between GitHub and WordPress, making it easier for developers and site managers to use custom or third-party plugins hosted on GitHub.

## Target Audience

This plugin is designed for:

- WordPress administrators who want to easily install plugins from GitHub
- Developers who maintain their plugins on GitHub and want an easy way to install/update them on WordPress sites
- Site managers who need to use custom plugins not available in the WordPress plugin directory

## Features

- **Multi-Project Support**: Save and manage multiple GitHub projects in one place
- **Project Cards**: Visual card-based interface showing all your saved projects
- **One-Click Sync**: Synchronize any saved project to pull the latest version from Git
- Install plugins directly from public or private GitHub repositories
- Update existing plugins installed from GitHub
- Preview repository contents before installation
- Select specific versions (tags) of a plugin to install
- Support for private repositories using GitHub Personal Access Tokens
- Automatic plugin folder naming based on the repository name
- Track last synchronization time for each project

## Installation

1. Download the plugin zip file or clone the repository into your WordPress plugins directory.
2. Navigate to the WordPress admin panel and go to Plugins > Installed Plugins.
3. Find "GitHub Plugin Installer" in the list and click "Activate".

## Usage

### Installing a New Plugin

1. In the WordPress admin panel, go to Plugins > GitHub Installer.
2. Enter a project name (optional) to save the project for later use.
3. Enter the GitHub repository URL of the plugin you want to install.
4. If it's a private repository, check the "Private Repository?" box and enter your GitHub Personal Access Token.
5. The plugin will fetch available versions and provide a preview of the repository contents.
6. Select the version you want to install from the dropdown menu.
7. Click "Install/Update Plugin" to proceed with the installation or update.
8. Optionally, click "Als Projekt speichern" (Save as Project) to save this configuration for future use.

### Managing Saved Projects

Once you've saved projects, they will appear as cards below the installation form:

- **View Project Details**: Each card shows the project name, repository URL, version, privacy status, and last sync time.
- **Synchronize**: Click the "Synchronisieren" button to pull the latest version from Git for that specific project.
- **Delete**: Click the "Löschen" button to remove a saved project from your list (this won't uninstall the plugin, just removes it from the saved projects).

### Synchronizing Projects

The synchronization feature allows you to quickly update any saved project:

1. Find the project card you want to sync.
2. Click the "Synchronisieren" (Synchronize) button.
3. The plugin will automatically fetch the latest version from Git and update the plugin.
4. You'll see a status message indicating success or failure.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Git installed on the server
- `exec()` function enabled on the server (for Git operations)

## Configuration

No additional configuration is required after installation. However, for private repositories, you will need to generate a GitHub Personal Access Token:

1. Go to GitHub Settings > Developer settings > Personal access tokens
2. Click "Generate new token"
3. Give your token a descriptive name
4. Select the 'repo' scope for full access to private repositories
5. Click "Generate token" at the bottom of the page
6. Copy the token and use it in the plugin when prompted

## Security Considerations

- The plugin uses nonces and capability checks to ensure only authorized users can install plugins.
- **Personal Access Tokens are stored in the WordPress database** when you save a project. Ensure your WordPress installation is secure.
- Only users with the `manage_options` capability (typically administrators) can access the plugin.
- Always review the contents of a repository before installing to ensure it's from a trusted source.
- Saved projects and their tokens are stored using WordPress options API with proper sanitization.

## Limitations

- The plugin requires Git to be installed on the server.
- It may not work on certain shared hosting environments where `exec()` is disabled.
- Large repositories may take longer to clone or update.

## Troubleshooting

If you encounter issues:

1. Ensure Git is installed on your server and accessible to PHP.
2. Check if the `exec()` function is enabled in your PHP configuration.
3. Verify you have the correct permissions for the WordPress plugins directory.
4. For private repositories, ensure your Personal Access Token has the correct permissions.

## Contributing

Contributions to the GitHub Plugin Installer are welcome! Please feel free to submit pull requests or create issues for bugs and feature requests.

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support, please open an issue on the GitHub repository or contact the plugin maintainer.

---

We hope this plugin simplifies your WordPress plugin management workflow. Happy coding!
