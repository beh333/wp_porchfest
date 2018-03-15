<?php

/**
 * @package PorchFest_PLUS
 * @version 0.9
 */

/*
 * Plugin Name: Porchfest PLUS
 * Plugin URI:
 * Description: This plugin automagically sets various fields to keep porches and bands in synch.
 * In particular:
 * 1. Porch title <= porch address
 * 2. Porch slot status, times, and band info are kept consistent (e.g., status NA => clear the rest)
 * 3. Band time/category <= porch time/category, when the porch has linked to the band
 * 4. Default search is on porch and band post types (including tag and category archives)
 *
 * Author: Bruce Hoppe
 * Version: 0.9
 * Author URI:
 */
function search_for_porches_and_bands($wp_query)
{
    if (is_search() || is_tag() || is_category() || is_author()) {
        set_query_var('post_type', get_query_var('post_type', array(
            'porch',
            'band'
        )));
    }
}
add_action('pre_get_posts', 'search_for_porches_and_bands');

function APF_post_beforesaving($post_id)
{
    if (! $_POST['acf']) {
        return;
    }
    
    $looking_id = 47;
    
    APF_update_POST_title($post_id);
    
    if ($_POST['post_type'] == 'porch') {
        // clear out band schedules so we don't leave orphans
        $band_post = get_field('band_link_1'); // gets old value before $_POST
        if ($band_post) {
            wp_set_post_categories($band_post->ID, array(
                $looking_id
            ), False);
        }
        $band_post = get_field('band_link_2'); // gets old value
        if ($band_post) {
            wp_set_post_categories($band_post->ID, array(
                $looking_id
            ), False);
        }
    }
}
add_action('acf/save_post', 'APF_post_beforesaving', 2);

function APF_post_aftersaving($post_id)
{
    $this_post = get_post($post_id);
    switch ($this_post->post_type) {
        case 'band':
            
            return;
    }
    
    $status_1 = get_field('status_of_slot_1', $this_post->ID);
    $status_2 = get_field('status_of_slot_2', $this_post->ID);
    
    $looking_id = 47;
    
    if (($status_1 == 'Looking for a band') || ($status_2 == 'Looking for a band')) {
        $results = wp_set_post_categories($this_post->ID, array(
            $looking_id
        ), False);
    } else {
        $results = wp_set_post_categories($this_post->ID, array(), False);
    }
    
    // update porch categories based on perf_times
    $terms_1 = get_field('perf_times_1');
    $terms_2 = get_field('perf_times_2');
    foreach ($terms_1 as $t1) {
        $results = wp_set_post_categories($this_post->ID, array(
            $t1
        ), True);
    }
    foreach ($terms_2 as $t2) {
        $results = wp_set_post_categories($this_post->ID, array(
            $t2
        ), True);
    }
    
    // If no performance times are selected then slot 2 status is NA (unused)
    if (! $terms_2) {
        update_field('status_of_slot_2', 'NA');
    }
    
    switch ($status_1) {
        case 'Have a band':
            update_field('band_name_1', '');
            $band_post_id = get_field('band_link_1');
            $band_post = get_post($band_post_id);
            APF_schedule_band($band_post, $terms_1);
            break;
        case 'Have an unlisted band':
            update_field('band_link_1', null);
            break;
        case 'NA':
            // Slot 1 can't have status NA
            break;
    }
    
    switch ($status_2) {
        case 'Have a band':
            update_field('band_name_2', '');
            $band_post_id = get_field('band_link_2');
            $band_post = get_post($band_post_id);
            APF_schedule_band($band_post, $terms_2);
            break;
        case 'Have an unlisted band':
            update_field('band_link_2', null);
            break;
        case 'NA':
            update_field('band_name_2', '');
            update_field('band_link_2', null);
            update_field('perf_times_2', null);
            break;
    }
}
add_action('acf/save_post', 'APF_post_aftersaving', 20);

function APF_update_POST_title($post_id)
{
    $map_marker_key = 'field_5aa4febaecbb3';
    
    if ($_POST['post_type'] == 'band') {
        $my_post = array(
            'ID' => $post_id,
            'post_name' => sanitize_title($_POST['post_title'])
        );
        wp_update_post($my_post);
    } elseif ($_POST['post_type'] == 'porch') {
        $map_marker = $_POST['acf'][$map_marker_key];
        if (! $map_marker) {
            return;
        }
        $address = APF_shorten_address($map_marker['address']);
        $my_post = array(
            'ID' => $post_id,
            'post_title' => $address,
            'post_name' => sanitize_title($address)
        );
        wp_update_post($my_post);
    }
}

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

