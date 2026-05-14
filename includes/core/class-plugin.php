<?php
namespace WA_ACF_PTM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private Container $container;
	private Lifecycle_Manager $lifecycle;
	private bool $modules_registered = false;

	public static function boot(): void {
		$plugin = new self();
		$plugin->register();
	}

	public function __construct() {
		$this->container    = new Container();
		$this->lifecycle    = new Lifecycle_Manager();
	}

	private function register(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_modules' ) );
		add_action( 'admin_init', array( $this, 'register_privacy_policy_content' ) );
		$this->lifecycle->register();
	}


	public function load_textdomain(): void {
		load_plugin_textdomain(
			'acf-page-text-manager',
			false,
			dirname( plugin_basename( WA_ACF_PTM_FILE ) ) . '/languages'
		);
	}


	public function register_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = wp_kses_post(
			__( 'ACF Page Text Manager allows administrators to export and import selected post, term, option, SEO and ACF field values. Depending on the configured fields, exported files and import previews may contain personal data. Review exported files before sharing them, store rollback files securely, and remove downloaded files when they are no longer needed.', 'acf-page-text-manager' )
		);

		wp_add_privacy_policy_content( 'ACF Page Text Manager', wpautop( $content ) );
	}

	public function register_modules(): void {
		if ( $this->modules_registered || ! is_admin() ) {
			return;
		}

		$this->modules_registered = true;
		$admin_module              = new \WA_ACF_PTM\Admin\Admin_Module( $this->container );
		$admin_module->register();
	}
}
