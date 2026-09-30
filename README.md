# ACF Page Text Manager

> **Portfolio project · WordPress/PHP · ACF · Yoast/Rank Math · CSV/XLSX · guarded content workflows**

ACF Page Text Manager is a WordPress admin plugin for reviewing and editing structured page/post content, supported ACF values, Yoast SEO and Rank Math metadata, image text and core post fields from one controlled interface.

It also provides validated CSV/XLSX import and export workflows for bulk content work.

**Developer profile:** [Andrew Baeten](https://github.com/Yolol100) · [Portfolio cases](https://andrewbaeten.nl/category/cases)

## What problem it solves

Structured WordPress content is often spread across the editor, ACF, SEO plugins and media fields. ACF Page Text Manager brings those supported text fields into one reviewable admin workflow and keeps bulk writes behind validation and explicit confirmation.

## Portfolio snapshot

| Area | What it demonstrates |
| --- | --- |
| WordPress | Admin tooling for pages and posts |
| Structured content | Dynamic ACF field discovery and editing |
| SEO workflows | Supported Yoast SEO and Rank Math metadata |
| Bulk operations | Validated CSV/XLSX export and import |
| Media | Alt text, captions, descriptions and guarded filename workflows |
| Safety | Capability checks, validation, temporary state and explicit media-rename opt-in |

## Main capabilities

- Browse and edit supported text-oriented fields for pages and posts.
- Discover supported ACF fields dynamically.
- Edit Yoast SEO and Rank Math metadata where those plugins are available.
- Manage supported image alt text, captions, descriptions and guarded filename workflows.
- Export one or more content items to CSV or XLSX.
- Validate and prepare CSV/XLSX/ZIP imports before applying writes.
- Process confirmed imports with progress feedback and bounded temporary state.
- Keep physical media filename changes behind explicit opt-in safety controls.

The plugin is intentionally focused on structured content editing rather than acting as a general-purpose migration suite.

## Requirements

- WordPress 6.5 or newer.
- PHP 8.0 or newer.
- Advanced Custom Fields 6.7.2 or newer (Free or Pro) for ACF-field workflows.

Current plugin version: `2.5.25`.

The plugin header is tested through WordPress 7.1. See `readme.txt` for the complete release history and current distribution metadata.

## Verification

The repository includes a GitHub Actions WordPress compatibility workflow that:

- runs PHP syntax checks;
- creates clean WordPress 6.5/PHP 8.0 and WordPress 7.1/PHP 8.3 runtimes;
- installs and activates ACF;
- activates ACF Page Text Manager in a real WordPress admin context;
- verifies that the admin module registers correctly;
- fails when the runtime debug log contains PHP fatal errors, parse errors, warnings, deprecations or notices.

This is runtime/bootstrap evidence rather than a full unit-test suite. Higher-risk media filename changes remain staging-first.

## Installation

1. Upload the plugin folder or ZIP through **Plugins → Add New → Upload Plugin**.
2. Activate **ACF Page Text Manager**.
3. Make sure Advanced Custom Fields 6.7.2 or newer is installed and active for ACF workflows.
4. Open **Tekstbeheer** in the WordPress admin sidebar.

## Usage

The admin workflow is organized around three primary areas:

- **Veldinhoud** — select a page or post and edit supported fields.
- **Export** — export selected items to CSV or XLSX.
- **Import** — upload and validate CSV/XLSX/ZIP input before processing it.

Inline content edits and bulk imports remain separate operations. Media filename changes require an explicit safe path and should be tested on staging before being enabled for production content.

## Media rename safety

Physical filename changes are disabled by default through `wa_acf_ptm_allow_media_file_rename`. Enable them only after project-specific staging validation.

```php
add_filter( 'wa_acf_ptm_allow_media_file_rename', '__return_true' );
```

WP-CLI imports additionally require `--confirm-media-rename` before physical filename changes are allowed.

## Privacy and data handling

The plugin stores its own settings in WordPress options and uses temporary state for in-progress imports. Uploaded import files are processed through the WordPress temporary upload path and cleaned up by the plugin workflow.

The plugin does not need to send content to an external service for its core editing/import/export functionality.

## Repository structure

- `acf-page-text-manager.php` — plugin bootstrap and metadata.
- `includes/` — application, import/export, field and integration logic.
- `assets/` — admin assets.
- `languages/` — translations.
- `uninstall.php` — plugin-owned cleanup.
- `readme.txt` — WordPress distribution metadata and full changelog.

## About the developer

I am **Andrew Baeten**, a WordPress Developer with 10+ years of experience and **70+ delivered projects**. My work combines WordPress, WooCommerce, Elementor, ACF, UX, performance, technical SEO and quality-focused delivery.

[Portfolio cases](https://andrewbaeten.nl/category/cases) · [LinkedIn](https://www.linkedin.com/in/andrew-baeten-305a1478/) · [Email](mailto:info@andrewbaeten.nl)

## License

GPL-2.0-or-later. See `LICENSE` and the plugin metadata.