<?php
/**
 * @var WA_ACF_PTM\Admin\Template_Renderer $renderer
 * @var array<int,array<string,mixed>> $targets
 * @var array<int,array<string,mixed>> $all_targets
 * @var string $content_scope
 * @var array<string,string> $content_types
 * @var array<int,array{label:string,fields:array<int,array<string,mixed>>}> $field_groups
 * @var string $active_tab
 * @var int $target_count
 * @var array<string,mixed> $selected_target
 * @var array<string,mixed> $target_data
 * @var array<int,array<string,mixed>> $fields
 * @var array<string,string> $tabs
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer partials escape their own markup and variables.
?>
<div class="wrap wa-acf-ptm-simple">
	<?php if ( empty( $targets ) ) : ?>
		<?php echo $renderer->render( 'admin/partials/empty-state.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Partial template escapes its own output. ?>
	<?php endif; ?>
	<div class="wa-acf-ptm-grid">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Partial template escapes its own output.
			echo $renderer->render(
				'admin/partials/actions-card.php',
				array(
					'targets'             => $targets,
					'selected_target'     => $selected_target,
					'target_type_options' => $target_type_options,
					'tabs'            => $tabs,
					'active_tab'      => $active_tab,
					'target_count'    => $target_count,
				)
			);
			?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Partial template escapes its own output.
			echo $renderer->render(
				'admin/partials/fields-card.php',
				array(
					'fields'          => $fields,
					'field_groups'    => $field_groups,
					'field_filters'   => $field_filters,
					'selected_target' => $selected_target,
				)
			);
			?>
	</div>
</div>
<?php // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped ?>
