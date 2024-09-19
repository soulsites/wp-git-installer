# GitHub Plugin Installer for WordPress

## Description

The GitHub Plugin Installer is a powerful WordPress plugin that allows administrators to install and update WordPress plugins directly from GitHub repositories. This tool bridges the gap between GitHub and WordPress, making it easier for developers and site managers to use custom or third-party plugins hosted on GitHub.

## Target Audience

This plugin is designed for:

- WordPress administrators who want to easily install plugins from GitHub
- Developers who maintain their plugins on GitHub and want an easy way to install/update them on WordPress sites
- Site managers who need to use custom plugins not available in the WordPress plugin directory

## Features

- Install plugins directly from public or private GitHub repositories
- Update existing plugins installed from GitHub
- Preview repository contents before installation
- Select specific versions (tags) of a plugin to install
- Support for private repositories using GitHub Personal Access Tokens
- Automatic plugin folder naming based on the repository name

## Installation

1. Download the plugin zip file or clone the repository into your WordPress plugins directory.
2. Navigate to the WordPress admin panel and go to Plugins > Installed Plugins.
3. Find "GitHub Plugin Installer" in the list and click "Activate".

## Usage

1. In the WordPress admin panel, go to Plugins > GitHub Installer.
2. Enter the GitHub repository URL of the plugin you want to install.
3. If it's a private repository, check the "Private Repository?" box and enter your GitHub Personal Access Token.
4. The plugin will fetch available versions and provide a preview of the repository contents.
5. Select the version you want to install from the dropdown menu.
6. Click "Install/Update Plugin" to proceed with the installation or update.

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
- Personal Access Tokens are not stored by the plugin and must be entered each time for private repositories.
- Always review the contents of a repository before installing to ensure it's from a trusted source.

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
