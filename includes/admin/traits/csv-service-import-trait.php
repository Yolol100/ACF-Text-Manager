<?php
namespace WA_ACF_PTM\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CSV_Service_Import_Trait {
	public function read_spreadsheet_dataset( string $path ): array {
		$ext = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( 'xlsx' === $ext ) {
			return $this->read_xlsx_dataset( $path );
		}

		return $this->read_csv_dataset( $path );
	}

	public function map_dataset_rows( array $dataset, array $mapping ): array {
		$headers    = isset( $dataset['headers'] ) && is_array( $dataset['headers'] ) ? array_values( $dataset['headers'] ) : array();
		$rows       = isset( $dataset['rows'] ) && is_array( $dataset['rows'] ) ? array_values( $dataset['rows'] ) : array();
		$mapped     = array();
		$header_map = array_flip( $headers );
		$has_direct_field_mapping = in_array( 'field_name', $mapping, true ) || in_array( 'field_key', $mapping, true ) || in_array( 'field_label', $mapping, true );
		$has_direct_value_mapping = in_array( 'value', $mapping, true ) || in_array( 'original_value', $mapping, true );

		foreach ( $rows as $row_index => $row ) {
			if ( ! is_array( $row ) || $this->is_empty_csv_row( $row ) ) {
				continue;
			}

			$record = array(
				'row_index'     => $row_index + 2,
				'target_type'   => '',
				'content_scope' => '',
				'target_id'     => '',
				'target_title'  => '',
				'target_slug'   => '',
				'page_id'       => 0,
				'page_title'    => '',
				'field_name'    => '',
				'field_key'     => '',
				'field_label'   => '',
				'field_type'    => '',
				'language_code' => '',
				'original_value'=> '',
				'value'         => '',
				'raw_row'       => array_values( $row ),
			);

			foreach ( $mapping as $header => $column ) {
				if ( '' === $column || ! isset( $header_map[ $header ] ) ) {
					continue;
				}

				$value = isset( $row[ $header_map[ $header ] ] ) ? (string) $row[ $header_map[ $header ] ] : '';

				switch ( $column ) {
					case 'page_id':
						$record['page_id'] = absint( $value );
						break;
					case 'field_name':
						$record['field_name'] = sanitize_key( $value );
						break;
					case 'field_key':
					case 'target_id':
					case 'target_title':
					case 'target_slug':
					case 'page_title':
					case 'field_label':
					case 'field_type':
					case 'language_code':
					case 'original_value':
					case 'value':
						$record[ $column ] = $value;
						break;
					case 'target_type':
						$record['target_type'] = sanitize_key( $value );
						break;
					case 'content_scope':
						$record['content_scope'] = sanitize_key( $value );
						break;
				}
			}

			if ( $has_direct_field_mapping || $has_direct_value_mapping ) {
				$mapped[] = $record;
				continue;
			}

			foreach ( $headers as $column_index => $header ) {
				if ( '' !== (string) ( $mapping[ $header ] ?? '' ) || $this->is_identity_header( $header ) ) {
					continue;
				}

				$value = isset( $row[ $column_index ] ) ? (string) $row[ $column_index ] : '';
				if ( '' === trim( $value ) ) {
					continue;
				}

				$wide_record = $record;
				$wide_record['field_name']  = sanitize_key( $header );
				$wide_record['field_label'] = $this->header_to_label( $header );
				$wide_record['value']       = $value;
				$mapped[] = $wide_record;
			}
		}

		return $mapped;
	}

