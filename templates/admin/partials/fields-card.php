<?php
/**
 * @var array<int,array<string,mixed>> $fields
 * @var array<int,array{label:string,fields:array<int,array<string,mixed>>}> $field_groups
 * @var array<int,string> $field_filters
 * @var array<string,mixed> $selected_target
 * @var array<string,mixed> $target_data
 * @var string $active_tab
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables are scoped by Template_Renderer::render().
?>
<section class="wa-acf-ptm-card wa-acf-ptm-fields-card" <?php echo ( isset( $active_tab ) && 'selected' !== $active_tab ) ? 'hidden' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static boolean attribute. ?>>
	<?php
	$selected_title = isset( $selected_target['title'] ) ? trim( (string) $selected_target['title'] ) : '';
	$selected_type  = isset( $selected_target['content_scope'] ) ? trim( (string) $selected_target['content_scope'] ) : '';
	?>
	<?php if ( ! empty( $selected_target['reference'] ) && '' !== $selected_title ) : ?>
		<header class="wa-acf-ptm-current-page-header">
			<h2 class="wa-acf-ptm-current-page-title"><?php echo esc_html( $selected_title ); ?></h2>
		</header>
	<?php endif; ?>

	<?php if ( empty( $selected_target['reference'] ) ) : ?>
		<p><?php esc_html_e( 'Kies links een pagina of bericht om de veldinhoud te tonen.', 'acf-page-text-manager' ); ?></p>
	<?php elseif ( empty( $fields ) ) : ?>
		<p><?php esc_html_e( 'Er zijn geen exporteerbare velden gevonden voor deze pagina of dit bericht.', 'acf-page-text-manager' ); ?></p>
	<?php else : ?>
		<div class="wa-acf-ptm-fields-toolbar" aria-label="<?php esc_attr_e( 'Veldtools', 'acf-page-text-manager' ); ?>">
			<div class="wa-acf-ptm-toolbar-group wa-acf-ptm-toolbar-group-filters">
				<span class="wa-acf-ptm-toolbar-label"><?php esc_html_e( 'Veldgroepen', 'acf-page-text-manager' ); ?></span>
				<div class="wa-acf-ptm-fields-toolbar-filters" role="group" aria-label="<?php esc_attr_e( 'Veldgroepen filteren', 'acf-page-text-manager' ); ?>">
					<button type="button" class="button wa-acf-ptm-field-filter is-active" data-field-filter="all" aria-pressed="true"><?php esc_html_e( 'Alles', 'acf-page-text-manager' ); ?></button>
					<?php foreach ( $field_filters as $filter_label ) : ?>
						<?php $filter_key = sanitize_title( (string) $filter_label ); ?>
						<?php if ( '' === $filter_key ) { continue; } ?>
						<button type="button" class="button wa-acf-ptm-field-filter" data-field-filter="<?php echo esc_attr( $filter_key ); ?>" aria-pressed="false"><?php echo esc_html( (string) $filter_label ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php foreach ( $field_groups as $group ) : ?>
			<section class="wa-acf-ptm-field-group" data-field-group="<?php echo esc_attr( sanitize_title( (string) $group['label'] ) ); ?>">
				<?php if ( count( $field_groups ) > 1 && 'WordPress' !== (string) $group['label'] ) : ?>
					<h3 class="wa-acf-ptm-field-group-title"><?php echo esc_html( (string) $group['label'] ); ?></h3>
				<?php endif; ?>
				<div class="wa-acf-ptm-fields-list" role="list" data-target-reference="<?php echo esc_attr( (string) $selected_target['reference'] ); ?>">
					<?php foreach ( $group['fields'] as $index => $field ) : ?>
						<?php
						$value          = isset( $field['raw_value'] ) ? ( is_array( $field['raw_value'] ) || is_object( $field['raw_value'] ) ? ( wp_json_encode( $field['raw_value'] ) ?: '' ) : (string) $field['raw_value'] ) : '';
						$field_type     = isset( $field['type'] ) ? (string) $field['type'] : '';
						$field_label    = (string) ( $field['label'] ?? '' );
						$is_read_only   = ! empty( $field['read_only'] );
						$readonly_note  = isset( $field['readonly_reason'] ) ? (string) $field['readonly_reason'] : '';
						$is_editable    = ! $is_read_only && ( in_array( $field_type, array( 'text', 'textarea', 'wysiwyg', 'message', 'email', 'url', 'oembed' ), true ) || 0 === strpos( (string) ( $field['key'] ?? '' ), 'special_' ) );
						$field_body_attributes = '';
						$editor_label = '';

						if ( $is_editable ) {
							/* translators: %s: field label. */
							$field_body_label = sprintf( __( 'Tekst voor %s', 'acf-page-text-manager' ), $field_label );
							$field_body_attributes = ' aria-label="' . esc_attr( $field_body_label ) . '"';

							/* translators: %s: field label. */
							$editor_label = sprintf( __( 'Waarde bewerken voor %s', 'acf-page-text-manager' ), $field_label );
						}
						?>
						<article class="wa-acf-ptm-field-item<?php echo esc_attr( $is_editable ? ' is-editable' : ' is-readonly' ); ?>" role="listitem" data-field-key="<?php echo esc_attr( (string) ( $field['key'] ?? '' ) ); ?>" data-field-type="<?php echo esc_attr( $field_type ); ?>">
							<div class="wa-acf-ptm-field-head"><div class="wa-acf-ptm-field-head-main"><strong class="wa-acf-ptm-field-title"><?php echo esc_html( $field_label ); ?></strong>
							<?php if ( $is_editable ) : ?>
									<span class="wa-acf-ptm-field-edit-hint"><?php esc_html_e( 'Klik om tekst aan te passen', 'acf-page-text-manager' ); ?></span>
								<?php elseif ( $is_read_only && '' !== $readonly_note ) : ?>
									<span class="wa-acf-ptm-field-edit-hint"><?php echo esc_html( $readonly_note ); ?></span>
								<?php endif; ?>
								</div>
								<div class="wa-acf-ptm-field-head-actions">
									<?php if ( $is_editable ) : ?>
										<button type="button" class="button button-secondary wa-acf-ptm-inline-edit" data-field-key="<?php echo esc_attr( (string) ( $field['key'] ?? '' ) ); ?>"><?php esc_html_e( 'Bewerken', 'acf-page-text-manager' ); ?></button>
										<button type="button" class="button button-secondary wa-acf-ptm-inline-save" disabled data-field-key="<?php echo esc_attr( (string) ( $field['key'] ?? '' ) ); ?>"><?php esc_html_e( 'Opslaan', 'acf-page-text-manager' ); ?></button>
									<?php endif; ?>
								</div>
							</div>
							<div class="wa-acf-ptm-field-body<?php echo esc_attr( '' === trim( wp_strip_all_tags( $value ) ) ? ' is-empty' : '' ); ?>"<?php echo $field_body_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_attr() above. ?>>
								<div class="wa-acf-ptm-field-display">
									<?php if ( '' === trim( wp_strip_all_tags( $value ) ) ) : ?>
										<span class="wa-acf-ptm-empty"><?php esc_html_e( 'Leeg', 'acf-page-text-manager' ); ?></span>
									<?php else : ?>
										<?php echo wp_kses_post( wpautop( $value ) ); ?>
									<?php endif; ?>
								</div>
								<?php if ( $is_editable ) : ?>
									<?php $editor_id = 'wa_acf_ptm_editor_' . substr( md5( (string) ( $selected_target['reference'] ?? '' ) . '|' . (string) ( $field['key'] ?? '' ) . '|' . (string) $index ), 0, 12 ); ?>
									<textarea id="<?php echo esc_attr( $editor_id ); ?>" class="wa-acf-ptm-inline-editor" aria-label="<?php echo esc_attr( $editor_label ); ?>" data-target-reference="<?php echo esc_attr( (string) $selected_target['reference'] ); ?>" data-field-key="<?php echo esc_attr( (string) ( $field['key'] ?? '' ) ); ?>" data-field-name="<?php echo esc_attr( (string) ( $field['name'] ?? '' ) ); ?>" data-field-type="<?php echo esc_attr( $field_type ); ?>" data-wysiwyg="<?php echo esc_attr( 'wysiwyg' === $field_type ? '1' : '0' ); ?>" rows="<?php echo esc_attr( in_array( $field_type, array( 'wysiwyg', 'textarea', 'message' ), true ) ? '8' : '4' ); ?>" hidden><?php echo esc_textarea( $value ); ?></textarea>
									<div class="wa-acf-ptm-inline-editor-status" aria-live="polite"></div>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
