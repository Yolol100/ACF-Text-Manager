<?php
namespace WA_ACF_PTM\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Import_Plan_Store {

	private const PREFIX = 'wa_acf_ptm_plan_';
	private const TTL = 1800;

	
	public function save( string $token, array $plan ): void {
		$token = $this->sanitize_token( $token );

		if ( '' === $token ) {
			return;
		}

		set_transient( self::PREFIX . $token, $plan, self::TTL );
	}

	public function get( string $token ): ?array {
		$token = $this->sanitize_token( $token );

		if ( '' === $token ) {
			return null;
		}

		$plan = get_transient( self::PREFIX . $token );

		return is_array( $plan ) ? $plan : null;
	}

	public function delete( string $token ): void {
		$token = $this->sanitize_token( $token );

		if ( '' === $token ) {
			return;
		}

		delete_transient( self::PREFIX . $token );
	}

	private function sanitize_token( string $token ): string {
		return self::sanitize_token_value( $token );
	}

	public static function build_rollback_download_url( string $token ): string {
		$token = self::sanitize_token_value( $token );

		if ( '' === $token ) {
			return '';
		}

		return add_query_arg(
			array(
				'action'                     => 'wa_acf_ptm_download_rollback',
				'token'                      => $token,
				'wa_acf_ptm_rollback_nonce' => wp_create_nonce( 'wa_acf_ptm_rollback_download_' . $token ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	private static function sanitize_token_value( string $token ): string {
		$token = sanitize_key( $token );

		return preg_match( '/^[a-f0-9-]{32,64}$/', $token ) ? $token : '';
	}

}
