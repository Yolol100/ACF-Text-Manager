=== ACF Page Text Manager ===
Contributors: webactueel
Tags: acf, csv, import, export, seo
Requires at least: 6.5
Tested up to: 6.9
Stable tag: 2.3.69
Requires PHP: 8.0
Requires Plugins: advanced-custom-fields
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage ACF fields, Yoast SEO, Rank Math, image metadata, and page/post title and excerpt fields from one admin screen with CSV/XLSX import and export.

== Description ==

ACF Page Text Manager adds a focused admin screen for managing supported ACF fields, Yoast SEO fields, Rank Math fields, image metadata, and page/post title and excerpt fields.

This release is intentionally focused on:

* common WordPress post and term fields
* common SEO metadata
* text-oriented ACF workflows when ACF is active
* common image metadata
* type-aware CSV/XLSX import and export
* safe review before import
* documented ACF field support and release checklist

Included features:

* Type-first workflow for posts and pages
* Right-side field overview for the selected item only
* CSV and XLSX export for one or multiple selected items
* CSV and XLSX import with preview, warnings, and progress feedback
* CSV/XLSX export includes all supported fields for the selected item or items
* Yoast SEO support for common fields such as keyphrase, title, and meta description
* Featured image and common image metadata support
* Keyboard-friendly tabs and compact admin actions

This plugin does **not** aim to be a full catalog, product, term, option-page, or generic custom-field migration suite. It intentionally focuses on ACF, Yoast SEO, Rank Math, image metadata, and basic page/post text fields.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Open the top-level Tekstbeheer menu in the WordPress admin.
4. Ensure Advanced Custom Fields is installed and active; this plugin declares ACF as a dependency.

== FAQ ==

= How do I import data? =

Choose a page or post, open the Import tab, upload one or more CSV/XLSX files, review the preview, and start the import. Unsupported product, option-page, and generic custom-field rows are skipped.


= Which ACF fields are supported? =

Text, textarea, WYSIWYG, email, URL, number, true/false, select, radio, button group, image, file, link, date/time, color, taxonomy, user, post object and relationship fields are the primary supported workflows. Complex group, repeater, flexible content, clone and gallery structures must be tested against the exact site data model. If the preview does not clearly show the intended change, do not run the import.

= Are product catalog imports supported? =

No. Product, product-category, price, stock and catalog imports are intentionally outside the supported scope. Use a dedicated product import workflow for product data.

= What does the import preview show? =

The preview shows how many rows will be updated or skipped, detected files and targets, unmapped columns, and a row-by-row change preview with current and new values.

= Can I import supported fields into custom content types? =

The plugin focuses on the supported built-in flows exposed in the interface. Always test your exact content model on a staging site first.

= Does the plugin show import status? =

Yes. The plugin shows preview feedback before import and progress feedback while the import runs.

= What does this version focus on? =

This version focuses on supported text, SEO, image metadata, and common field-management workflows from one admin screen.

= Where do I get support? =

Please contact Webactueel support or the site maintainer responsible for this plugin release.

== Changelog ==
= 2.3.68 =
* Fixed field-group filters so only provider groups that exist for the selected item are shown.
* Replaced remaining hardcoded Dutch picker/export UI text with localized strings.

= 2.3.66 =
* Fixed remaining JavaScript fallback paths so numeric zero values remain visible after inline saves and in import previews.

= 2.3.65 =
* Fixed custom field export scoping so selected field keys are only applied to the item currently shown in the field panel.
* Preserved numeric zero values when resetting inline editor content from AJAX responses.

= 2.3.64 =
* Compatibility: removed the PHP 8.1-only `never` return type so the plugin matches its declared PHP 8.0 minimum.

= 2.3.62 =
* Release readiness: added plugin, author, and private update URI headers.
* Added an ACF minimum-version admin warning for older ACF installs.
* Added a translation template file for maintainers and translators.
* Improved import safety copy around physical media filename changes.
* Packaged the release inside a top-level plugin folder for cleaner distribution.

= 2.3.60 =
* Hardened media filename rename rollback and reference update logging.
* Made attachment GUID updates opt-in during media filename renames.
* Added stricter validation for media rename reference tables.
* Removed unused internal stubs.
* Improved admin focus styles for keyboard users.

