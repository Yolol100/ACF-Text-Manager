<?php
namespace WA_ACF_PTM\Admin\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WA_ACF_PTM_PATH . 'includes/admin/services/traits/special-field-definitions-trait.php';
require_once WA_ACF_PTM_PATH . 'includes/admin/services/traits/special-field-value-trait.php';
require_once WA_ACF_PTM_PATH . 'includes/admin/services/traits/special-field-image-trait.php';

final class Special_Field_Service {
	use Special_Field_Definitions_Trait, Special_Field_Value_Trait, Special_Field_Image_Trait;

	private Field_Value_Service $values;
	private const IMAGE_META_KEYS = array( 'file_name', 'alt', 'title', 'caption', 'description' );
	private const ALLOWED_POST_STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );
	private const YOAST_META_KEYS = array(
		'_yoast_wpseo_focuskw'                 => array( 'type' => 'text' ),
		'_yoast_wpseo_title'                   => array( 'type' => 'text' ),
		'_yoast_wpseo_metadesc'                => array( 'type' => 'textarea' ),
		'_yoast_wpseo_canonical'               => array( 'type' => 'url' ),
		'_yoast_wpseo_opengraph-title'         => array( 'type' => 'text' ),
		'_yoast_wpseo_opengraph-description'   => array( 'type' => 'textarea' ),
		'_yoast_wpseo_twitter-title'           => array( 'type' => 'text' ),
		'_yoast_wpseo_twitter-description'     => array( 'type' => 'textarea' ),
	);
	private const RANK_MATH_META_KEYS = array(
		'rank_math_title'         => array( 'type' => 'text' ),
		'rank_math_description'   => array( 'type' => 'textarea' ),
		'rank_math_focus_keyword' => array( 'type' => 'text' ),
		'rank_math_canonical_url' => array( 'type' => 'url' ),
	);

	public function __construct( ?Field_Value_Service $values = null ) {
		$this->values = $values ?? new Field_Value_Service();
	}

}
