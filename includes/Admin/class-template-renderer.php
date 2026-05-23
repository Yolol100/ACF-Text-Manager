<?php
namespace WA_ACF_PTM\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Template_Renderer {
	/**
	 * @param array<string,mixed> $context
	 */
	public function render( string $template, array $context = array() ): string {
		$base_path     = realpath( WA_ACF_PTM_PATH . 'templates' );
		$template_path = realpath( WA_ACF_PTM_PATH . 'templates/' . ltrim( $template, '/' ) );

		if ( false === $base_path || false === $template_path || 0 !== strpos( $template_path, $base_path . DIRECTORY_SEPARATOR ) || ! is_readable( $template_path ) ) {
			return '';
		}

		ob_start();
		$this->include_template( $template_path, $context );

		return (string) ob_get_clean();
	}

	/**
	 * @param array<string,mixed> $context
	 */
	private function include_template( string $template_path, array $context ): void {
		$renderer            = $context['renderer'] ?? $this;
		$targets             = isset( $context['targets'] ) && is_array( $context['targets'] ) ? $context['targets'] : array();
		$all_targets         = isset( $context['all_targets'] ) && is_array( $context['all_targets'] ) ? $context['all_targets'] : array();
		$content_scope       = isset( $context['content_scope'] ) ? (string) $context['content_scope'] : '';
		$content_types       = isset( $context['content_types'] ) && is_array( $context['content_types'] ) ? $context['content_types'] : array();
		$field_groups        = isset( $context['field_groups'] ) && is_array( $context['field_groups'] ) ? $context['field_groups'] : array();
		$field_filters       = isset( $context['field_filters'] ) && is_array( $context['field_filters'] ) ? $context['field_filters'] : array();
		$active_tab          = isset( $context['active_tab'] ) ? (string) $context['active_tab'] : 'selected';
		$target_count        = isset( $context['target_count'] ) ? (int) $context['target_count'] : 0;
		$selected_target     = isset( $context['selected_target'] ) && is_array( $context['selected_target'] ) ? $context['selected_target'] : array();
		$target_data         = isset( $context['target_data'] ) && is_array( $context['target_data'] ) ? $context['target_data'] : array();
		$fields              = isset( $context['fields'] ) && is_array( $context['fields'] ) ? $context['fields'] : array();
		$tabs                = isset( $context['tabs'] ) && is_array( $context['tabs'] ) ? $context['tabs'] : array();
		$target_type_options = isset( $context['target_type_options'] ) && is_array( $context['target_type_options'] ) ? $context['target_type_options'] : array();

		require $template_path;
	}
}
