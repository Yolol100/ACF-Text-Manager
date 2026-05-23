<?php
namespace WA_ACF_PTM\Admin;

use WA_ACF_PTM\Admin\Traits\Page_Repository_ACF_Trait;
use WA_ACF_PTM\Admin\Traits\Page_Repository_Index_Trait;
use WA_ACF_PTM\Admin\Traits\Page_Repository_Summary_Trait;
use WA_ACF_PTM\Admin\Traits\Page_Repository_Target_Data_Trait;

use WA_ACF_PTM\Admin\Services\Field_Value_Service;
use WA_ACF_PTM\Admin\Services\Special_Field_Service;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


final class Page_Repository {
	use Page_Repository_Index_Trait, Page_Repository_Target_Data_Trait, Page_Repository_Summary_Trait, Page_Repository_ACF_Trait;


	private const CACHE_KEY = 'wa_acf_ptm_target_index_v12';
	private const DETAIL_CACHE_PREFIX = 'wa_acf_ptm_target_detail_';


	private Field_Value_Service $field_values;
	private Special_Field_Service $special_fields;

	private array $supported_field_types = array(
		'text',
		'textarea',
		'wysiwyg',
		'image',
		'number',
		'range',
		'email',
		'url',
		'select',
		'true_false',
		'date_picker',
		'group',
		'file',
		'link',
		'post_object',
		'relationship',
		'gallery',
		'checkbox',
		'radio',
		'button_group',
		'oembed',
		'page_link',
		'taxonomy',
		'user',
		'google_map',
		'date_time_picker',
		'time_picker',
		'color_picker',
		'message',
	);


	public function __construct( ?Field_Value_Service $field_values = null, ?Special_Field_Service $special_fields = null ) {
		$this->field_values   = $field_values ?? new Field_Value_Service();
		$this->special_fields = $special_fields ?? new Special_Field_Service( $this->field_values );
	}

}
