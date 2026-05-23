<?php
namespace WA_ACF_PTM\Admin;

use WA_ACF_PTM\Admin\Traits\CSV_Service_Export_Trait;
use WA_ACF_PTM\Admin\Traits\CSV_Service_Import_Trait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


final class CSV_Service {
	use CSV_Service_Export_Trait, CSV_Service_Import_Trait;

	private const MAX_IMPORT_ROWS = 5000;
	private const MAX_IMPORT_COLUMNS = 200;
	private const MAX_XLSX_XML_BYTES = 5242880;

}