function APF_schedule_band($band_post, $terms)
{
    if ($band_post) {
        wp_set_post_categories($band_post->ID, array(), False);
        foreach ($terms as $term) {
            wp_set_post_categories($band_post->ID, array(
                $term
            ), True);
        }
    }
}

function APF_validate_map_marker($valid, $value, $field, $input)
{
    
    // bail early if value is already invalid
    if (! $valid) {
        return $valid;
    }
    
    $current_id = $_POST['post_ID'];
    $address = APF_shorten_address($value['address']);
    if ($address) {
        $same_title = get_posts(array(
            'numberposts' => - 1,
            'post_type' => 'porch',
            'title' => $address,
            'exclude' => array(
                $current_id
            )
        ));
        if ($same_title) {
            $valid = 'Another porch already registered at ' . $address;
        }
    }
    return $valid;
}
add_filter('acf/validate_value/name=map_marker', 'APF_validate_map_marker', 10, 4);

function APF_validate_slot($valid, $value, $field, $input)
{
    
    // bail early if value is already invalid
    if (! $valid) {
        return $valid;
    }
    
    $perf_times_1 = $value;
    
    $status_of_slot_2_key = acf_get_field_key('status_of_slot_2', $_POST['ID']);
    $status_of_slot_2 = $_POST['acf'][$status_of_slot_2_key];
    
    if (($status_of_slot_2 == 'Have a band') || ($status_of_slot_2 == 'Have an unlisted band') || ($status_of_slot_2 == 'Looking for a band')) {
        $perf_times_2_key = acf_get_field_key('perf_times_2', $_POST['ID']);
        $perf_times_2 = $_POST['acf'][$perf_times_2_key];
        foreach ($perf_times_1 as &$time1) {
            foreach ($perf_times_2 as &$time2) {
                if ($time2 == $time1) {
                    $valid = get_term_by('id', $time1, 'category')->name . ' cannot be selected in two different slots';
                    return $valid;
                }
            }
            unset($time2);
        }
        unset($time1);
    }
    return $valid;
}
add_filter('acf/validate_value/name=perf_times_1', 'APF_validate_slot', 10, 4);

function APF_get_band_host($band_id, $band_name, $method, $exclude)
{
    switch ($method) {
        case 'by_name':
            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key' => 'band_name_1',
                    'value' => sanitize_title($band_name),
                    'compare' => '='
                ),
                array(
                    'key' => 'band_name_2',
                    'value' => sanitize_title($band_name),
                    'compare' => '='
                )
            );
            break;
        case 'by_link':
            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key' => 'band_link_1',
                    'value' => $band_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'band_link_2',
                    'value' => $band_id,
                    'compare' => '='
                )
            );
            break;
        default:
            return false;
    }
    
    $porch_posts = get_posts(array(
        'numberposts' => - 1,
        'post_type' => 'porch',
        'exclude' => $exclude,
        'meta_query' => $meta_query
    ));
    
    if (empty($porch_posts)) {
        return false;
    } else {
        return $porch_posts[0];
    }
}

function APF_validate_match($valid, $value, $field, $input)
{
    
    // bail early if value is already invalid
    if (! $valid || ($value == '')) {
        return $valid;
    }
    
    if (($field['name'] == 'band_name_1') || ($field['name'] == 'band_name_2')) {
        $band_name = $value;
        $band_post = null;
        $same_name_band_posts = get_posts(array(
            'numberposts' => - 1,
            'post_type' => 'band',
            'name' => sanitize_title($band_name)
        ));
    } elseif (($field['name'] == 'band_link_1') || ($field['name'] == 'band_link_2')) {
        $band_post = get_post($value);
        $band_name = $band_post->post_title;
        $same_name_band_posts = get_posts(array(
            'numberposts' => - 1,
            'post_type' => 'band',
            'name' => sanitize_title($band_name),
            'exclude' => array(
                $band_post->ID
            )
        ));
    } else {
        return $valid;
    }
    
    $porch_posts = null;
    
    if ($same_name_band_posts) {
        $porch_posts = array(
            APF_get_band_host($same_name_band_posts[0], array(
                $_POST['post_ID']
            ))
        );
        if (empty($porch_posts)) {
            $valid = $value . ' already registered. Please link to their listing';
            return $valid;
        }
    }
    if (empty($porch_posts)) {
        $porch_posts = get_posts(array(
            'numberposts' => 1,
            'post_type' => 'porch',
            'meta_key' => 'band_name_1',
            'meta_value' => sanitize_title($band_name),
            'exclude' => array(
                $_POST['post_ID']
            )
        ));
    }
    if (empty($porch_posts)) {
        $porch_posts = get_posts(array(
            'numberposts' => 1,
            'post_type' => 'porch',
            'meta_key' => 'band_name_2',
            'meta_value' => sanitize_title($band_name),
            'exclude' => array(
                $_POST['post_ID']
            )
        ));
    }
    if (empty($porch_posts) && ($band_post != null)) {
        $porch_posts = get_posts(array(
            'numberposts' => 1,
            'post_type' => 'porch',
            'meta_key' => 'band_link_1',
            'meta_value' => $band_post->ID,
            'exclude' => array(
                $_POST['post_ID']
            )
        ));
    }
    if (empty($porch_posts) && ($band_post != null)) {
        $porch_posts = get_posts(array(
            'numberposts' => 1,
            'post_type' => 'porch',
            'meta_key' => 'band_link_2',
            'meta_value' => $band_post->ID,
            'exclude' => array(
                $_POST['post_ID']
            )
        ));
    }
    
    if ($porch_posts) {
        $valid = $band_name . ' already scheduled at ' . $porch_posts[0]->post_title;
    }
    
    return $valid;
}
add_filter('acf/validate_value/name=band_name_1', 'APF_validate_match', 10, 4);
add_filter('acf/validate_value/name=band_name_2', 'APF_validate_match', 10, 4);
add_filter('acf/validate_value/name=band_link_1', 'APF_validate_match', 10, 4);
add_filter('acf/validate_value/name=band_link_2', 'APF_validate_match', 10, 4);

