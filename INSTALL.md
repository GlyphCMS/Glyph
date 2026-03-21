# Install Glyph

## Requirements

Before installing, make sure your host provides:

- PHP 8.3 or newer
- Apache with rewrite support, or Nginx with front-controller routing
- Required PHP extensions:
  - `json`
  - `mbstring`
  - `fileinfo`
  - `openssl`
- Recommended PHP extensions:
  - `zip` for backups, updates, and ZIP-based theme/plugin installs
  - `apcu` for faster caching

Glyph does not require a database.

## Writable Directories

Glyph needs writable runtime storage for its content and generated data.

At minimum, make sure the parent directories for these paths are writable by PHP:

- `content/`
- `data/`
- `storage/`
- `uploads/`

In most shared-hosting setups, that means the web server user must be able to create and update files inside those directories.

## Install Steps

1. Upload the Glyph files to your web root.
2. Confirm the files extracted correctly.
3. Visit `/install` in your browser.
4. Complete the installer form:
   - site name
   - site URL
   - admin email
   - password
   - password confirmation
   - cache driver
5. Submit the installer.
6. After installation finishes, visit `/login` and sign in.

If the installer detects a server problem, fix that first and reload the page.

## Recommended First Checks

After signing in, review these areas first:

- `Settings`
  - site name and URL
  - timezone
  - date and time formats
  - meta description
  - social image
  - email configuration
- `System`
  - diagnostics
  - maintenance mode
  - backups
  - update settings
- `Content`
  - create a test post
  - create a test page
- `Media`
  - upload a test image
- `Themes`
  - confirm the active theme is rendering correctly

## Web Server Notes

### Apache

Glyph ships with an `.htaccess` file for rewrite-based routing.

### Nginx

Use a standard front-controller setup that routes unknown requests to `index.php` while still serving real files directly.

## Shared Hosting Notes

- `apcu` is optional, not required.
- `zip` is strongly recommended if you want browser-based backups, updates, and ZIP package installs.
- A real `site_url` is important for canonical URLs and social metadata.
- SMTP is generally more reliable than basic PHP mail for password reset and other email delivery.

## After Install

Once installed, Glyph stores runtime content and site data in its writable directories so future upgrades can replace the application files without requiring a reinstall.

For update guidance, see [UPGRADE.md](UPGRADE.md).