= 2.3.58 =
* Media filename renames now update stored upload URLs for the attachment, generated image sizes, post content, excerpts, and common WordPress meta stores to reduce broken media links.

= 2.3.57 =
* Release metadata: align the internal plugin version constant with the 2.3.57 stable tag.

= 2.3.56 =
* Tighten escaped output for controlled admin template attributes flagged by WPCS-style review.

= 2.3.55 =
* Harden import-preview detection so every image metadata filename change, including featured images, requires explicit media rename confirmation.
* Avoid repointing generated image-size metadata to an existing file during media filename renames.

= 2.3.54 =
* Prevent duplicate admin screen rendering if the page callback is registered more than once by caching or repeated bootstrap edge cases.
* Make admin module registration idempotent for a single request.


= 2.3.53 =
* Performance: lazily boots the admin module only for admin requests, reducing frontend request overhead.
* Performance: WordPress editor assets are only enqueued on the plugin screen after a target is selected, instead of on the initial empty plugin page.

= 2.3.52 =
* Maintenance: shared target permission service is now injected consistently into import processing and admin actions.
* QA: verified that CSV import/export UI is limited to the Import and Export tabs, not the field/translation content area.


= 2.3.51 =
* Architecture cleanup: moved the admin module into the admin namespace and removed the unused modules wrapper from the release package.

= 2.3.50 =
* Maintenance: centralized AJAX JSON error extraction in admin core script.
* Maintenance: reduced duplicate JavaScript response parsing logic in import and inline editor flows.


= 2.3.48 =
* Onderhoudscleanup: gedeelde target-permissielogica gecentraliseerd in een service.
* Ongebruikte kleine traitbestanden en docs uit de release-zip verwijderd.
* Release-package opgeschoond zonder bedoelde functionele import/export-wijzigingen.

= 2.3.42 =
* Fixed an XLSX download header edge case where a failed filesize lookup could send Content-Length: 0.
* Fixed import preview behavior so an intentionally empty value column is not replaced by original_value.
* Fixed admin JavaScript on screens without an export form.
* Hardened CSV header validation BOM handling and cleaned up XLSX validation flow.

= 2.3.41 =
* Fixed direct term and option target lookup dispatch.
* Fixed rollback CSV direction so rollback values restore the previous value.
* Fixed XLSX column letters for exports with more than 26 columns.
* Fixed CSV BOM header normalization before lowercasing/trimming.
* Added an import-preview reading modal that matches the import progress style.
* Added safeguards for import batch loops and XLSX download file size handling.

= 2.3.40 =
* Bugfix: import options now invalidate the prepared dry-run plan until a fresh preview is generated.
* Bugfix: inline media filename changes now require explicit confirmation on the client and server.
* Hardening: AJAX checkbox options are normalized with unslashing and sanitization before use.
* Cleanup: lifecycle transient cleanup and release metadata were aligned for this patch release.

= 2.3.39 =
* Hardening: automatic import target detection now only auto-selects exact title or slug matches.
* Cleanup: removed obsolete product-catalog confirmation remnants from runtime code and release copy.
* Cleanup: consolidated import progress modal CSS and improved dialog focus behavior.
* Release: aligned plugin version metadata and stable tag.

= 2.3.36 =
* Scope: limited managed targets to pages and posts.
* Scope: removed product and product-category fields from the supported import/export UI.
* Scope: removed generic custom-field exposure; supported fields are ACF, Yoast SEO, Rank Math, image metadata, post/page title and excerpt.
* UX: removed product-catalog staging checkbox because products are no longer handled by this plugin.

= 2.3.35 =
* Historical: product-catalog staging confirmation was only shown when product-sensitive changes were detected.
* Safety: import preview response now returns can-run state consistently for the admin UI.

= 2.3.34 =
* Historical: product-sensitive imports required explicit staging confirmation before execution.
* Safety: import preview exposes row-level change details and warnings more clearly.
* Hardening: process step rejects unsafe import plans server-side.
* Documentation: added import safety, ACF field support notes and release checklist.
* Release: kept version metadata aligned and runtime package hygiene intact.

= 2.3.32 =
* Hardening: package structure, admin CSS scoping, and decorative label accessibility cleanup.