/*
 * function APF_validate_band_status( $valid, $value, $field, $input ) {
 * $name = sanitize_title( $_POST['post_title'] );
 * $current_id = $_POST['post_ID'];
 *
 * $band_post = get_post( $current_id );
 * if( !$band_post ) {
 * return $valid;
 * }
 *
 * $host = APF_get_band_host( $band_post, array() );
 *
 * if( $host ) {
 * if( $value == 'Looking for a porch' ) {
 * $valid = $host->post_title . ' says they are hosting ' . $band_post->post_title;
 * }
 * } else {
 * if( $value == 'Have a porch' ) {
 * $valid = 'No host yet for ' . $band_post->post_title . '. Please ask your host to register and link to your listing.';
 * }
 * }
 * return $valid;
 * }
 */
// add_filter('acf/validate_value/name=status_of_band_planning', 'APF_validate_band_status', 10, 4);
function APF_google_map_api($api)
{
    $api['key'] = 'AIzaSyBhyEivpzqgJYnsFQIzpp9zAelI3kh6MN0';
    return $api;
}
add_filter('acf/fields/google_map/api', 'APF_google_map_api');

function APF_validate_band_post_name($messages)
{
    global $wpdb;
    global $post;
    
    $name = sanitize_title($post->post_title);
    $current_id = $post->ID;
    
    $wtitlequery = "SELECT post_name FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'band' AND post_name = '{$name}' AND ID != {$current_id} ";
    
    $wresults = $wpdb->get_results($wtitlequery);
    if ($wresults) {
        $error_message = $post->post_title . ' already registered';
        add_settings_error('post_has_links', '', $error_message, 'error');
        settings_errors('post_has_links');
        $post->post_status = 'draft';
        wp_update_post($post);
        return;
    }
    return $messages;
}
add_action('post_updated_messages', 'APF_validate_band_post_name');

function APF_select_only_published($options, $field, $the_post)
{
    $options['post_status'] = array(
        'publish'
    );
    return $options;
}
add_filter('acf/fields/post_object/query/name=band_link_1', 'APF_select_only_published', 10, 3);
add_filter('acf/fields/post_object/query/name=band_link_2', 'APF_select_only_published', 10, 3);

/**
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

function APF_save_band($post_id)
{
    $post = get_post($post_id);
    // If new band, check for a host
    if (! $update) {
        $host = APF_get_band_host($post, array());
        ?><pre><?php var_dump( $host ); exit(); ?></pre><?php
        if ($host) {
            $band_name_1 = get_field('band_name_1', $host->ID);
            $band_name_2 = get_field('band_name_2', $host->ID);
            if (sanitize_title($band_name_1) == $post->post_name) {
                $terms_1 = get_field('perf_times_1', $host->ID);
                foreach ($terms_1 as $t1) {
                    $results = wp_set_post_categories($post_id, array(
                        $t1
                    ), True);
                }
            } elseif (sanitize_title($band_name_2) == $post->post_name) {
                $terms_2 = get_field('perf_times_2', $host->ID);
                foreach ($terms_2 as $t2) {
                    $results = wp_set_post_categories($post_id, array(
                        $t2
                    ), True);
                }
            } else {
                wp_set_post_categories($post_id, array(
                    47
                ), False);
            }
        } else {
            wp_set_post_categories($post_id, array(
                47
            ), False);
        }
    }
}

// add_action( 'save_post', 'APF_save_band', 20, 3 );

?>