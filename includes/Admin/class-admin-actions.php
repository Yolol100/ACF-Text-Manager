<?php
namespace WA_ACF_PTM\Admin;

use WA_ACF_PTM\Admin\Services\Cache_Invalidation_Service;
use WA_ACF_PTM\Admin\Services\Field_Value_Service;
use WA_ACF_PTM\Admin\Services\File_Upload_Service;
use WA_ACF_PTM\Admin\Services\Import_Plan_Builder;
use WA_ACF_PTM\Admin\Services\Import_Processor;
use WA_ACF_PTM\Admin\Services\Target_Permission_Service;
use WA_ACF_PTM\Admin\Controllers\Import_Controller;
use WA_ACF_PTM\Admin\Services\Import_Target_Detector;
use WA_ACF_PTM\Admin\Traits\Admin_Actions_Export_Trait;
use WA_ACF_PTM\Admin\Traits\Admin_Actions_Inline_Trait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Actions {
	use Admin_Actions_Export_Trait, Admin_Actions_Inline_Trait;

	private Page_Repository $repository;
	private CSV_Service $csv_service;
	private Import_Plan_Store $plan_store;
	private File_Upload_Service $upload_service;
	private Import_Plan_Builder $plan_builder;
	private Import_Processor $import_processor;
	private Cache_Invalidation_Service $cache_invalidator;
	private Target_Permission_Service $target_permissions;
	private Import_Target_Detector $import_target_detector;
	private Import_Controller $import_controller;

	public function __construct(
		Page_Repository $repository,
		CSV_Service $csv_service,
		Import_Plan_Store $plan_store,
		?File_Upload_Service $upload_service = null,
		?Import_Plan_Builder $plan_builder = null,
		?Import_Processor $import_processor = null,
		?Cache_Invalidation_Service $cache_invalidator = null,
		?Target_Permission_Service $target_permissions = null,
		?Import_Target_Detector $import_target_detector = null,
		?Import_Controller $import_controller = null
	) {
		$this->repository         = $repository;
		$this->csv_service        = $csv_service;
		$this->plan_store         = $plan_store;
		$this->upload_service     = $upload_service ?? new File_Upload_Service();
		$this->plan_builder       = $plan_builder ?? new Import_Plan_Builder( $csv_service, new Field_Value_Service() );
		$this->import_processor   = $import_processor ?? new Import_Processor( $plan_store, $repository );
		$this->cache_invalidator  = $cache_invalidator ?? new Cache_Invalidation_Service( $repository );
		$this->target_permissions      = $target_permissions ?? new Target_Permission_Service();
		$this->import_target_detector  = $import_target_detector ?? new Import_Target_Detector( $csv_service, $repository );
		$this->import_controller       = $import_controller ?? new Import_Controller(
			$csv_service,
			$plan_store,
			$this->upload_service,
			$this->plan_builder,
			$this->import_processor,
			$this->import_target_detector
		);
	}

	public function register(): void {
		add_action( 'admin_post_wa_acf_ptm_export', array( $this, 'handle_export' ) );
		add_filter( 'bulk_actions-edit-page', array( $this, 'register_pages_bulk_export_action' ) );
		add_filter( 'handle_bulk_actions-edit-page', array( $this, 'handle_pages_bulk_export_action' ), 10, 3 );
		add_filter( 'bulk_actions-edit-post', array( $this, 'register_pages_bulk_export_action' ) );
		add_filter( 'handle_bulk_actions-edit-post', array( $this, 'handle_pages_bulk_export_action' ), 10, 3 );
		add_action( 'admin_post_wa_acf_ptm_download_rollback', array( $this, 'handle_rollback_download' ) );
		add_action( 'acf/save_post', array( $this, 'flush_cache_after_acf_save' ) );
		add_action( 'save_post', array( $this, 'flush_cache_after_post_save' ), 10, 2 );
		add_action( 'edited_term', array( $this, 'flush_cache_after_term_change' ), 10, 3 );
		add_action( 'created_term', array( $this, 'flush_cache_after_term_change' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'flush_cache_after_term_change' ), 10, 3 );
		add_action( 'wp_ajax_wa_acf_ptm_prepare_import', array( $this, 'ajax_prepare_import' ) );
		add_action( 'wp_ajax_wa_acf_ptm_process_import', array( $this, 'ajax_process_import' ) );
		add_action( 'wp_ajax_wa_acf_ptm_save_field', array( $this, 'ajax_save_field' ) );
	}

	public function flush_cache_after_acf_save( $post_id = 0 ): void {
		$this->cache_invalidator->flush_after_acf_save( $post_id );
	}

	public function flush_cache_after_post_save( int $post_id, \WP_Post $post ): void {
		$this->cache_invalidator->flush_after_post_save( $post_id, $post );
	}

	public function flush_cache_after_term_change( int $term_id, int $tt_id = 0, string $taxonomy = '' ): void {
		$this->cache_invalidator->flush_after_term_change( $term_id, $tt_id, $taxonomy );
	}



	public function ajax_prepare_import(): void {
		$this->assert_ajax_permissions();
		$this->assert_acf_dependency( true );
		$this->import_controller->prepare_import();
	}

	public function ajax_process_import(): void {
		$this->assert_ajax_permissions();
		$this->assert_acf_dependency( true );
		$this->import_controller->process_import();
	}

	private function assert_acf_dependency( bool $ajax = false ): void {
		if ( defined( 'ACF_VERSION' ) && function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		$message = __( 'Advanced Custom Fields is verplicht voor import en export. Activeer ACF voordat je deze actie uitvoert.', 'acf-page-text-manager' );

		if ( $ajax ) {
			wp_send_json_error( array( 'message' => $message ), 400 );
		}

		wp_die( esc_html( $message ) );
	}

	private function assert_permissions(): void {
		if ( ! current_user_can( Settings::get_manage_capability() ) ) {
			wp_die( esc_html__( 'Je hebt geen toestemming om deze actie uit te voeren.', 'acf-page-text-manager' ) );
		}
	}

	private function assert_ajax_permissions(): void {
		$this->assert_permissions();
		check_ajax_referer( 'wa_acf_ptm_ajax_nonce', 'nonce' );
	}

	private function redirect_with_notice( string $type, string $message ) {
		$url = add_query_arg(
			array(
				'page'               => 'acf-page-text-manager',
				'wa_acf_ptm_notice'  => sanitize_key( $type ),
				'wa_acf_ptm_message' => sanitize_text_field( $message ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
