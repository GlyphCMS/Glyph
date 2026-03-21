# Glyph

Glyph is a lightweight flat-file CMS written in pure PHP for bloggers, hobbyists, and small self-hosted websites.

It is designed to feel simple to install, straightforward to manage, and flexible to grow without requiring a database, Composer, or a CLI-first workflow.

[![Support on Patreon](https://img.shields.io/badge/Support-Patreon-F96854?logo=patreon&logoColor=white)](https://www.patreon.com/c/GlyphCMS)

## Why Glyph

Glyph aims to keep publishing approachable:

- Flat-file content storage with no database required
- Pure PHP runtime built for shared hosting and simple deployments
- Browser-based installer and admin panel
- Posts, pages, media uploads, and custom navigation
- Themes, plugins, and role-based user management
- Category support with clean public URLs
- Backup, maintenance, diagnostics, and update tooling
- Upgrade-safe runtime data paths for easier releases

## Current Status

Glyph is in its first public beta stage.

The core publishing workflow is in place and usable today, but the project is still evolving and will continue to get polish, UX improvements, and ecosystem expansion over time.

## Requirements

- PHP 8.3 or newer
- Apache or Nginx with PHP-FPM
- Pretty URLs recommended
- Required PHP extensions:
  - `json`
  - `mbstring`
  - `fileinfo`
  - `openssl`
- Recommended PHP extensions:
  - `zip` for backups, updates, and ZIP-based theme/plugin installs
  - `apcu` for faster caching

Glyph works without a database.

## Quick Start

1. Upload Glyph to your web root.
2. Make sure the runtime directories are writable.
3. Visit `/install` in your browser.
4. Complete the installer.
5. Sign in at `/login`.

For full setup instructions, see [INSTALL.md](INSTALL.md).

## Project Structure

A typical Glyph install keeps its application code separate from its runtime content:

- `src/` application code
- `themes/` frontend themes
- `plugins/` plugins and extensions
- `content/` posts and pages
- `data/` categories, users, cache, sessions, and system data
- `uploads/` uploaded media
- `storage/` logs and internal runtime storage

That separation helps make manual upgrades safer and easier.

## Documentation

- [INSTALL.md](INSTALL.md) - installation and first-run setup
- [UPGRADE.md](UPGRADE.md) - upgrading an existing site

## Support Glyph

If you want to support Glyph's development, you can do that here:

- [Patreon](https://www.patreon.com/c/GlyphCMS)

## License

GPL-3.0-only
