# Upgrade Glyph

Glyph is designed so you can update the application without reinstalling your site.

## Before You Upgrade

Before applying an update:

1. Create a fresh backup from `Admin -> System`.
2. Confirm the site is healthy and writable.
3. Review the release notes for any migration or compatibility notes.
4. If you use a custom theme or plugin, make sure it is compatible with the new version.

## Preserved Runtime Paths

Glyph intentionally preserves site runtime data during updates.

Do not wipe these paths when upgrading:

- `content/`
- `data/`
- `uploads/`

Those directories contain your posts, pages, media, users, categories, sessions, cache, and other site-specific data.

## Upgrade Methods

### Manual File Upgrade

Use this method when updating from a release ZIP by hand.

1. Download the new Glyph release package.
2. Back up the existing site.
3. Replace the shipped application files with the new version.
4. Keep your existing runtime data in place:
   - `content/`
   - `data/`
   - `uploads/`
5. Load the site and sign in to the admin area.
6. Go to `System` and run any pending migrations if needed.

### Admin Package Apply

Glyph also supports applying a local update package from `Admin -> System`.

That updater flow is designed to:

- validate the package structure first
- create a backup before applying the update
- preserve `content/`, `data/`, and `uploads/`
- replace shipped application files
- record the last applied package state

## What To Check After Upgrading

After any upgrade, verify:

- frontend pages load correctly
- admin login still works
- content editing and autosave work
- media uploads work
- the active theme renders correctly
- plugins still behave as expected
- `System` diagnostics and version state look healthy

## Rollback

If something goes wrong:

1. Restore the backup created before the update.
2. Put the previous application files back in place.
3. Re-check file permissions and runtime directories.
4. Review the release notes before trying again.

## Notes

If you are upgrading a production site, test the new release on a copy or staging environment first whenever possible.
