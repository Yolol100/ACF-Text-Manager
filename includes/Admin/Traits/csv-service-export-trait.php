<?php
namespace WA_ACF_PTM\Admin\Traits;

use WA_ACF_PTM\Admin\Settings;
use WA_ACF_PTM\Admin\Services\Download_Response_Service;
use WA_ACF_PTM\Admin\Services\Temp_File_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CSV_Service_Export_Trait {
	public function get_export_rows( array $target_data, array $allowed_field_keys = array() ): array {
		return $this->target_to_rows( $target_data, $allowed_field_keys );
	}


	// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV generation uses php://temp with fputcsv().
	public function build_csv_string( array $rows ): string {
		$delimiter = (string) Settings::get()['delimiter'];
		$stream = fopen( 'php://temp', 'r+' );
		if ( false === $stream ) {
			return '';
		}
		fwrite( $stream, "\xEF\xBB\xBF" );
		fputcsv( $stream, $this->get_header_row(), $delimiter );
		foreach ( $rows as $row ) {
			fputcsv( $stream, $this->escape_csv_formula_row( $row ), $delimiter );
		}
		rewind( $stream );
		$content = stream_get_contents( $stream );
		fclose( $stream );
		return false === $content ? '' : $content;
	}

	public function stream_custom_csv_rows( string $filename, array $header_row, array $rows ): void {
		Download_Response_Service::send_headers( 'text/csv; charset=utf-8', $filename );

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streams generated CSV directly to browser output.
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		fwrite( $output, "\xEF\xBB\xBF" );
		$delimiter = (string) Settings::get()['delimiter'];
		fputcsv( $output, $header_row, $delimiter );
		foreach ( $rows as $row ) {
			fputcsv( $output, $this->escape_csv_formula_row( $row ), $delimiter );
		}
		fclose( $output );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}


	// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	/**
	 * Prefix spreadsheet formula-like CSV cells so exported content is not
	 * interpreted as a formula by Excel, LibreOffice, or similar tools.
	 *
	 * @param array<int|string, mixed> $row Export row.
	 * @return array<int|string, mixed>
	 */
	private function escape_csv_formula_row( array $row ): array {
		foreach ( $row as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$string_value = (string) $value;
			if ( '' !== $string_value && preg_match( '/^[=+\-@\t\r]/', $string_value ) ) {
				$row[ $key ] = "'" . $string_value;
			}
		}

		return $row;
	}

	public function stream_export( array $target_data, array $allowed_field_keys = array(), string $format = 'csv' ): void {
		$rows = $this->target_to_rows( $target_data, $allowed_field_keys );

		$filename = sprintf(
			'wa-acf-text-%s-%s.%s',
			sanitize_file_name( (string) $target_data['slug'] ),
			gmdate( 'Y-m-d-H-i-s' ),
			'xlsx' === $format ? 'xlsx' : 'csv'
		);

		if ( 'xlsx' === $format ) {
			$this->stream_xlsx_rows( $filename, $rows );
			return;
		}

		$this->stream_csv_rows( $filename, $rows );
	}

	private function stream_csv_rows( string $filename, array $rows ): void {
		Download_Response_Service::send_headers( 'text/csv; charset=utf-8', $filename );

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streams generated CSV directly to browser output.
		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			exit;
		}

		fwrite( $output, "\xEF\xBB\xBF" );
		$delimiter = (string) Settings::get()['delimiter'];
		fputcsv( $output, $this->get_header_row(), $delimiter );

		foreach ( $rows as $row ) {
			fputcsv( $output, $this->escape_csv_formula_row( $row ), $delimiter );
		}

		fclose( $output );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public function build_xlsx_file( array $rows, string $filename ): string {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return '';
		}

		$temp = wp_tempnam( $filename );
		if ( ! $temp ) {
			return '';
		}

		$zip = new \ZipArchive();
		$opened = $zip->open( $temp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		if ( true !== $opened ) {
			Temp_File_Service::delete( $temp );
			return '';
		}

		$all_rows       = array_merge( array( $this->get_header_row() ), $rows );
		$shared_strings = $this->build_shared_strings( $all_rows );
		$sheet_xml      = $this->build_sheet_xml( $all_rows, $shared_strings['lookup'] );

		$zip->addFromString( '[Content_Types].xml', $this->get_content_types_xml() );
		$zip->addEmptyDir( '_rels' );
		$zip->addFromString( '_rels/.rels', $this->get_root_rels_xml() );
		$zip->addEmptyDir( 'docProps' );
		$zip->addFromString( 'docProps/app.xml', $this->get_docprops_app_xml() );
		$zip->addFromString( 'docProps/core.xml', $this->get_docprops_core_xml() );
		$zip->addEmptyDir( 'xl' );
		$zip->addFromString( 'xl/workbook.xml', $this->get_workbook_xml() );
		$zip->addEmptyDir( 'xl/_rels' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $this->get_workbook_rels_xml() );
		$zip->addEmptyDir( 'xl/worksheets' );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->addFromString( 'xl/sharedStrings.xml', $this->get_shared_strings_xml( $shared_strings['values'] ) );
		$zip->addFromString( 'xl/styles.xml', $this->get_styles_xml() );
		$zip->close();

		return $temp;
	}



	private function stream_xlsx_rows( string $filename, array $rows ): void {
		$temp = $this->build_xlsx_file( $rows, $filename );
		if ( '' === $temp ) {
			wp_die( esc_html__( 'Het XLSX-bestand kon niet worden opgebouwd.', 'acf-page-text-manager' ) );
		}

		if ( ! Temp_File_Service::is_safe_temp_file( $temp ) || ! is_readable( $temp ) ) {
			Temp_File_Service::delete( $temp );
			wp_die( esc_html__( 'Het XLSX-bestand kon niet veilig worden gelezen.', 'acf-page-text-manager' ) );
		}

		$file_size = filesize( $temp );
		Download_Response_Service::send_headers( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $filename, false === $file_size ? false : (int) $file_size );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams a generated export file to the browser after temp-path validation.
		readfile( $temp );
		Temp_File_Service::delete( $temp );
		exit;
	}


	private function build_shared_strings( array $rows ): array {
		$values = array();
		$lookup = array();

		foreach ( $rows as $row ) {
			foreach ( $row as $cell ) {
				$cell = (string) $cell;
				if ( ! array_key_exists( $cell, $lookup ) ) {
					$lookup[ $cell ] = count( $values );
					$values[] = $cell;
				}
			}
		}

		return array(
			'values' => $values,
			'lookup' => $lookup,
		);
	}

	private function build_sheet_xml( array $rows, array $shared_lookup ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

		foreach ( $rows as $row_index => $row ) {
			$xml .= '<row r="' . ( $row_index + 1 ) . '">';
			foreach ( array_values( $row ) as $column_index => $value ) {
				$ref          = $this->column_index_to_letters( $column_index ) . ( $row_index + 1 );
				$string_index = $shared_lookup[ (string) $value ] ?? 0;
				$xml .= '<c r="' . $this->xml_escape( $ref ) . '" t="s"><v>' . $string_index . '</v></c>';
			}
			$xml .= '</row>';
		}

		$xml .= '</sheetData></worksheet>';
		return $xml;
	}

	private function get_shared_strings_xml( array $shared_strings ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$xml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $shared_strings ) . '" uniqueCount="' . count( $shared_strings ) . '">';
		foreach ( $shared_strings as $value ) {
			$xml .= '<si><t xml:space="preserve">' . $this->xml_escape( $value ) . '</t></si>';
		}
		$xml .= '</sst>';
		return $xml;
	}

	private function get_header_row(): array {
		return array(
			'target_type',
			'content_scope',
			'target_id',
			'target_title',
			'target_slug',
			'page_id',
			'page_title',
			'field_name',
			'field_key',
			'field_label',
			'field_type',
			'language_code',
			'original_value',
			'value',
		);
	}

	private function get_content_types_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
			. '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
			. '</Types>';
	}

	private function get_root_rels_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
			. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
			. '</Relationships>';
	}

	private function get_docprops_app_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>WordPress</Application></Properties>';
	}

	private function get_docprops_core_xml(): string {
		$now = gmdate( 'Y-m-d\TH:i:s\Z' );
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
			. '<dc:creator>WA ACF Page Text Manager</dc:creator>'
			. '<cp:lastModifiedBy>WA ACF Page Text Manager</cp:lastModifiedBy>'
			. '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
			. '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
			. '</cp:coreProperties>';
	}

	private function get_workbook_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>';
	}

	private function get_workbook_rels_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
			. '</Relationships>';
	}

	private function get_styles_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
			. '<borders count="1"><border/></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
			. '</styleSheet>';
	}

	private function target_to_rows( array $target_data, array $allowed_field_keys = array() ): array {
		$rows = array();

		$content_scope      = sanitize_key( (string) ( $target_data['content_scope'] ?? '' ) );
		$export_target_type = $this->get_export_target_type( $target_data, $content_scope );

		foreach ( $target_data['fields'] as $field ) {
			$field_key = (string) ( $field['key'] ?? '' );

			if ( ! empty( $allowed_field_keys ) && ! in_array( $field_key, $allowed_field_keys, true ) ) {
				continue;
			}

			$export_value = $this->stringify_export_value( $field['raw_value'] ?? '' );

			$rows[] = array(
				$export_target_type,
				$content_scope,
				(string) $target_data['target_id'],
				(string) $target_data['title'],
				(string) ( $target_data['slug'] ?? '' ),
				'page' === $content_scope ? (int) $target_data['target_id'] : 0,
				'page' === $content_scope ? (string) $target_data['title'] : '',
				$this->get_export_field_name( $field ),
				(string) $field['key'],
				(string) $field['label'],
				(string) $field['type'],
				(string) $target_data['language_code'],
				$export_value,
				$export_value,
			);
		}

		return $rows;
	}

	private function stringify_export_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			return is_string( $encoded ) ? $encoded : '';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	private function get_export_target_type( array $target_data, string $content_scope ): string {
		$target_type = sanitize_key( (string) ( $target_data['target_type'] ?? '' ) );

		if ( in_array( $content_scope, array( 'page', 'post' ), true ) ) {
			return $content_scope;
		}

		return $target_type;
	}

	private function get_export_field_name( array $field ): string {
		return sanitize_key( (string) ( $field['name'] ?? '' ) );
	}

	private function column_index_to_letters( int $index ): string {
		$letters = '';
		$index++;

		while ( $index > 0 ) {
			$index--;
			$letters = chr( 65 + ( $index % 26 ) ) . $letters;
			$index = (int) floor( $index / 26 );
		}

		return $letters;
	}

	private function xml_escape( string $value ): string {
		return htmlspecialchars( $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
	}
}
