<?php

/**
 * @package PorchFest_PLUS
 * @version 1.0
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
 * Version: 1.0
 * Author URI:
 */
include_once 'googlemaps_api.php';

/*
 * Include porch and band content types in standard search pages
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

/*
 * Right before saving updated porch info,
 * Make sure not to leave orphans in the schedule
 */
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
            update_field('porch_link', null, $band_post->ID);
            update_field('porch_address', '', $band_post->ID);
            wp_set_post_categories($band_post->ID, array(
                $looking_id
            ), False);
        }
        $band_post = get_field('band_link_2'); // gets old value
        if ($band_post) {
            update_field('porch_link', null, $band_post->ID);
            update_field('porch_address', '', $band_post->ID);
            wp_set_post_categories($band_post->ID, array(
                $looking_id
            ), False);
        }
    }
}
add_action('acf/save_post', 'APF_post_beforesaving', 2);

/*
 * As part of pre-save, set title and name
 * Porch title/name = sanitized & shortened address.
 * Always update band name/slug based on its title
 */
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
 * Clean up fields for consistency right after saving a porch or band.
 * New band gets autolinked to a host that had named it on its schedule.
 */
function APF_post_aftersaving($post_id)
{
    $this_post = get_post($post_id);
    
    /*
     * BAND: Auto register new band if host has named it already
     */
    if ($this_post->post_type == 'band') {
        $host = APF_get_band_host($post_id, $this_post, 'by_name', array());
        if ($host) {
            $band_name_1 = get_field('band_name_1', $host->ID);
            $band_name_2 = get_field('band_name_2', $host->ID);
            if (sanitize_title($band_name_1) == $this_post->post_name) {
                $terms_1 = get_field('perf_times_1', $host->ID);
                APF_schedule_band($this_post, $host, $terms_1, True);
                // Update host info: band is linked, not named
                update_field('band_name_1', '', $host->ID);
                update_field('band_link_1', $post_id, $host->ID);
                update_field('status_of_slot_1', 'Have a band', $host->ID);
                return;
            } elseif (sanitize_title($band_name_2) == $this_post->post_name) {
                $terms_2 = get_field('perf_times_2', $host->ID);
                APF_schedule_band($this_post, $host, $terms_2, True);
                // Update host info: band is linked, not named
                update_field('band_name_2', '', $host->ID);
                update_field('band_link_2', $post_id, $host->ID);
                update_field('status_of_slot_2', 'Have a band', $host->ID);
                return;
            }
        } /*
           * Otherwise categorize band 'Looking for a match'
           */
        else {
            wp_set_post_categories($post_id, array(
                47
            ), False);
            return;
        }
    } /*
       * HOST: Lots of housekeeping to keep fields consistent
       */
    elseif ($this_post->post_type == 'porch') {
        
        $status_1 = get_field('status_of_slot_1', $this_post->ID);
        $status_2 = get_field('status_of_slot_2', $this_post->ID);
        
        $looking_id = 47;
        
        // Initialize porch slot status either "Looking" or empty
        if (($status_1 == 'Looking for a band') || ($status_2 == 'Looking for a band')) {
            $results = wp_set_post_categories($this_post->ID, array(
                $looking_id
            ), False);
        } else {
            $results = wp_set_post_categories($this_post->ID, array(), False);
        }
        
        // And then update porch categories based on perf_times
        $terms_1 = get_field('perf_times_1');
        $terms_2 = get_field('perf_times_2');
        foreach ($terms_1 as $t1) {
            $results = wp_set_post_categories($this_post->ID, array(
                $t1
            ), True);
        }
        if ($terms_2) {
            foreach ($terms_2 as $t2) {
                $results = wp_set_post_categories($this_post->ID, array(
                    $t2
                ), True);
            }
        }
    }
    
    // If no performance times are selected then slot 2 status is NA (unused)
    if (! $terms_2) {
        update_field('status_of_slot_2', 'NA');
    }
    /*
     * Update band info based on slot 1
     */
    switch ($status_1) {
        case 'Have a band':
            update_field('band_name_1', '');
            $band_post_id = get_field('band_link_1');
            $band_post = get_post($band_post_id);
            APF_schedule_band($band_post, $this_post, $terms_1, True);
            break;
        case 'Have an unlisted band':
            update_field('band_link_1', null);
            break;
        case 'Looking for a band':
            update_field('band_name_1', '');
            update_field('band_link_1', null);
            break;
        case 'NA':
            // Slot 1 can't have status NA
            break;
    }
    /*
     * Update band info based on slot 2
     */
    switch ($status_2) {
        case 'Have a band':
            update_field('band_name_2', '');
            $band_post_id = get_field('band_link_2');
            $band_post = get_post($band_post_id);
            APF_schedule_band($band_post, $this_post, $terms_2, True);
            break;
        case 'Have an unlisted band':
            update_field('band_link_2', null);
            break;
        case 'Looking for a band':
            update_field('band_name_2', '');
            update_field('band_link_2', null);
            break;
        case 'NA':
            update_field('band_name_2', '');
            update_field('band_link_2', null);
            update_field('perf_times_2', null);
            break;
    }
    return;
}