	public function detect_column_mapping( array $headers ): array {
		$synonyms = array(
			'target_type'   => array( 'target_type', 'type', 'doel_type', 'item_type', 'content_type' ),
			'content_scope' => array( 'content_scope', 'scope', 'doel_scope', 'item_scope', 'post_type', 'taxonomy' ),
			'target_id'     => array( 'target_id', 'post_id', 'acf_post_id', 'doel_id', 'item_id' ),
			'target_title'  => array( 'target_title', 'target_name', 'doel_titel', 'item_titel', 'item_naam' ),
			'target_slug'   => array( 'target_slug', 'slug', 'post_name', 'page_slug', 'product_slug', 'doel_slug', 'item_slug' ),
			'page_id'       => array( 'page_id', 'pagina_id', 'paginaid' ),
			'page_title'    => array( 'page_title', 'pagina_titel', 'pagina_naam', 'paginanaam' ),
			'field_name'    => array( 'field_name', 'field', 'name', 'naam', 'veld', 'veldnaam', 'veld_naam', 'acf_name', 'acf_naam' ),
			'field_key'     => array( 'field_key', 'key', 'veld_key', 'acf_key', 'acf_sleutel' ),
			'field_label'   => array( 'field_label', 'label', 'veld_label', 'veld_titel', 'veld_omschrijving' ),
			'field_type'    => array( 'field_type', 'veld_type', 'type_veld' ),
			'language_code' => array( 'language_code', 'lang', 'locale', 'taal', 'taalcode' ),
			'original_value'=> array( 'original_value', 'old_value', 'source_value', 'huidige_waarde', 'originele_waarde', 'oude_waarde', 'bestaande_waarde' ),
			'value'         => array( 'value', 'new_value', 'tekst', 'content', 'inhoud', 'waarde', 'nieuwe_waarde', 'nieuwe_tekst', 'import_waarde', 'importwaarde', 'vertaling' ),
		);

		$mapping = array();

		foreach ( $headers as $header ) {
			$mapped_to = '';
			foreach ( $synonyms as $column => $matches ) {
				if ( in_array( $header, $matches, true ) ) {
					$mapped_to = $column;
					break;
				}
			}
			$mapping[ $header ] = $mapped_to;
		}

		return $mapping;
	}

	// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Import parsing requires fgetcsv() streaming with delimiter detection.
	private function read_csv_dataset( string $path ): array {
		$handle = fopen( $path, 'r' );

		if ( false === $handle ) {
			return array( 'headers' => array(), 'rows' => array() );
		}

		$delimiter = (string) Settings::get()['delimiter'];
		$header = fgetcsv( $handle, 0, $delimiter );

		if ( ( empty( $header ) || ! is_array( $header ) || 1 === count( $header ) ) && ';' !== $delimiter ) {
			rewind( $handle );
			$header = fgetcsv( $handle, 0, ';' );
			$delimiter = ';';
		}

		if ( ( empty( $header ) || ! is_array( $header ) || 1 === count( $header ) ) && ',' !== $delimiter ) {
			rewind( $handle );
			$header = fgetcsv( $handle, 0, ',' );
			$delimiter = ',';
		}

		if ( empty( $header ) || ! is_array( $header ) ) {
			fclose( $handle );
			return array( 'headers' => array(), 'rows' => array() );
		}

		$header = array_map( array( $this, 'normalize_header_value' ), $header );
		$rows   = array();
		$column_count = count( $header );
		if ( $column_count > self::MAX_IMPORT_COLUMNS ) {
			fclose( $handle );
			return array( 'headers' => array(), 'rows' => array() );
		}

		while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
			if ( count( $rows ) >= self::MAX_IMPORT_ROWS ) {
				break;
			}
			$rows[] = array_map( 'strval', array_slice( $row, 0, self::MAX_IMPORT_COLUMNS ) );
		}

		fclose( $handle );

