<?php
namespace WA_ACF_PTM\Core;

use WA_ACF_PTM\Admin\CSV_Service;
use WA_ACF_PTM\Admin\Page_Repository;
use WA_ACF_PTM\Admin\Services\Field_Value_Service;
use WA_ACF_PTM\Admin\Services\Import_Plan_Builder;
use WA_ACF_PTM\Admin\Services\Import_Processor;
use WA_ACF_PTM\Admin\Services\Special_Field_Service;
use WA_ACF_PTM\Admin\Services\Target_Permission_Service;
use WA_ACF_PTM\Admin\Services\Temp_File_Service;
use WA_ACF_PTM\Admin\Import_Plan_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI commands for large ACF Page Text Manager imports and exports.
 */
final class WP_CLI_Command {
	private function assert_acf_available(): void {
		if ( defined( 'ACF_VERSION' ) && function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		\WP_CLI::error( 'Advanced Custom Fields is not active. Activate ACF before running import/export commands.' );
	}

	/**
	 * Export one target to CSV or XLSX.
	 *
	 * ## OPTIONS
	 *
	 * <target>
	 * : Target reference, for example page:123.
	 *
	 * [--format=<format>]
	 * : csv or xlsx. Default: csv.
	 *
	 * --output=<path>
	 * : Destination file path.
	 */
	public function export( array $args, array $assoc_args ): void {
		$this->assert_acf_available();

		$reference = isset( $args[0] ) ? sanitize_text_field( (string) $args[0] ) : '';
		$format    = isset( $assoc_args['format'] ) && 'xlsx' === sanitize_key( (string) $assoc_args['format'] ) ? 'xlsx' : 'csv';
		$output    = isset( $assoc_args['output'] ) ? (string) $assoc_args['output'] : '';

		if ( '' === $reference ) {
			\WP_CLI::error( 'Missing target reference.' );
		}

		if ( '' === $output ) {
			\WP_CLI::error( 'Missing --output path.' );
		}

		$repository = $this->repository();
		$csv        = new CSV_Service();
		$target     = $repository->get_target_data( $reference );

		if ( empty( $target['fields'] ) ) {
			\WP_CLI::error( 'No exportable fields found for target.' );
		}

		$rows = $csv->get_export_rows( $target );

		if ( 'xlsx' === $format ) {
			$temp = $csv->build_xlsx_file( $rows, basename( $output ) );
			if ( '' === $temp || ! file_exists( $temp ) ) {
				\WP_CLI::error( 'Could not build XLSX export.' );
			}
			if ( ! copy( $temp, $output ) ) {
				Temp_File_Service::delete( $temp );
				\WP_CLI::error( 'Could not write XLSX export to output path.' );
			}
			Temp_File_Service::delete( $temp );
		} else {
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();

			if ( ! $wp_filesystem || ! $wp_filesystem->put_contents( $output, $csv->build_csv_string( $rows ), FS_CHMOD_FILE ) ) {
				\WP_CLI::error( 'Could not write CSV export to output path.' );
			}
		}

		\WP_CLI::success( sprintf( 'Exported %d rows to %s.', count( $rows ), $output ) );
	}

	/**
	 * Import a CSV or XLSX file for one target.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : CSV or XLSX file path.
	 *
	 * --target=<reference>
	 * : Target reference, for example page:123.
	 *
	 * [--yes]
	 * : Execute the import. Without --yes this is a dry run.
	 *
	 * [--confirm-media-rename]
	 * : Allow imports to physically rename media files when a media filename field changes.
	 */
	public function import( array $args, array $assoc_args ): void {
		$this->assert_acf_available();

		$file      = isset( $args[0] ) ? (string) $args[0] : '';
		$reference = isset( $assoc_args['target'] ) ? sanitize_text_field( (string) $assoc_args['target'] ) : '';
		$execute   = ! empty( $assoc_args['yes'] );
		$confirm_media_rename = ! empty( $assoc_args['confirm-media-rename'] );

		if ( '' === $file || ! is_readable( $file ) ) {
			\WP_CLI::error( 'Import file is missing or unreadable.' );
		}

		if ( '' === $reference ) {
			\WP_CLI::error( 'Missing --target reference.' );
		}

		if ( 0 === get_current_user_id() ) {
			\WP_CLI::error( 'Run this command with a WordPress user, for example: wp acf-ptm import file.csv --target=page:123 --user=1 --yes' );
		}

		$csv        = new CSV_Service();
		$repository = $this->repository();
		$target     = $repository->get_target_data( $reference );

		if ( empty( $target['fields'] ) ) {
			\WP_CLI::error( 'No importable fields found for target.' );
		}

		$dataset = $csv->read_spreadsheet_dataset( $file );
		if ( empty( $dataset['headers'] ) ) {
			\WP_CLI::error( 'Import file has no readable headers.' );
		}

		$target['ignore_import_target_mismatch'] = true;
		$target['import_options'] = array(
			'skip_empty_values'    => true,
			'overwrite_existing'   => true,
			'confirm_media_rename' => $confirm_media_rename,
		);

		$builder = new Import_Plan_Builder( $csv, new Field_Value_Service(), new Special_Field_Service( new Field_Value_Service() ) );
		$mapping = $csv->detect_column_mapping( array_values( $dataset['headers'] ) );
		$plan    = $builder->build( wp_generate_uuid4(), $target, $dataset, $mapping, basename( $file ) );

		$update_count = (int) ( $plan['summary']['update_count'] ?? 0 );
		$skip_count   = (int) ( $plan['summary']['skipped_count'] ?? 0 );

		if ( ! $execute ) {
			\WP_CLI::success( sprintf( 'Dry run complete: %d update(s), %d skipped. Re-run with --yes to execute.', $update_count, $skip_count ) );
			return;
		}

		$store = new Import_Plan_Store();
		$token = wp_generate_uuid4();
		$plan['token'] = $token;
		$plan['user_id'] = get_current_user_id();
		$plan['cursor'] = 0;
		$plan['updated_count'] = 0;
		$plan['error_messages'] = array();
		$plan['import_allowed'] = true;
		$store->save( $token, $plan );

		$processor = new Import_Processor( $store, $repository, new Field_Value_Service(), new Special_Field_Service( new Field_Value_Service() ), new Target_Permission_Service() );
		do {
			$result = $processor->process( $token );
			if ( isset( $result['error'] ) ) {
				\WP_CLI::error( (string) $result['error'] );
			}
		} while ( empty( $result['done'] ) );

		\WP_CLI::success( sprintf( 'Import complete: %d update(s), %d skipped.', (int) ( $result['updated_count'] ?? $update_count ), $skip_count ) );
	}

	private function repository(): Page_Repository {
		$field_service   = new Field_Value_Service();
		$special_service = new Special_Field_Service( $field_service );
		return new Page_Repository( $field_service, $special_service );
	}
}