= 2.3.31 =
* Removed the automatic import explanation text from the import form.
* Limited the import warning text to 50% width on desktop, with full width on smaller screens.

= 2.3.29 =
* Removed the selected-item import override from the UI and backend so imports always use automatic CSV/file target matching.
* Improved automatic dataset target detection by considering target_slug for whole-file matching.

= 2.3.28 =
* Import koppelt standaard automatisch op basis van CSV-inhoud, doelkolommen en bestandsnaam.
* Eén CSV met meerdere target_title/page_title waarden kan nu meerdere items in één import voorbereiden.
* Handmatig importeren naar het geselecteerde item blijft mogelijk via een expliciete checkbox.


= 2.3.27 =
* Security hardening: import plans are now bound to the admin user that prepared them before processing.
* Security hardening: inline saves and import writes now verify object-level edit permissions before changing posts, terms, products, or options.

= 2.3.26 =
* Fixed media filename no-op saves so unchanged filenames are not renamed to a unique suffixed variant.
* Fixed generated image-size metadata so thumbnails are only updated when the physical rename succeeds or the target file already exists.
* Hardened featured image and taxonomy operations against malformed operations without a valid post context.
* Added a direct-access guard to the language index file.

= 2.3.25 =
* Fix: define the inline-save fallback HTML escaping helper to prevent a rare JavaScript ReferenceError if the server omits display_html.
* Hardening: generated media size file renames now sanitize metadata filenames and confirm thumbnail paths remain inside the uploads directory.

= 2.3.24 =
* Hardening: textarea/message input is sanitized with sanitize_textarea_field() and oEmbed values with esc_url_raw() before saving/importing.

= 2.3.23 =
* Security hardening: keep import uploads in a plugin-owned temporary directory instead of falling back to the public uploads path when the temp directory cannot be prepared.
* Security hardening: media file rename now verifies that source and destination paths stay inside the WordPress uploads directory before renaming.
* Quality: cleaned up duplicate product lookup and guarded template context variable extraction.
* Quality: escaped unexpected bootstrap output defensively.
* Compatibility: import-plan TTL no longer depends on a WordPress runtime constant during class loading.

= 2.3.22 =
* Media-bestandsnaam hernoemen teruggezet: file_name-velden zijn weer bewerkbaar en fysieke attachment-bestanden worden opnieuw hernoemd met metadata-update.
* Extra rollback toegevoegd wanneer het fysieke bestand wel is hernoemd maar WordPress attachment metadata niet kan worden bijgewerkt.

= 2.3.21 =
* Hardening: media-bestandsnaamvelden zijn nu read-only, zodat import/inline edit geen fysieke bestanden meer kan hernoemen.
* Fix: CSV/XLSX-export stringifyt array/object-waarden veilig, zonder PHP array-to-string notices.
* Fix: multi-export geeft nu een nette foutmelding wanneer geen enkel bestand aan de ZIP kan worden toegevoegd.
* Fix: admin-notices worden niet meer dubbel URL-geencodeerd.
* Hardening: import-exceptionmeldingen worden gesanitized voordat ze naar AJAX teruggaan.
* Hardening: ZIP-entrynamen worden strenger op pad-traversal gecontroleerd.
* Hardening: import-previewlabels en inline empty placeholders worden veilig geescaped in JavaScript.

= 2.3.20 =
* Fix: harden import processing against incomplete/corrupted transient operations to avoid PHP notices.
* Fix: deactivation cleanup now removes the current target index cache key.
* Fix: term descriptions use the raw stored description instead of filtered frontend output.
* Fix: term description updates preserve safe HTML instead of stripping all markup.
* Fix: admin JavaScript strings and type labels are localized instead of hardcoded.
* Improvement: import and inline-save JSON error parsing now surfaces safe server messages more consistently.
* Improvement: import form now exposes the existing selected-item import mode with a visible checkbox.
* Fix: unchecking selected-item import now allows automatic filename/title matching across all supported content types.
* Fix: editable date/time fields now use the site's WordPress timezone where available.

