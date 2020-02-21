<?php

// global $APF_view_type;
$APF_porch_slots = array(
    1,
    2,
    3,
    4
);
$APF_porch_slot_key = array(
    'status_of_slot' => array(),
    'perf_times' => array(),
    'band_name' => array(),
    'band_link' => array()
);

function APF_init_parameters()
{
    // global $APF_view_type;
    global $APF_porch_slots;
    global $APF_porch_slot_key;
    global $APF_max_slot;
    global $APF_looking_for_band;
    global $APF_looking_for_exhibit;
    global $APF_looking_for_porch;
    global $APF_scheduled_performance;
    global $APF_scheduled_exhibit;
    
    /*
     *
     * if (!isset($APF_view_type)) {
     * $APF_view_type = 'excerpt';
     * }
     */
    
    $APF_porch_slots = array(
        1,
        2,
        3,
        4
    );
    $APF_max_slot = 4;
    $APF_looking_for_band = 55;
    $APF_looking_for_exhibit = 56;
    $APF_looking_for_porch = 57;
    $APF_scheduled_performance = 43;
    $APF_scheduled_exhibit = 58;
    $any_porches = get_posts(array(
        'numberposts' => 1,
        'post_type' => 'porch',
        'post_status' => 'publish'
    ));
    if (! empty($any_porches)) {
        $any_porch = $any_porches[0]->ID;
    } else {
        $any_porch = 0;
    }
    foreach ($APF_porch_slots as $slot) {
        $field_key = acf_get_field_key('status_of_slot_' . $slot, $any_porch);
        $APF_porch_slot_key['status_of_slot'][$slot] = $field_key;
        $field_key = acf_get_field_key('perf_times_' . $slot, $any_porch);
        $APF_porch_slot_key['perf_times'][$slot] = $field_key;
        $field_key = acf_get_field_key('band_name_' . $slot, $any_porch);
        $APF_porch_slot_key['band_name'][$slot] = $field_key;
        $field_key = acf_get_field_key('band_link_' . $slot, $any_porch);
        $APF_porch_slot_key['band_link'][$slot] = $field_key;
    }
}
add_action('init', 'APF_init_parameters');

/*
 * Get field value from a porch slot
 * Argument $post_id is optional
 */
function APF_get_field($name, $slot, $post_id = 0)
{
    global $APF_porch_slots;
    if (in_array($slot, $APF_porch_slots)) {
        $field_name = $name . '_' . $slot;
        if ($post_id > 0) {
            return get_field($field_name, $post_id);
        } else {
            return get_field($field_name);
        }
    }
    return False;
}

/*
 * Update field value in a porch slot
 * Argument $post_id is optional
 */
function APF_update_field($name, $slot, $value, $post_id = 0)
{
    global $APF_porch_slots;
    if (in_array($slot, $APF_porch_slots)) {
        $field_name = $name . '_' . $slot;
        if ($post_id > 0) {
            return update_field($field_name, $value, $post_id);
        } else {
            return update_field($field_name, $value);
        }
    }
    return False;
}
 
/*
 * Include porch and band content types in standard wp search pages
 * And give unlimited posts per page for map and table views
 */
function search_for_porches_and_bands($query)
{
    // do not modify queries in the admin
    if (is_admin()) {
        return;
    }

    if (($query->is_main_query() && isset($_GET['post_types'])) ||  $query->is_archive() || $query->is_search() ||  $query->is_tag() || $query->is_category() || $query->is_author()) {
        $query->set('post_type', array('porch', 'band', 'exhibit'));
        $orderby = 'num';
        if (isset($_GET['apf-orderby'])) {
            $orderby = $_GET['apf-orderby'];
        }
        // Sort numerically (by custom code) or alphabetically (by cleaned post name)
        if ('num' == $orderby) {
            $query->set('orderby', array('meta_value_num'=>'ASC', 'post_type'=>'DESC'));
            $query->set('meta_key', 'num_order_code');
        } elseif ('alpha' == $orderby) {
            $query->set('orderby', 'meta_value');
            $query->set('meta_key', 'title_for_sorting');
            $query->set('order', 'ASC');
        } elseif ('new' == $orderby) {
            $query->set('meta_key', 'marker_label');
            $query->set('meta_value', '9999');
        }
        if (isset($_GET['view'])) {
            $view_type = $_GET['view'];
            if (($view_type == 'map') || ($view_type == 'table') || ($view_type == 'pins')) {
                set_query_var('posts_per_page', 999);
                if ($view_type == 'map') {
                    $query->set('orderby', 'post_type');
                    $query->set('order', 'ASC');
                }
            }
        }
    }
}
add_action('pre_get_posts', 'search_for_porches_and_bands');

/*
 * Make porch addresses short and Arlington-centric
 */