add_action('acf/save_post', 'APF_post_aftersaving', 20);

/*
 * Copy band times into its post categories.
 * We merge multiple slots of times into the categories for the one post.
 * If $looks_good is false then we CANCEL the band
 */
function APF_schedule_band($band_post, $porch_post, $terms, $looks_good)
{
    if ($band_post) {
        wp_set_post_categories($band_post->ID, array(), False);
        if ($looks_good) {
            update_field('porch_link', $porch_post->ID, $band_post->ID);
            update_field('porch_address', $porch_post->post_title, $band_post->ID);
            foreach ($terms as $term) {
                wp_set_post_categories($band_post->ID, array(
                    $term
                ), True);
            }
        } else {
            update_field('porch_link', null, $band_post->ID);
            update_field('porch_address', '', $band_post->ID);
            wp_set_post_categories($band_post->ID, array(
                47
            ), True);
            wp_set_post_categories($porch_post->ID, array(
                47
            ), True);
            $band_link_1 = get_field('band_link_1', $porch_post->ID);
            $band_link_2 = get_field('band_link_2', $porch_post->ID);
            
            if ($band_link_1->ID == $band_post->ID) {
                update_field('status_of_slot_1', 'Looking for a band', $porch_post->ID);
                update_field('band_link_1', null, $porch_post->ID);
            } elseif ($band_link_2->ID == $band_post->ID) {
                update_field('status_of_slot_2', 'Looking for a band', $porch_post->ID);
                update_field('band_link_2', null, $porch_post->ID);
            }
        }
    }
}

/*
 * Ensure each porch has a unique location
 */
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

/*
 * When porch has checked times in multiple slots,
 * Make sure the times do not overlap
 * (This is done very roughly for now)
 */
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

/*
 * Find host of a band accounting for names, links, and slots
 * Ideally we want to allow for approximate string matching
 * Return one matching post, or Return FALSE if no match
 */
