<?php
namespace WA_ACF_PTM\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WA_ACF_PTM_PATH . 'includes/admin/traits/csv-service-export-trait.php';
require_once WA_ACF_PTM_PATH . 'includes/admin/traits/csv-service-import-trait.php';

final class CSV_Service {
	use CSV_Service_Export_Trait, CSV_Service_Import_Trait;

	private const MAX_IMPORT_ROWS = 5000;
	private const MAX_IMPORT_COLUMNS = 200;
	private const MAX_XLSX_XML_BYTES = 5242880;

}