function APF_shorten_address($address)
{
    $things_to_eliminate = array(
        '/\,*\s*USA/',
        '/\,*\s*MA/',
        '/\,*\s*Arlington/',
        '/\,*\s*\d{5}(?:[-\s]\d{4})?/'
    );
    $abbreviations = array(
        '/Massachusetts/' => 'Mass',
        '/Avenue/' => 'Ave',
        '/Street/' => 'St',
        '/Place/' => 'Pl',
        '/Road/' => 'Rd',
        '/Court/' => 'Ct',
        '/Circle/' => 'Cir',
        '/Drive/' => 'Dr',
        '/Parkway/' => 'Pkwy',
        '/Square/' => 'Sq',
        '/Terrace/' => 'Ter'
    );
    $shortened = preg_replace($things_to_eliminate, '', $address);
    foreach ($abbreviations as $key => $value) {
        $shortened = preg_replace($key, $value, $shortened);
    }
    if ($shortened) {
        return $shortened;
    }
    return $address;
}

/*
 * Porchfest update messages. Adapted from standard WP messages
 */
function APF_updated_messages($messages)
{
    $post = get_post();
    $post_type = get_post_type($post);
    $post_type_object = get_post_type_object($post_type);
    
    /*
     * See wp-admin/edit-form-advanced.php
     */
    $permalink = get_permalink($post->ID);
    if (! $permalink) {
        $permalink = '';
    }
    $preview_post_link_html = $view_post_link_html = '';
    $preview_url = get_preview_post_link($post);
    // Preview post link.
    $preview_post_link_html = sprintf(' <a target="_blank" href="%1$s">%2$s</a>', esc_url($preview_url), __('Preview listing'));
    // View post link.
    $view_post_link_html = sprintf(' <a href="%1$s">%2$s</a>', esc_url($permalink), __('View listing'));
    
    if (($post_type == 'band') || ($post_type == 'exhibit')) {
        if ($post->post_status == 'draft') {
            $messages[$post_type] = array(
                1 => 'DRAFT ' . $post_type . ' listing still not published',
                4 => 'DRAFT ' . $post_type . ' listing still not published',
                6 => 'DRAFT ' . $post_type . ' listing not yet published',
                7 => 'DRAFT ' . $post_type . ' listing saved.',
                10 => 'DRAFT ' . $post_type . ' draft listing updated.'
            );
        } else {
            $messages[$post_type] = array(
                1 => ucfirst($post_type) . ' listing updated.' . $view_post_link_html,
                4 => ucfirst($post_type) . ' listing updated.',
                6 => ucfirst($post_type) . ' listing published.' . $view_post_link_html,
                7 => ucfirst($post_type) . ' listing saved.',
                10 => ucfirst($post_type) . ' draft listing updated.' . $preview_post_link_html
            );
        }
    } elseif ($post_type == 'porch') {
        $messages['porch'] = array(
            1 => 'Porch listing updated.' . $view_post_link_html,
            4 => 'Porch listing updated.',
            6 => 'Porch listing published.' . $view_post_link_html,
            7 => 'Porch listing saved.',
            10 => 'Porch draft listing updated.' . $preview_post_link_html
        );
    }
    return $messages;
}
add_filter('post_updated_messages', 'APF_updated_messages');

/*
 * Filter UI select box so that only published listings appear
 */
function APF_select_only_published($options, $field, $the_post)
{
    $options['post_status'] = array(
        'publish'
    );
    return $options;
}
add_filter('acf/fields/post_object/query/name=band_link_1', 'APF_select_only_published', 10, 3);
add_filter('acf/fields/post_object/query/name=band_link_2', 'APF_select_only_published', 10, 3);
add_filter('acf/fields/post_object/query/name=band_link_3', 'APF_select_only_published', 10, 3);
add_filter('acf/fields/post_object/query/name=band_link_4', 'APF_select_only_published', 10, 3);
add_filter('acf/fields/post_object/query/name=exhibit_link', 'APF_select_only_published', 10, 3);

/**
 * advanced custom fields
 * Get field key for field name.
 * Will return first matched acf field key for a give field name.
 *
 * ACF somehow requires a field key, where a sane developer would prefer a human readable field name.
 * http://www.advancedcustomfields.com/resources/update_field/#field_key-vs%20field_name
 *
 * This function will return the field_key of a certain field.
 *
 * @param $field_name String
 *            ACF Field name
 * @param $post_id int
 *            The post id to check.
 * @return
 */
function acf_get_field_key($field_name, $post_id)
{
    global $wpdb;
    $acf_fields = $wpdb->get_results($wpdb->prepare("SELECT ID,post_parent,post_name FROM $wpdb->posts WHERE post_excerpt=%s AND post_type=%s", $field_name, 'acf-field'));
    // get all fields with that name.
    switch (count($acf_fields)) {
        case 0: // no such field
            return false;
        case 1: // just one result.
            return $acf_fields[0]->post_name;
    }
    // result is ambiguous
    // get IDs of all field groups for this post
    $field_groups_ids = array();
    $field_groups = acf_get_field_groups(array(
        'post_id' => $post_id
    ));
    foreach ($field_groups as $field_group)
        $field_groups_ids[] = $field_group['ID'];
    
    // Check if field is part of one of the field groups
    // Return the first one.
    foreach ($acf_fields as $acf_field) {
        if (in_array($acf_field->post_parent, $field_groups_ids))
            return $acf_field->post_name;
    }
    return false;
}