function APF_get_band_host($band_id, $band_p, $method, $exclude)
{
    if ($band_p) {
        $band_post = $band_p;
    } else {
        $band_post = get_post($band_id);
    }
    switch ($method) {
        case 'by_name':
            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key' => 'band_name_1',
                    'value' => $band_post->post_title, // sanitized??
                    'compare' => '='
                ),
                array(
                    'key' => 'band_name_2',
                    'value' => $band_post->post_title,
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
        case 'both':
            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key' => 'band_name_1',
                    'value' => $band_post->post_title, // sanitized??
                    'compare' => '='
                ),
                array(
                    'key' => 'band_name_2',
                    'value' => $band_post->post_title,
                    'compare' => '='
                ),
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

/*
 * When porch links to a band, make sure the band is available
 */
function APF_validate_linked_match($valid, $value, $field, $input)
{
    // bail early if value is already invalid
    if (! $valid) {
        return $valid;
    }
    // Did host link to band already hosted elsewhere?
    $band_post = get_post($value);
    $porch_post = APF_get_band_host($value, $band_post, 'by_link', array(
        $_POST['post_ID']
    ));
    if ($porch_post) {
        $valid = $band_post->post_title . ' already scheduled at ' . $porch_post->post_title;
    }
    return $valid;
}
add_filter('acf/validate_value/name=band_link_1', 'APF_validate_linked_match', 10, 4);
add_filter('acf/validate_value/name=band_link_2', 'APF_validate_linked_match', 10, 4);

/*
 * When host names a band on its schedule, make sure the band is unregistered
 * If it is registered then provide the address
 */
function APF_validate_named_match($valid, $value, $field, $input)
{
    // bail early if value is already invalid
    if (! $valid || ($value == '')) {
        return $valid;
    }
    // Did host enter name of a registered band?
    $band_post = null;
    $same_name = get_posts(array(
        'numberposts' => - 1,
        'post_type' => 'band',
        'name' => sanitize_title($value)
    ));
    
    // Band name not registered
    if (empty($same_name)) {
        return $valid;
    }
    // Band name is registered. Is it hosted elsewhere?
    $porch_post = APF_get_band_host($same_name[0]->ID, $same_name[0], 'by_link', array(
        $_POST['post_ID']
    ));
    
    if (! $porch_post) {
        $valid = $value . ' already registered. Please link to their listing';
    } else {
        $valid = $value . ' already scheduled at ' . $porch_post->post_title;
    }
    return $valid;
}
add_filter('acf/validate_value/name=band_name_1', 'APF_validate_named_match', 10, 4);
add_filter('acf/validate_value/name=band_name_2', 'APF_validate_named_match', 10, 4);

/*
 * Porchfest update messages.
 * Adapted from standard WP messages
 */
function APF_updated_messages($messages)
{
    $post = get_post();
    $post_type = get_post_type($post);
    $post_type_object = get_post_type_object($post_type);

    /*
     * See wp-admin/edit-form-advanced.php
     */
    $permalink = get_permalink( $post->ID );
    if ( ! $permalink ) {
        $permalink = '';
    }
    $preview_post_link_html = $view_post_link_html = '';
    $preview_url = get_preview_post_link( $post );
    // Preview post link.
    $preview_post_link_html = sprintf( ' <a target="_blank" href="%1$s">%2$s</a>',
        esc_url( $preview_url ),
        __( 'Preview listing' )
        );
    // View post link.
    $view_post_link_html = sprintf( ' <a href="%1$s">%2$s</a>',
        esc_url( $permalink ),
        __( 'View listing' )
        );
    
    if ($post_type == 'band') {
        $messages['band'] = array(
            0 => '', // Unused. Messages start at index 1.
            1 => __('Band listing updated.') . $view_post_link_html,
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => __('Band listing updated.'),
        /* translators: %s: date and time of the revision */
        5 => isset($_GET['revision']) ? sprintf(__('Band listing restored to revision from %s.'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6 => __('Band listing published.') . $view_post_link_html,
            7 => __('Band listing saved.'),
            8 => __('Band listing submitted.') . $preview_post_link_html,
            //9 => sprintf(__('Listing scheduled for: %s.'), '<strong>' . $scheduled_date . '</strong>') . $scheduled_post_link_html,
            10 => __('Band draft listing updated.') . $preview_post_link_html
        );
        // Important 
        // Band validation
        $messages = APF_validate_band_save($post, $messages);
        
    } elseif ($post_type == 'porch') {
        $messages['porch'] = array(
            0 => '', // Unused. Messages start at index 1.
            1 => __('Porch listing updated.') . $view_post_link_html,
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => __('Porch listing updated.'),
        /* translators: %s: date and time of the revision */
        5 => isset($_GET['revision']) ? sprintf(__('Porch listing restored to revision from %s.'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6 => __('Porch listing published.') . $view_post_link_html,
            7 => __('Porch listing saved.'),
            8 => __('Porch listing submitted.') . $preview_post_link_html,
            //9 => sprintf(__('Listing scheduled for: %s.'), '<strong>' . $scheduled_date . '</strong>') . $scheduled_post_link_html,
            10 => __('Porch draft listing updated.') . $preview_post_link_html
        );
        // Porch validation happened before post is saved
        // So there is no APF_validate_porch_save here 
    }
    return $messages;
}
add_filter('post_updated_messages', 'APF_updated_messages');

function APF_validate_band_save($band_post, $messages)
{
    $messages = APF_validate_band_cancel($band_post, $messages);
    $messages = APF_validate_band_name($band_post, $messages);
    return $messages;
}

/*
 * Band clicks cancel and "yes I am sure"
 */
function APF_validate_band_cancel($band_post, $messages)
{
    if($band_post->post_name=='') {
        return $messages;
    }
    // Check if user is sure -- cancel performance
    $value = get_field('are_you_sure', $band_post->ID);
    // Reset the cancel and are-you-sure fields for future
    update_field('cancel', 'no', $band_post->ID);
    update_field('are_you_sure', 'no', $band_post->ID);
    // Cancel the performance if user is sure
    if ($value == 'yes') { 
        $host = APF_get_band_host($band_post->ID, $band_post, 'by_link', array());
        APF_schedule_band($band_post, $host, array(), False); // False => Cancel
        $error_message = $band_post->post_title . ' has successfully cancelled';
        add_settings_error('APF_option_group', esc_attr('band_canceled'), $error_message, 'updated');
        settings_errors('APF_option_group', False, True);
    }
    return $messages;
}

/*
 * Prevent duplicate band names by converting duplicate into draft status
 */
function APF_validate_band_name($band_post, $messages)
{
    if($band_post->post_name=='') {
        return $messages;
    }
    $duplicate_name = get_posts(array(
        'numberposts' => -1,
        'post_type' => 'band',
        'post_status' => 'publish',
        'name' => sanitize_title($band_post->post_title),
        'exclude' => array(
            $band_post->ID
        )
    )); 
    if (!empty($duplicate_name)) {
        $error_message = $band_post->post_title . ' already registered';
        add_settings_error('APF_option_group', esc_attr('band_duplicate'), $error_message, 'error');
        settings_errors('APF_option_group', False, True);
        $band_post->post_status = 'draft';
        wp_update_post($band_post);
    }
    return $messages;
}


function APF_register_settings () {
    // register_setting( $option_group, $option_name, $sanitize_callback );
    register_setting('APF_option_group', 'APF_option_group', 'APF_validate_band_save');
}
add_action( 'admin_init', 'APF_register_settings' );


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

/**
 * Simple helper function for make menu item objects
 *
 * @param $title -
 *            menu item title
 * @param $url -
 *            menu item url
 * @param $order -
 *            where the item should appear in the menu
 * @param int $parent
 *            - the item's parent item
 * @return \stdClass
 */
function _custom_nav_menu_item($title, $url, $order, $parent = 0)
{
    $item = new stdClass();
    $item->ID = 1000000 + $order + $parent;
    $item->db_id = $item->ID;
    $item->title = $title;
    $item->url = $url;
    $item->menu_order = $order;
    $item->menu_item_parent = $parent;
    $item->type = '';
    $item->object = '';
    $item->object_id = '';
    $item->classes = array();
    $item->target = '';
    $item->attr_title = '';
    $item->description = '';
    $item->xfn = '';
    $item->status = '';
    return $item;
}

function custom_nav_menu_items($items, $menu)
{
    // only add item to a specific menu
    if ($menu->slug == 'search-menu') {
        
        // only add profile link if user is logged in
        if (get_current_user_id()) {
            $items[] = _custom_nav_menu_item('My Page', get_author_posts_url(get_current_user_id()), 99);
        }
    }
    
    return $items;
}
// add_filter( 'wp_get_nav_menu_items', 'custom_nav_menu_items', 20, 2 );

?>