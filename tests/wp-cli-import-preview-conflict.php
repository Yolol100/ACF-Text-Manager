<?php
/**
 * Reproduce a stale import preview overwriting an editor's later ACF update.
 *
 * Only synthetic data inside the disposable WordPress/MySQL CI installation.
 * No website, media, external service or real customer value is accessed.
 */

if ( ! function_exists( 'acf_add_local_field_group' ) || ! function_exists( 'update_field' ) ) {
    throw new RuntimeException( 'ACF is not loaded in the isolated test runtime.' );
}

wp_set_current_user( 1 );
$group_key = 'group_wa_ptm_preview_ci';
$field_key = 'field_wa_ptm_preview_ci';
$field_name = 'wa_ptm_preview_ci';
acf_add_local_field_group( array(
    'key' => $group_key,
    'title' => 'Temporary preview conflict test',
    'fields' => array(
        array( 'key' => $field_key, 'label' => 'CI preview', 'name' => $field_name, 'type' => 'text' ),
    ),
    'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ) ),
) );

$post_id = wp_insert_post( array(
    'post_type' => 'page',
    'post_title' => 'Isolated ACF preview collision',
    'post_status' => 'publish',
), true );
if ( is_wp_error( $post_id ) || ! $post_id ) {
    throw new RuntimeException( 'Failed to create synthetic ACF target.' );
}

$plan_store = new \WA_ACF_PTM\Admin\Import_Plan_Store();
$processor = new \WA_ACF_PTM\Admin\Services\Import_Processor(
    $plan_store,
    new \WA_ACF_PTM\Admin\Page_Repository()
);
$tokens = array();

try {
    $scenarios = array(
        array( 'label' => 'stale preview after editor change', 'planned' => 'original', 'current' => 'editor-update', 'new' => 'import-target', 'expect' => 'editor-update', 'updated' => 0 ),
        array( 'label' => 'unchanged preview', 'planned' => 'original', 'current' => 'original', 'new' => 'import-target', 'expect' => 'import-target', 'updated' => 1 ),
        array( 'label' => 'preview target already applied', 'planned' => 'original', 'current' => 'import-target', 'new' => 'import-target', 'expect' => 'import-target', 'updated' => 1 ),
    );

    foreach ( $scenarios as $scenario ) {
        update_field( $field_key, $scenario['planned'], $post_id );
        $values = new \WA_ACF_PTM\Admin\Services\Field_Value_Service();
        $snapshot = $values->get_current_field_value( $field_name, $post_id, $field_key );
        if ( $snapshot !== $scenario['planned'] ) {
            throw new RuntimeException( 'ACF preview snapshot did not match test setup.' );
        }

        $token = wp_generate_uuid4();
        $tokens[] = $token;
        $plan_store->save( $token, array(
            'user_id' => get_current_user_id(),
            'import_allowed' => true,
            'operations' => array( array(
                'row_index' => 2,
                'action' => 'update',
                'acf_post_id' => $post_id,
                'target_type' => 'page',
                'content_scope' => 'page',
                'target_id' => (string) $post_id,
                'field_key' => $field_key,
                'field_name' => $field_name,
                'field_label' => 'CI preview',
                'field_type' => 'text',
                'is_special' => false,
                'new_value' => $scenario['new'],
                'current_value' => $snapshot,
            ) ),
            'cursor' => 0,
            'updated_count' => 0,
            'summary' => array( 'skipped_count' => 0, 'warnings' => array() ),
        ) );

        update_field( $field_key, $scenario['current'], $post_id );
        $result = $processor->process( $token );
        $actual = $values->get_current_field_value( $field_name, $post_id, $field_key );
        if ( empty( $result['done'] ) || $actual !== $scenario['expect'] || (int) ( $result['updated_count'] ?? -1 ) !== $scenario['updated'] ) {
            throw new RuntimeException(
                'Stale ACF import preview conflict in ' . $scenario['label']
                . ': result=' . wp_json_encode( $result )
                . ', field=' . $actual
            );
        }
    }
    echo "ACF import preview conflict and valid update: OK\n";
} finally {
    foreach ( $tokens as $token ) {
        $plan_store->delete( $token );
    }
    wp_delete_post( (int) $post_id, true );
}