= 2.3.19 =
* Fix: XLSX import now preserves blank cells in sparse rows so values do not shift into the wrong columns.
* Fix: multi-item export no longer applies one item-specific field selection to all selected items.
* Fix: category exports can be re-imported without false target-type mismatch skips.
* Fix: import preview no longer requires the optional PHP mbstring extension.
* Fix: inline-save AJAX errors now show a readable message instead of a browser JSON parse error.
* Hardening: import uploads are routed to a temporary import directory while using the WordPress upload API.
* Hardening: import-complete redirects are checked in JavaScript before navigation.
* Cleanup: updated stale readme copy about export behavior and translated image metadata labels.

= 2.3.18 =
* Fix: import and inline save no longer require ACF when only WordPress, SEO, custom-field, image, or term fields are used.
* Fix: category and product-category inline/import updates now keep the correct taxonomy context.
* Fix: admin CSS is scoped to the top-level Tekstbeheer menu after moving out of Tools.
* Cleanup: removed the obsolete optional ACF dependency notice/link class.
* Cleanup: uploaded import temp files are deleted even when spreadsheet parsing fails.

= 2.3.15 =
* Laat het beheerscherm altijd renderen, ook wanneer er nog geen exporteerbare items of velden zijn gevonden.
* Toont de lege staat als informatieve melding binnen de pagina in plaats van als harde blokkade.
* Ververst de target-index cache zodat oude lege resultaten niet blijven hangen.

= 2.3.13 =
* Historical ACF-focused hardening release before broader WordPress/SEO field support was restored in later versions.
* Prefer ACF field keys when reading, comparing and updating fields.
* Improved ACF value sanitization for complex values, dates, times and colors.
* Removed raw post-content fallback for empty ACF values.


= 2.3.12 =
* Historical ACF-focused workflow update before broader field export/import support returned in later versions.

= 2.3.11 =
* Cleanup: verwijdert dev-only TypeScript reference comments uit runtime JavaScript-bestanden.
* Cleanup: ruimt de reguliere cache-reset op zodat alleen de huidige indexcache wordt verwijderd; legacy transient cleanup blijft beschikbaar bij deactivatie en uninstall.
* Cleanup: kleine whitespace-opruiming zonder functionele wijziging.

= 2.3.10 =
* Release zip is packaged under the canonical `acf-page-text-manager` folder so WordPress can replace/update the existing plugin instead of installing a duplicate.
* Security hardening: voorkomt spreadsheet-formuleinterpretatie in CSV-exportwaarden.
* Security hardening: valideert upload-extensies en MIME-types strikter voor CSV-, XLSX- en ZIP-imports.
* Fix: respecteert de importinstellingen voor lege waarden overslaan en bestaande waarden overschrijven.
* Cleanup: verwijdert verouderde autoloader-verwijzingen naar niet-meegeleverde bestanden.

= 2.3.9 =
* Fix: geeft de export- en itemfilters correct hun type-opties mee aan de template, zodat er geen PHP-notice of lege typefilter ontstaat.
* Voorkomt dat import automatisch naar het geopende item wordt geforceerd; CSV/XLSX-doelen worden standaard uit het bestand bepaald.
* Vervangt de meervoudige exportselectie door een checkboxlijst met zoeken, filteren en zichtbare-selectieknoppen.
* Verbetert inline opslaan zodat de net ingevoerde waarde direct zichtbaar blijft.
* Voegt WYSIWYG-bewerking toe voor ondersteunde WYSIWYG-velden.

= 2.3.6 =
* Maakt CSV- en XLSX-importvalidatie toleranter voor gangbare exports van verschillende systemen, met behoud van basisveiligheid.

= 2.3.4 =
* Improved release polish by removing error suppression around temporary upload copies.

= 2.3.3 =
* Refined the type-aware item, export, and import workflow.
* Cleaned up the admin layout for item selection, export, and import actions.
* Removed unstable field ordering functionality and related code.
* Improved type filtering so only supported content types are shown.



= 2.2.9 =
* Production packaging cleanup: only runtime files included in the release zip.

= 2.2.6 =
* Improved import preview with file summaries, mapped versus unmapped columns, and row-level change review.
* Tightened the WordPress.org readme and free-version positioning.

= 2.2.5 =
* Added type-first workflow, type-aware filtering, grouped field panels, type-aware import validation, and item search.

= 2.2.2 =
* Added select all and clear all controls for export field selection.

= 2.2.1 =
* Added per-field export toggles in the right column.
* Export now respects selected fields for single and multi export.
