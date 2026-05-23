<?php
namespace WA_ACF_PTM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Upgrade_Cleaner {
	private const INSTALLED_VERSION_OPTION = 'wa_acf_ptm_installed_version';
	private const CLEANUP_DONE_OPTION      = 'wa_acf_ptm_cleanup_manifest_version';

	/**
	 * Registers the version check that removes stale files left by overwritten plugin installs.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_cleanup_after_upgrade' ), 1 );
	}

	/**
	 * Runs once after install/update when the stored version differs from the package version.
	 */
	public function maybe_cleanup_after_upgrade(): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$installed_version = (string) get_option( self::INSTALLED_VERSION_OPTION, '' );
		$cleanup_version   = (string) get_option( self::CLEANUP_DONE_OPTION, '' );

		if ( WA_ACF_PTM_VERSION === $installed_version && WA_ACF_PTM_VERSION === $cleanup_version ) {
			return;
		}

		$this->finalize_current_package_state();
	}

	/**
	 * Marks the currently active package as installed during plugin activation.
	 */
	public function mark_installed(): void {
		$this->finalize_current_package_state();
	}

	/**
	 * Runs cleanup tasks and records the current package version.
	 */
	private function finalize_current_package_state(): void {
		$this->remove_duplicate_legacy_plugins();
		$this->remove_files_not_in_current_package();
		update_option( self::INSTALLED_VERSION_OPTION, WA_ACF_PTM_VERSION, false );
		update_option( self::CLEANUP_DONE_OPTION, WA_ACF_PTM_VERSION, false );
	}

	/**
	 * Removes older duplicate installs of this plugin from other plugin folders.
	 *
	 * Detection is intentionally strict: it only targets plugins that declare the
	 * same plugin name or text domain. This prevents accidental deletion of
	 * unrelated plugins that merely contain similar code or wording.
	 */
	private function remove_duplicate_legacy_plugins(): void {
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$current_basename = plugin_basename( WA_ACF_PTM_FILE );
		$plugins          = get_plugins();
		$duplicates       = array();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			if ( $plugin_file === $current_basename ) {
				continue;
			}

			$name        = isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : '';
			$text_domain = isset( $plugin_data['TextDomain'] ) ? (string) $plugin_data['TextDomain'] : '';
			$basename    = basename( $plugin_file );

			if ( 'ACF Page Text Manager' === $name || 'acf-page-text-manager' === $text_domain || 'acf-page-text-manager.php' === $basename ) {
				$duplicates[] = $plugin_file;
			}
		}

		if ( array() === $duplicates ) {
			return;
		}

		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( $duplicates, true, is_multisite() );
		}

		delete_plugins( $duplicates );
	}

	/**
	 * Deletes stale files in this plugin directory that are not part of the current package manifest.
	 */
	private function remove_files_not_in_current_package(): void {
		$base_path = wp_normalize_path( trailingslashit( WA_ACF_PTM_PATH ) );
		$real_base = realpath( $base_path );

		if ( false === $real_base || ! is_dir( $real_base ) ) {
			return;
		}

		$real_base = wp_normalize_path( trailingslashit( $real_base ) );
		$manifest  = $this->manifest();
		$files     = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $real_base, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $file_info ) {
			$path = wp_normalize_path( $file_info->getPathname() );

			if ( 0 !== strpos( $path, $real_base ) ) {
				continue;
			}

			if ( $file_info->isDir() ) {
				$this->remove_empty_directory( $path, $real_base );
				continue;
			}

			$relative_path = ltrim( substr( $path, strlen( $real_base ) ), '/' );

			if ( isset( $manifest[ $relative_path ] ) ) {
				continue;
			}

			wp_delete_file( $path );
		}

		$this->remove_empty_directories( $real_base );
	}

	/**
	 * Removes empty folders below the plugin root without touching the root itself.
	 */
	private function remove_empty_directories( string $real_base ): void {
		$directories = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $real_base, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $directories as $dir_info ) {
			if ( ! $dir_info->isDir() ) {
				continue;
			}

			$this->remove_empty_directory( wp_normalize_path( $dir_info->getPathname() ), $real_base );
		}
	}

	private function remove_empty_directory( string $path, string $real_base ): void {
		$path = wp_normalize_path( trailingslashit( $path ) );

		if ( $path === $real_base || 0 !== strpos( $path, $real_base ) ) {
			return;
		}

		$items = @scandir( $path );

		if ( array( '.', '..' ) === $items ) {
			@rmdir( $path );
		}
	}

	/**
	 * Current release file manifest. Files outside this list are treated as stale leftovers.
	 *
	 * @return array<string,bool>
	 */
	private function manifest(): array {
		return array_fill_keys(
			array(
				'CHANGELOG.md',
				'README.md',
				'SECURITY.md',
				'LICENSE',
				'acf-page-text-manager.php',
				'assets/css/admin.css',
				'assets/js/admin-core.js',
				'assets/js/admin-import.js',
				'assets/js/admin-tabs.js',
				'assets/js/admin.js',
				'includes/Admin/Controllers/class-import-controller.php',
				'includes/Admin/Services/Traits/special-field-definitions-trait.php',
				'includes/Admin/Services/Traits/special-field-image-trait.php',
				'includes/Admin/Services/Traits/special-field-value-trait.php',
				'includes/Admin/Services/class-cache-invalidation-service.php',
				'includes/Admin/Services/class-download-response-service.php',
				'includes/Admin/Services/class-field-value-service.php',
				'includes/Admin/Services/class-file-upload-service.php',
				'includes/Admin/Services/class-import-plan-builder.php',
				'includes/Admin/Services/class-import-processor.php',
				'includes/Admin/Services/class-import-target-detector.php',
				'includes/Admin/Services/class-special-field-service.php',
				'includes/Admin/Services/class-spreadsheet-upload-service.php',
				'includes/Admin/Services/class-target-permission-service.php',
				'includes/Admin/Services/class-temp-file-service.php',
				'includes/Admin/Services/class-upload-validator.php',
				'includes/Admin/Services/class-zip-import-service.php',
				'includes/Admin/Traits/admin-actions-export-trait.php',
				'includes/Admin/Traits/admin-actions-inline-trait.php',
				'includes/Admin/Traits/csv-service-export-trait.php',
				'includes/Admin/Traits/csv-service-import-trait.php',
				'includes/Admin/Traits/page-repository-acf-trait.php',
				'includes/Admin/Traits/page-repository-index-trait.php',
				'includes/Admin/Traits/page-repository-summary-trait.php',
				'includes/Admin/Traits/page-repository-target-data-trait.php',
				'includes/Admin/class-admin-actions.php',
				'includes/Admin/class-admin-module.php',
				'includes/Admin/class-admin-page-view-model.php',
				'includes/Admin/class-admin-page.php',
				'includes/Admin/class-csv-service.php',
				'includes/Admin/class-import-plan-store.php',
				'includes/Admin/class-page-repository.php',
				'includes/Admin/class-settings.php',
				'includes/Admin/class-template-renderer.php',
				'includes/Core/class-autoloader.php',
				'includes/Core/class-container.php',
				'includes/Core/class-lifecycle-manager.php',
				'includes/Core/class-plugin.php',
				'includes/Core/class-upgrade-cleaner.php',
				'includes/Core/class-wp-cli-command.php',
				'languages/acf-page-text-manager-en_US.mo',
				'languages/acf-page-text-manager-nl_NL.mo',
				'languages/acf-page-text-manager.pot',
				'readme.txt',
				'templates/admin/page.php',
				'templates/admin/partials/actions-card.php',
				'templates/admin/partials/empty-state.php',
				'templates/admin/partials/fields-card.php',
				'uninstall.php',
			),
			true
		);
	}
}
