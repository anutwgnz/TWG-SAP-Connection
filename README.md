# Quickstart

## New Plugin Setup

```bash
# Clone boilerplate
git clone https://github.com/The-Web-Guys/twg-plugin-master-template.git your-plugin-name
cd your-plugin-name

# Disconnect from boilerplate repo
rm -rf .git

# Initialize fresh repo
git init
git add .
git commit -m "Initial commit from boilerplate"

# Create new repo on GitHub, then:
git remote add origin git@github.com:your-org/your-plugin-name.git
git push -u origin main
```

## Replacements

When starting a new plugin, replace:

| Find | Replace with | Example |
|------|--------------|---------|
| `twg-plugin-name` | your-plugin-slug | `acme-forms` |
| `twg_plugin_name` | your_plugin_slug | `acme_forms` |
| `TwgPluginName` | YourPluginNamespace | `AcmeForms` |
| `TWG_PLUGIN_NAME` | YOUR_PREFIX | `ACME_FORMS` |
| `twg/plugin-name` | vendor/package | `acme/forms` |

Files to rename:
- `twg-plugin-name.php` → `your-plugin-slug.php`

Then:

```bash
composer install
git add .
git commit -m "Configure plugin"
git push
```

## Structure

```
twg-plugin-name.php   # Bootstrap file (entry point)
uninstall.php         # Cleanup on plugin deletion
composer.json         # Dependency management and PSR-4 autoloading

includes/             # All PHP classes (PSR-4 autoloaded)
  Config.php          # Plugin constants (slug, API URL, etc.)
  Plugin.php          # Main orchestrator
  Activator.php       # Runs on plugin activation
  Deactivator.php     # Runs on plugin deactivation
  Cron/               # Scheduled tasks
    WpCron.php        # WP-Cron (pseudo-cron, runs on page load)
    WpCli.php         # WP-CLI commands (for real Linux cron)
  Admin/              # WP Admin (WordPress Admin Dashboard) specific code
    Admin.php         # Hooks into admin_enqueue_scripts
  Frontend/           # Public-facing (front-end) code
    Frontend.php      # Hooks into wp_enqueue_scripts
  Api/                # 3rd party integrations

assets/               # Static files (CSS, JS, images)
  admin/              # Admin assets
    css/
    js/
  public/             # Frontend assets
    css/
    js/
```

## PSR-4 Autoloading

PSR-4 maps namespaces to directories. `TwgPluginName\Admin\Admin` loads from `includes/Admin/Admin.php`.

Benefit: no manual `require_once` per class. Add new classes under `includes/`, they load automatically.

## Deployment Setup

### GitHub Environments

Create two environments in repo Settings > Environments:
- `development` (deploys from `develop` branch)
- `production` (deploys from `main` branch)

### Secrets (per environment)

| Secret | Value |
|--------|-------|
| `SSH_KEY` | Private key for cPanel SSH access |
| `SSH_USER` | cPanel username |
| `DEVELOPMENT_IP` | Dev server IP (development env only) |
| `PRODUCTION_SH_02_IP` | Prod server IP (production env only) |

### Variables (per environment)

| Variable | Value |
|----------|-------|
| `PLUGIN_FOLDER` | Full path to plugin, e.g. `/home/user/public_html/wp-content/plugins/your-plugin-name` |

### cPanel SSH Setup

1. Generate key locally (no passphrase): `ssh-keygen -t rsa -b 4096 -N ""`
2. cPanel > Security > SSH Access > Import Key (paste public key)
3. Authorize the imported key
4. Add private key to GitHub secrets as `SSH_KEY`

### Server Requirements

Composer must be available on the server. After first deploy, SSH in and run:

```bash
cd /path/to/plugin
composer install --no-dev
```
