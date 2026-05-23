<?php
/**
 * @var array<int,array<string,mixed>> $targets
 * @var array<string,mixed> $selected_target
 * @var array<string,string> $tabs
 * @var string $active_tab
 * @var int $target_count
 * @var array<string,string> $target_type_options
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables are scoped by Template_Renderer::render().
$tab_icons = array(
	'selected' => 'editor-table',
	'export'   => 'download',
	'import'   => 'upload',
	'product'  => 'shield-alt',
);
?>
<section class="wa-acf-ptm-card wa-acf-ptm-actions-card">
	<div class="wa-acf-ptm-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Acties', 'acf-page-text-manager' ); ?>">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<?php $is_active = $active_tab === $tab_key; ?>
			<button
				type="button"
				class="wa-acf-ptm-tab<?php echo esc_attr( $is_active ? ' is-active' : '' ); ?>"
				data-tab="<?php echo esc_attr( $tab_key ); ?>"
				id="wa-acf-ptm-tab-<?php echo esc_attr( $tab_key ); ?>"
				role="tab"
				aria-selected="<?php echo esc_attr( $is_active ? 'true' : 'false' ); ?>"
				aria-controls="wa-acf-ptm-panel-<?php echo esc_attr( $tab_key ); ?>"
				tabindex="<?php echo esc_attr( $is_active ? '0' : '-1' ); ?>"
			>
				<span class="dashicons dashicons-<?php echo esc_attr( (string) ( $tab_icons[ $tab_key ] ?? 'admin-generic' ) ); ?>" aria-hidden="true"></span>
				<span><?php echo esc_html( $tab_label ); ?></span>
			</button>
		<?php endforeach; ?>
	</div>

	<div class="wa-acf-ptm-tab-panel<?php echo esc_attr( 'selected' === $active_tab ? ' is-active' : '' ); ?>" data-panel="selected" id="wa-acf-ptm-panel-selected" role="tabpanel" aria-labelledby="wa-acf-ptm-tab-selected" tabindex="0" <?php echo 'selected' === $active_tab ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static boolean attribute. ?>>
		<form method="get" class="wa-acf-ptm-inline-picker" id="wa-acf-ptm-item-picker-form">
			<input type="hidden" name="page" value="acf-page-text-manager">
			<div class="wa-acf-ptm-inline-row">
				<div class="wa-acf-ptm-inline-field wa-acf-ptm-inline-field-type">
					<label for="wa-acf-ptm-target-type"><?php esc_html_e( 'Kies een type', 'acf-page-text-manager' ); ?></label>
					<select id="wa-acf-ptm-target-type" class="wa-acf-ptm-target-type">
						<?php foreach ( $target_type_options as $type_value => $type_label ) : ?>
							<option value="<?php echo esc_attr( $type_value ); ?>" <?php selected( (string) ( $selected_target['content_scope'] ?? '' ), (string) $type_value ); ?>><?php echo esc_html( $type_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="wa-acf-ptm-inline-field wa-acf-ptm-inline-field-search">
					<label for="wa-acf-ptm-target-search"><?php esc_html_e( 'Zoek pagina of bericht', 'acf-page-text-manager' ); ?></label>
					<input type="search" id="wa-acf-ptm-target-search" class="wa-acf-ptm-target-search" placeholder="<?php esc_attr_e( 'Zoek op titel…', 'acf-page-text-manager' ); ?>">
				</div>
				<div class="wa-acf-ptm-inline-field wa-acf-ptm-inline-field-select">
					<label for="wa-acf-ptm-target-select"><?php esc_html_e( 'Kies pagina of bericht', 'acf-page-text-manager' ); ?></label>
					<select id="wa-acf-ptm-target-select" name="target">
						<?php foreach ( $targets as $target ) : ?>
							<option
								value="<?php echo esc_attr( (string) $target['reference'] ); ?>"
								data-content-scope="<?php echo esc_attr( (string) ( $target['content_scope'] ?? '' ) ); ?>"
								<?php selected( (string) ( $selected_target['reference'] ?? '' ), (string) $target['reference'] ); ?>
							>
								<?php echo esc_html( sprintf( '%1$s (%2$d)', (string) $target['title'], (int) $target['field_count'] ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="wa-acf-ptm-inline-field wa-acf-ptm-inline-field-action wa-acf-ptm-inline-submit-wrap">
					<span class="wa-acf-ptm-field-spacer" aria-hidden="true"></span>
					<button type="submit" class="button button-primary wa-acf-ptm-button wa-acf-ptm-inline-submit"><?php esc_html_e( 'Veldinhoud openen', 'acf-page-text-manager' ); ?></button>
				</div>
			</div>
		</form>
		<?php /* translators: %d: number of found pages/posts. */ ?>
		<p class="wa-acf-ptm-support-text"><span id="wa-acf-ptm-target-count"><?php echo esc_html( sprintf( _n( '%d pagina/bericht gevonden', '%d pagina’s/berichten gevonden', $target_count, 'acf-page-text-manager' ), $target_count ) ); ?></span></p>
	</div>

	<div class="wa-acf-ptm-tab-panel" data-panel="export" id="wa-acf-ptm-panel-export" role="tabpanel" aria-labelledby="wa-acf-ptm-tab-export" tabindex="0" hidden>
		<div class="wa-acf-ptm-action-block">
			<h3><?php esc_html_e( 'Exporteren', 'acf-page-text-manager' ); ?></h3>
			<p class="wa-acf-ptm-support-text"><?php esc_html_e( 'Download ACF-velden, Yoast SEO, Rank Math, afbeeldingmetadata en titel/excerpt van pagina’s en berichten.', 'acf-page-text-manager' ); ?></p>
			<?php if ( empty( $targets ) ) : ?>
				<p class="wa-acf-ptm-support-text"><?php esc_html_e( 'Selecteer minstens één pagina of bericht om te exporteren.', 'acf-page-text-manager' ); ?></p>
			<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wa-acf-ptm-export-form">
				<input type="hidden" name="action" value="wa_acf_ptm_export">
				<?php wp_nonce_field( 'wa_acf_ptm_export_action', 'wa_acf_ptm_export_nonce' ); ?>
				<div class="wa-acf-ptm-export-picker">
					<div class="wa-acf-ptm-export-filterbar">
						<div class="wa-acf-ptm-inline-field wa-acf-ptm-inline-field-type">
							<label for="wa-acf-ptm-export-target-type"><?php esc_html_e( 'Type', 'acf-page-text-manager' ); ?></label>
							<select id="wa-acf-ptm-export-target-type" class="wa-acf-ptm-target-type">
								<?php foreach ( $target_type_options as $type_value => $type_label ) : ?>
									<option value="<?php echo esc_attr( $type_value ); ?>" <?php selected( (string) ( $selected_target['content_scope'] ?? '' ), (string) $type_value ); ?>><?php echo esc_html( $type_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="wa-acf-ptm-inline-field wa-acf-ptm-inline-field-search">
							<label for="wa-acf-ptm-export-target-search"><?php esc_html_e( 'Zoeken', 'acf-page-text-manager' ); ?></label>
							<input type="search" id="wa-acf-ptm-export-target-search" class="wa-acf-ptm-target-search" placeholder="<?php esc_attr_e( 'Zoek pagina of bericht…', 'acf-page-text-manager' ); ?>">
						</div>
						<div class="wa-acf-ptm-inline-field wa-acf-ptm-inline-field-format">
							<label for="wa-acf-ptm-export-format"><?php esc_html_e( 'Formaat', 'acf-page-text-manager' ); ?></label>
							<select id="wa-acf-ptm-export-format" name="export_format">
								<option value="csv">CSV</option>
								<option value="xlsx">XLSX</option>
							</select>
						</div>
					</div>
					<div class="wa-acf-ptm-export-selection-panel">
						<div class="wa-acf-ptm-export-selection-topline">
							<div>
								<strong id="wa-acf-ptm-export-target-list-label"><?php esc_html_e( 'Te exporteren pagina’s/berichten', 'acf-page-text-manager' ); ?> <span id="wa-acf-ptm-export-target-count" class="wa-acf-ptm-export-target-count"></span></strong>
								<p id="wa-acf-ptm-export-selection-status" class="wa-acf-ptm-support-text wa-acf-ptm-export-selection-status" aria-live="polite"></p>
							</div>
							<div class="wa-acf-ptm-export-checklist-actions" aria-label="<?php esc_attr_e( 'Exportselectie acties', 'acf-page-text-manager' ); ?>">
								<button type="button" class="button button-small" data-export-target-action="select-visible"><?php esc_html_e( 'Kies zichtbare', 'acf-page-text-manager' ); ?></button>
								<button type="button" class="button button-small" data-export-target-action="clear-all"><?php esc_html_e( 'Wis selectie', 'acf-page-text-manager' ); ?></button>
							</div>
						</div>
						<div id="wa-acf-ptm-export-selected-chips" class="wa-acf-ptm-export-selected-chips" aria-live="polite"></div>
						<div id="wa-acf-ptm-export-target-list" class="wa-acf-ptm-export-checklist" role="group" aria-labelledby="wa-acf-ptm-export-target-list-label">
							<?php foreach ( $targets as $target ) : ?>
								<?php $target_reference = (string) $target['reference']; ?>
								<label class="wa-acf-ptm-export-checklist-item" data-content-scope="<?php echo esc_attr( (string) ( $target['content_scope'] ?? '' ) ); ?>" data-search-text="<?php echo esc_attr( strtolower( (string) $target['title'] . ' ' . (string) ( $target['slug'] ?? '' ) ) ); ?>">
									<input type="checkbox" name="target_references[]" value="<?php echo esc_attr( $target_reference ); ?>" <?php checked( (string) ( $selected_target['reference'] ?? '' ), $target_reference ); ?>>
									<span class="wa-acf-ptm-export-check-title"><?php echo esc_html( (string) $target['title'] ); ?></span>
									<?php $import_export_field_count = (int) ( $target['import_export_field_count'] ?? $target['field_count'] ); ?>
									<?php /* translators: %d: number of import/export fields for this page/post. */ ?>
									<span class="wa-acf-ptm-export-check-meta"><?php echo esc_html( sprintf( _n( '%d export/import veld', '%d export/import velden', $import_export_field_count, 'acf-page-text-manager' ), $import_export_field_count ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="wa-acf-ptm-inline-row wa-acf-ptm-inline-row-export-actions">
					<p id="wa-acf-ptm-export-field-selection-status" class="wa-acf-ptm-support-text wa-acf-ptm-export-field-selection-status" aria-live="polite"></p>
					<button type="submit" class="button button-secondary wa-acf-ptm-button wa-acf-ptm-export-submit" data-default-label="<?php echo esc_attr__( 'Export downloaden', 'acf-page-text-manager' ); ?>"><?php esc_html_e( 'Export downloaden', 'acf-page-text-manager' ); ?></button>
				</div>
			</form>
			<?php endif; ?>
		</div>
	</div>

	<div class="wa-acf-ptm-tab-panel" data-panel="import" id="wa-acf-ptm-panel-import" role="tabpanel" aria-labelledby="wa-acf-ptm-tab-import" tabindex="0" hidden>
		<div class="wa-acf-ptm-action-block">
			<h3><?php esc_html_e( 'Importeren', 'acf-page-text-manager' ); ?></h3>
			<p class="wa-acf-ptm-support-text wa-acf-ptm-import-intro"><?php esc_html_e( 'Import kan alleen ACF-velden, Yoast SEO, Rank Math, afbeeldingmetadata en titel/excerpt van pagina’s en berichten bijwerken.', 'acf-page-text-manager' ); ?></p>
			<form id="wa-acf-ptm-import-form" enctype="multipart/form-data">
				<label for="wa-acf-ptm-import-file"><?php esc_html_e( 'CSV-, XLSX- of ZIP-bestand', 'acf-page-text-manager' ); ?></label>
				<div class="wa-acf-ptm-file-shell">
					<input id="wa-acf-ptm-import-file" class="wa-acf-ptm-file-input wa-acf-ptm-import-file" type="file" name="import_file[]" multiple accept=".csv,.xlsx,.zip,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip">
					<label class="wa-acf-ptm-file-trigger" for="wa-acf-ptm-import-file"><?php esc_html_e( 'Bestand kiezen', 'acf-page-text-manager' ); ?></label>
					<span class="wa-acf-ptm-file-name" id="wa-acf-ptm-file-name"><?php esc_html_e( 'Nog geen bestand gekozen', 'acf-page-text-manager' ); ?></span>
				</div>
				<p class="wa-acf-ptm-support-text"><?php esc_html_e( 'Kies een CSV-, XLSX- of ZIP-bestand. De import wordt eerst gevalideerd en daarna pas uitgevoerd na je bevestiging.', 'acf-page-text-manager' ); ?></p>
				<fieldset class="wa-acf-ptm-import-options">
					<legend><?php esc_html_e( 'Importveiligheid', 'acf-page-text-manager' ); ?></legend>
					<label><input type="checkbox" name="skip_empty_values" value="1" checked> <?php esc_html_e( 'Lege importwaarden overslaan', 'acf-page-text-manager' ); ?></label>
					<label><input type="checkbox" name="overwrite_existing" value="1" checked> <?php esc_html_e( 'Bestaande waarden overschrijven', 'acf-page-text-manager' ); ?></label>
					<label><input type="checkbox" name="confirm_media_rename" value="1"> <?php esc_html_e( 'Ik begrijp dat media-bestandsnamen bestaande media-URL’s kunnen wijzigen', 'acf-page-text-manager' ); ?></label>
				</fieldset>
				<div class="wa-acf-ptm-import-buttons">
					<button type="button" id="wa-acf-ptm-run-button" class="button button-primary wa-acf-ptm-button wa-acf-ptm-button-primary" disabled><?php esc_html_e( 'Import uitvoeren', 'acf-page-text-manager' ); ?></button>
				</div>
			</form>
			<div id="wa-acf-ptm-status" class="wa-acf-ptm-status" role="status" aria-live="polite" aria-atomic="true"></div>
			<div id="wa-acf-ptm-announcer" class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true"></div>
		</div>
	</div>

	</section>