		return array(
			'headers' => $header,
			'rows'    => $rows,
		);
	}

	private function read_xlsx_dataset( string $path ): array {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return array( 'headers' => array(), 'rows' => array() );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $path ) ) {
			return array( 'headers' => array(), 'rows' => array() );
		}

		$shared_strings = array();
		$shared_xml     = $zip->getFromName( 'xl/sharedStrings.xml' );

		if ( false !== $shared_xml && strlen( $shared_xml ) <= self::MAX_XLSX_XML_BYTES ) {
			$shared_doc = $this->safe_simplexml_load_string( $shared_xml );
			if ( $shared_doc instanceof \SimpleXMLElement ) {
				$namespaces = $shared_doc->getNamespaces( true );
				$ns = isset( $namespaces[''] ) ? $namespaces[''] : '';
				foreach ( $shared_doc->children( $ns )->si as $si ) {
					$text = '';
					if ( isset( $si->t ) ) {
						$text = (string) $si->t;
					} elseif ( isset( $si->r ) ) {
						foreach ( $si->r as $run ) {
							$text .= (string) $run->t;
						}
					}
					$shared_strings[] = $text;
				}
			}
		}

		$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );

		if ( false === $sheet_xml ) {
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$name = (string) $zip->getNameIndex( $i );
				if ( preg_match( '#^xl/worksheets/sheet[0-9]+\.xml$#', $name ) ) {
					$sheet_xml = $zip->getFromName( $name );
					break;
				}
			}
		}

		$zip->close();

		if ( false === $sheet_xml || strlen( $sheet_xml ) > self::MAX_XLSX_XML_BYTES ) {
			return array( 'headers' => array(), 'rows' => array() );
		}

		$sheet = $this->safe_simplexml_load_string( $sheet_xml );
		if ( ! $sheet instanceof \SimpleXMLElement ) {
			return array( 'headers' => array(), 'rows' => array() );
		}

		$rows_matrix = array();
		$namespaces  = $sheet->getNamespaces( true );
		$ns          = isset( $namespaces[''] ) ? $namespaces[''] : '';

		foreach ( $sheet->children( $ns )->sheetData->row as $row ) {
			if ( count( $rows_matrix ) >= self::MAX_IMPORT_ROWS ) {
				break;
			}
			$row_values = array();
			foreach ( $row->c as $cell ) {
				if ( count( $row_values ) >= self::MAX_IMPORT_COLUMNS ) {
					break;
				}
				$ref   = isset( $cell['r'] ) ? (string) $cell['r'] : '';
				$index = $this->cell_ref_to_index( $ref );
				$type  = isset( $cell['t'] ) ? (string) $cell['t'] : '';
				$value = '';
				if ( 'inlineStr' === $type && isset( $cell->is->t ) ) {
					$value = (string) $cell->is->t;
				} elseif ( 's' === $type && isset( $cell->v ) ) {
					$shared_index = (int) $cell->v;
					$value = $shared_strings[ $shared_index ] ?? '';
				} elseif ( isset( $cell->v ) ) {
					$value = (string) $cell->v;
				}
				$row_values[ $index ] = $value;
			}
			$rows_matrix[] = $this->normalize_sparse_xlsx_row( $row_values );
		}

		if ( empty( $rows_matrix ) ) {
			return array( 'headers' => array(), 'rows' => array() );
		}

		$headers = array_map( array( $this, 'normalize_header_value' ), array_map( 'strval', $rows_matrix[0] ) );

		if ( count( $headers ) > self::MAX_IMPORT_COLUMNS ) {
			return array( 'headers' => array(), 'rows' => array() );
		}

		$header_count = count( $headers );

		return array(
			'headers' => $headers,
			'rows'    => array_map(
				static function( array $row ) use ( $header_count ): array {
					$row = array_map( 'strval', $row );
					if ( count( $row ) < $header_count ) {
						$row = array_pad( $row, $header_count, '' );
					}

					return array_slice( $row, 0, $header_count );
				},
				array_slice( $rows_matrix, 1 )
			),
		);
	}

	private function normalize_sparse_xlsx_row( array $row_values ): array {
		if ( empty( $row_values ) ) {
			return array();
		}

		ksort( $row_values );
		$max_index  = min( max( array_map( 'intval', array_keys( $row_values ) ) ), self::MAX_IMPORT_COLUMNS - 1 );
		$normalized = array();

		for ( $index = 0; $index <= $max_index; $index++ ) {
			$normalized[] = isset( $row_values[ $index ] ) ? (string) $row_values[ $index ] : '';
		}

		return $normalized;
	}

	private function safe_simplexml_load_string( string $xml ) {
		$previous = libxml_use_internal_errors( true );
		$doc      = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $doc;
	}

	private function is_identity_header( string $header ): bool {
		return in_array(
			$header,
			array(
				'target_type', 'content_scope', 'target_id', 'target_title', 'target_slug', 'page_id', 'page_title',
				'post_id', 'item_id', 'item_titel', 'item_slug', 'slug', 'pagina_id', 'pagina_titel',
				'language_code', 'lang', 'locale', 'taal', 'taalcode',
			),
			true
		);
	}

	private function header_to_label( string $header ): string {
		$label = str_replace( '_', ' ', $header );
		return ucwords( trim( $label ) );
	}

	private function normalize_header_value( string $value ): string {
		$value = preg_replace( '/^\xEF\xBB\xBF/u', '', $value );
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9_]+/u', '_', (string) $value );
		return trim( (string) $value, '_' );
	}

	private function is_empty_csv_row( array $row ): bool {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	private function cell_ref_to_index( string $ref ): int {
		$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
		$index = 0;
		for ( $i = 0, $length = strlen( $letters ); $i < $length; $i++ ) {
			$index = ( $index * 26 ) + ( ord( $letters[ $i ] ) - 64 );
		}
		return max( 0, $index - 1 );
	}
}
