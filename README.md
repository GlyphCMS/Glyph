<p align="center">
  <img src="https://github.com/GlyphCMS/Glyph/blob/main/assets/branding/glyph-app-icon-512.png?raw=true" alt="Glyph Logo" width="128" height="128">
</p>

<h1 align="center">Glyph</h1>

<p align="center">
  Glyph is a lightweight flat-file CMS written in pure PHP for bloggers, hobbyists, and small self-hosted websites.
</p>

<p align="center">
  <a href="https://glyphcms.com"><strong>Website</strong></a>
  ·
  <a href="https://demo.glyphcms.com"><strong>View Demo</strong></a>
  ·
  <a href="INSTALL.md"><strong>Installation Guide</strong></a>
  ·
  <a href="UPGRADE.md"><strong>Upgrade Guide</strong></a>
</p>

<p align="center">
  <a href="https://www.patreon.com/c/GlyphCMS">
    <img src="https://img.shields.io/badge/Support-Patreon-F96854?logo=patreon&logoColor=white" alt="Support on Patreon">
  </a>
  <a href="LICENSE">
    <img src="https://img.shields.io/badge/License-GPLv3-blue.svg" alt="License: GPLv3">
  </a>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/status-public_beta-orange" alt="Status: Public Beta">
  <a href="https://demo.glyphcms.com">
    <img src="https://img.shields.io/badge/Demo-Live%20Preview-0ea5e9" alt="Live Demo">
  </a>
  <!--<a href="https://github.com/GlyphCMS/Glyph/releases">
    <img src="https://img.shields.io/github/v/release/GlyphCMS/Glyph" alt="Latest Release">
  </a>-->
  <a href="https://github.com/GlyphCMS/Glyph/stargazers">
    <img src="https://img.shields.io/github/stars/GlyphCMS/Glyph?style=flat" alt="GitHub Stars">
  </a>
  <a href="https://github.com/GlyphCMS/Glyph/issues">
    <img src="https://img.shields.io/github/issues/GlyphCMS/Glyph" alt="GitHub Issues">
  </a>
</p>

## Why Glyph

Glyph is built to keep publishing approachable.

It is designed to be simple to install, straightforward to manage, and flexible enough to grow, without requiring a database, Composer, or a CLI-first workflow.

### Highlights

- Flat-file content storage with no database required
- Pure PHP runtime built for shared hosting and simple deployments
- Browser-based installer and admin panel
- Posts, pages, media uploads, and custom navigation
- Themes, plugins, and role-based user management
- Category support with clean public URLs
- Backup, maintenance, diagnostics, and update tooling
- Upgrade-safe runtime data paths for safer releases

## Demo

Want to see how Glyph works before installing it?

**Live demo:** [https://demo.glyphcms.com](https://demo.glyphcms.com)

## Current Status

Glyph is currently in its first public beta.

The core publishing workflow is already usable today, and the project will continue to receive polish, UX improvements, and ecosystem expansion over time.

## Requirements

- PHP 8.3 or newer
- Apache or Nginx with PHP-FPM
- Pretty URLs recommended

### Required PHP extensions

- `json`
- `mbstring`
- `fileinfo`
- `openssl`

### Recommended PHP extensions

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

A typical Glyph install keeps application code separate from runtime content:

- `src/` — application code
- `themes/` — frontend themes
- `plugins/` — plugins and extensions
- `content/` — posts and pages
- `data/` — categories, users, cache, sessions, and system data
- `uploads/` — uploaded media
- `storage/` — logs and internal runtime storage

This separation helps make manual upgrades safer and easier.

## Documentation

- [INSTALL.md](INSTALL.md) — installation and first-run setup
- [UPGRADE.md](UPGRADE.md) — upgrading an existing site

## Support Glyph

If you would like to support Glyph’s development, you can do so here:

- [Patreon](https://www.patreon.com/c/GlyphCMS)

## License

GPL-3.0-only
