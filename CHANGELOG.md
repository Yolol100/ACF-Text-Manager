# Changelog

## 2.5.24

- Cleanup: rebuilt bundled MO translation catalogs from the current POT so removed import-preview messages are no longer shipped in binary language files.
- Verification: reran syntax, CSS, dead-code, language-file and package checks.

## 2.5.22

- Cleanup: removed stale CSS selectors for old close/progress class names and switched progress percent styling to the live data-attribute selector.

## 2.5.21
- Cleanup: removed dead auto-submit picker handling from the target selector.
- Cleanup: removed duplicate export no-selection handling so only the checklist controller owns that validation.
- Verification: reran PHP, JS, CSS, duplicate-code, dead-code and package checks.

## 2.5.20

- Cleanup: removed unused generic tab-jump selector handling that no rendered markup uses.
- Cleanup: removed unused picker open-link support and dead `data-edit-url` attributes from the target dropdown.
- Cleanup: removed unused multi-select picker branches because export now uses its own checklist UI.
- Cleanup: removed a dead `clear-visible` JavaScript branch with no triggering control.
- Verification: reran PHP, JS, CSS, package, duplicate-code and dead-code checks.

## 2.5.19

- Cleanup: removed unused import preview-row payload generation after the separate preview UI was removed.
- Cleanup: removed private import preview text helpers that no longer had runtime callers.
- Docs: aligned README usage text with the current three-tab UI and single-action import flow.
- Verification: reran strict package, syntax, duplicate-code, CSS, and security-boundary checks after cleanup.

## 2.5.16

- Maintenance: removed a stale orphan docblock left after import target detection was moved into a service.
- Maintenance: consolidated filename-to-target candidate normalization in `Import_Target_Detector`.
- Verified PHP, JS, CSS, package hygiene, and duplicate-code scans after cleanup.

## 2.5.15

- Maintenance: consolidated duplicate import target matching logic.
- Maintenance: consolidated duplicate ZIP entry validation logic used by validation and extraction.
- Maintenance: consolidated repeated ACF field-group traversal and flexible-content child traversal.
- Maintenance: consolidated repeated upgrade cleanup finalization.
- Fix: field-filter toolbar CSS no longer applies button styling to the container.
- CSS: shared import file-shell form-surface styling with the existing input/select rule to avoid duplicate visual declaration blocks.
- Kept runtime behavior unchanged; this is a cleanup release.

## 2.5.13

- Maintenance: consolidated duplicate temporary upload cleanup into `Temp_File_Service::delete_upload_files()`.

## 2.5.12

- Maintenance: consolidated duplicate rollback download URL generation into `Import_Plan_Store::build_rollback_download_url()`.
- Verified remaining repeated CSS selectors are responsive-state overrides, not accidental cascade conflicts.

## 2.5.11

- CSS: removed unused legacy/product-status selectors that were no longer rendered by PHP or JavaScript.
- CSS: consolidated the import file-shell styling into one authoritative rule instead of inheriting from the generic input group and overriding it later.
- CSS: removed unused helper selectors so the stylesheet only targets live markup and dynamic JavaScript elements.
- Cache: bumped the plugin version so the cleaned admin CSS is loaded reliably.

## 2.5.5

- Safety: physical media filename renames are disabled by default at code level via `wa_acf_ptm_allow_media_file_rename`; enable intentionally in project code after staging validation.
- Compatibility: removed the hard `Requires Plugins` header so ACF Pro-only installations can rely on the existing runtime dependency check.
- Hardening: separate multi-file imports are now capped via `wa_acf_ptm_max_import_upload_files` before files are stored or expanded.
- Hardening: WP-CLI imports only allow physical media filename renames when `--confirm-media-rename` is explicitly supplied.
- Improvement: bulk export is available from both Pages and Posts list tables.
- Docs: added `CHANGELOG.md` and `SECURITY.md` for private/premium distribution and maintenance.
- i18n: removed the stale export-toggle strings from the bundled `.mo` files as well as the `.pot` template.
- Source hygiene: added `.editorconfig`, `.gitignore`, `composer.json`, `phpcs.xml`, GitHub issue/PR templates, and a CI workflow for source repositories. These files are intended for the source package, not the lean runtime distribution zip.
- Release hygiene: aligned `README.md`, `readme.txt`, and plugin metadata to version 2.5.5.
