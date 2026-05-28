# ACF Page Text Manager

Manage Advanced Custom Fields (ACF), Yoast SEO, Rank Math, image metadata, and page/post title and excerpt fields from a single WordPress admin screen — with CSV/XLSX import and export.

**Version:** 2.5.25

Latest update: hardened import caps, refreshed metadata, and reran package checks.

**License:** GPL-2.0-or-later
**Requires:** WordPress 6.5+, PHP 8.0+, Advanced Custom Fields (Free or Pro)
**Tested up to:** WordPress 7.0

---

## What it does

ACF Page Text Manager adds a focused **Tekstbeheer** menu to the WordPress admin where you can:

- Browse and inline-edit text-oriented fields for any selected page or post — ACF fields, Yoast SEO metadata, Rank Math metadata, image alt/caption/description, and the post's own title and excerpt.
- Export the values for one or many items to CSV or XLSX.
- Import a CSV/XLSX/ZIP back in with validation and progress feedback before writes are processed.

It is not a general-purpose migration suite. The scope is deliberately limited to text content for pages and posts, which keeps the workflow predictable and the import safe.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` (or upload the `.zip` via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin in **Plugins**.
3. Make sure Advanced Custom Fields (Free or Pro) is installed and active.
4. Open **Tekstbeheer** in the WordPress admin sidebar.

## Usage

The admin screen is organised in three tabs:

- **Veldinhoud** — pick a page or post and inline-edit its fields.
- **Export** — select one or more items and download CSV or XLSX.
- **Import** — upload a CSV/XLSX/ZIP file and run the validated import.

A few practical notes:

- Inline edits autosave when you click outside the field; press `Ctrl+Enter` (`Cmd+Enter` on macOS) inside a field to save explicitly, or `Esc` to cancel.
- Media filename changes are an opt-in path and require explicit "Save" — they are never applied implicitly.
- Imports are validated/prepared before processing, then the confirmed import runs through the progress modal.

## Compatibility

| Component | Minimum | Tested |
|---|---|---|
| WordPress | 6.5 | 7.0 |
| PHP | 8.0 | 8.3 |
| ACF | 6.7.2 | 6.7.x |

The plugin checks for ACF at runtime and shows a clear notice if it is missing or outdated. ACF Pro and ACF Free are both supported.

## Privacy

The plugin stores its own settings in `wp_options` (`wa_acf_ptm_settings`, `wa_acf_ptm_media_rename_log`) and uses short-lived transients for in-progress import plans. Uploaded files are processed in WordPress' temp uploads directory and removed at the end of the import.

The plugin does not phone home, does not load remote assets, and does not track usage.

## Uninstall

Removing the plugin via WordPress' "Delete" action runs `uninstall.php`, which removes the plugin's options and transients for the active site (and, on multisite, for every subsite). It does not touch ACF data or any page/post content.

## Changelog

## 2.5.21

- Cleanup: removed dead auto-submit picker handling from the target selector.
- Cleanup: removed duplicate export no-selection handling so only the checklist controller owns that validation.

## 2.5.20

- Cleanup: removed unused generic tab-jump selector handling.
- Cleanup: removed unused picker open-link support and dead `data-edit-url` attributes.
- Cleanup: removed unused multi-select picker branches and a dead `clear-visible` action branch.

## 2.5.19

- Cleanup: removed unused import preview-row payload generation after the separate preview UI was removed.
- Cleanup: removed private import preview text helpers that no longer had runtime callers.
- Docs: aligned README usage text with the current three-tab UI and single-action import flow.

See [readme.txt](readme.txt) for the full version history.

## License

This plugin is released under the GNU General Public License v2.0 or later. See [LICENSE](LICENSE) for the full text.

Copyright © Webactueel — https://www.webactueel.nl/


## WP-CLI media rename safety

Physical media filename changes during CLI imports are disabled by default. Add `--confirm-media-rename` only after reviewing the dry-run output and confirming the import is intended to rename media files.


## Media filename rename safety

Physical media filename renames are disabled by default at code level through `wa_acf_ptm_allow_media_file_rename`. Enable this only in project code after staging validation, for example:

```php
add_filter( 'wa_acf_ptm_allow_media_file_rename', '__return_true' );
```

WP-CLI imports also require `--confirm-media-rename` before physical filename renames are allowed.


## 2.5.25

- Hardening: stricter import caps for ZIP and separate upload files.
- Hardening: safer WP-CLI XLSX temp cleanup.
- Release: bumped plugin metadata for the hardened build.


- Removed an unused export picker data attribute.
- Added shipped documentation files to the upgrade cleanup manifest.
