<?php

/**
 * @package PorchFest_PLUS
 * @version 1.1
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
 * Version: 1.1
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
            APF_schedule_band($band_post, $band_post->post_title, get_post($post_id), $terms, False);
            // update_field('porch_link', null, $band_post->ID);
            // update_field('porch_address', '', $band_post->ID);
            // wp_set_post_categories($band_post->ID, array(
            // $looking_id
            // ), False);
        }
        $band_post = get_field('band_link_2'); // gets old value
        if ($band_post) {
            APF_schedule_band($band_post, $band_post->post_title, get_post($post_id), $terms, False);
            // update_field('porch_link', null, $band_post->ID);
            // update_field('porch_address', '', $band_post->ID);
            // wp_set_post_categories($band_post->ID, array(
            // $looking_id
            // ), False);
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
                APF_schedule_band($this_post, $this_post->post_title, $host, $terms_1, True);
                // Update host info: band is linked, not named
                update_field('band_name_1', '', $host->ID);
                update_field('band_link_1', $post_id, $host->ID);
                update_field('status_of_slot_1', 'Have a band', $host->ID);
                return;
            } elseif (sanitize_title($band_name_2) == $this_post->post_name) {
                $terms_2 = get_field('perf_times_2', $host->ID);
                APF_schedule_band($this_post, $this_post->post_title, $host, $terms_2, True);
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
            APF_schedule_band($band_post, $band_post->post_title, $this_post, $terms_1, True);
            break;
        case 'Have an unlisted band':
            update_field('band_link_1', null);
            APF_schedule_band(False, get_field('band_name_1'), $this_post, $terms_1, True);
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
            APF_schedule_band($band_post, $band_post->post_title, $this_post, $terms_2, True);
            break;
        case 'Have an unlisted band':
            update_field('band_link_2', null);
            APF_schedule_band(False, get_field('band_name_2'), $this_post, $terms_1, True);
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
 * Merge multiple slots of times into the categories for the one post.
 * If $looks_good is false then CANCEL the band
 */
function APF_schedule_band($band_post, $band_name, $porch_post, $terms, $looks_good)
{
    // Look for performance post
    $perf_posts = get_posts(array(
        'numberposts' => - 1,
        'post_type' => 'performance',
        'title' => $band_name,
        'post_status' => array(
            'publish',
            'draft',
            'trash'
        ),
        'meta_key' => 'performance_porch_link',
        'meta_value' => $porch_post->ID
    ));
    
    // Adding schedule 
    // Start by get the performance post
    if ($looks_good) {
        // If found existing performance post then use it for updating
        if (! empty($perf_posts)) {
            $post_id = $perf_posts[0]->ID;
        // Otherwise create new performance post
        } else {
            $postarr = array(
                'ID' => 0,
                'post_type' => 'performance',
                'post_title' => $band_name,
                'post_status' => 'publish'
            );
            $post_id = wp_insert_post($postarr);
            update_field('performance_porch_link', $porch_post->ID, $post_id);
            update_field('performance_map_marker', get_field('map_marker', $porch_post->ID), $post_id);
        }
        // Now $post_id is performance post with title, porch link, map marker;
        // But maybe missing band link, categories, and tags.
        // Updating hinges on whether band info comes from post or name
        if ($band_post) {
            // Band post keeps its own record of its porch
            // So that porch_address is displayed in band edit form
            update_field('porch_link', $porch_post->ID, $band_post->ID);
            update_field('porch_address', $porch_post->post_title, $band_post->ID);
            // Performance post links to the band post
            update_field('performance_band_link', $band_post->ID, $post_id);
            // reset categories for band and performance posts with $terms
            wp_set_post_categories($band_post->ID, array(), False);
            wp_set_post_categories($post_id, array(), False);
            foreach ($terms as $term) {
                wp_set_post_categories($band_post->ID, array(
                    $term
                ), True);
                wp_set_post_categories($post_id, array(
                    $term
                ), True);
            }
            // Set performance tags by band
            $tags = wp_get_post_tags($band_post->ID); 
            wp_set_post_tags($post_id, array(), False);
            foreach ($tags as $tag) {
                wp_set_post_tags($post_id, array(
                    $tag->term_id
                ), True);
            }
        // Case where there is no band post. Band info is named by porch
        } else {
            // performance post has no band link, just band name
            update_field('performance_band_link', 0, $post_id);
            // reset categories for performance post with $terms
            wp_set_post_categories($post_id, array(), False);
            foreach ($terms as $term) {
                wp_set_post_categories($post_id, array(
                    $term
                ), True);
            }
            // Set performance tags by porch
            $tags = wp_get_post_tags($porch_post->ID);
            wp_set_post_tags($post_id, array(), False);
            foreach ($tags as $tag) {
                wp_set_post_tags($post_id, array(
                    $tag->term_id
                ), True);
            }
        }
    // Canceling schedule
    //
    } else {
        // Put performance post in trash
        if(!empty($perf_posts)) {
            wp_delete_post($perf_posts[0]->ID);
        }
        if ($band_post) {
            update_field('porch_link', null, $band_post->ID);
            update_field('porch_address', '', $band_post->ID);
            // Set band and porch categories to Looking for match
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
 * When porch links to a band, make sure the band is available.
 * Right now band link field is filtered to category 'looking for a match'
 * Which technically makes this check superfluous
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
    
    if ($post_type == 'band') {
        if ($post->post_status == 'draft') {
            $messages['band'] = array(
                1 => 'DRAFT band listing still not published',
                4 => 'DRAFT band listing still not published',
                6 => 'DRAFT band listing not yet published',
                7 => 'DRAFT band listing saved.',
                10 => 'DRAFT band draft listing updated.'
            );
        } else {
            $messages['band'] = array(
                1 => 'Band listing updated.' . $view_post_link_html,
                4 => 'Band listing updated.',
                6 => 'Band listing published.' . $view_post_link_html,
                7 => 'Band listing saved.',
                10 => 'Band draft listing updated.' . $preview_post_link_html
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
 * Band clicks cancel and "yes I am sure"
 */
function APF_validate_band_cancel($post_id)
{
    $band_post = get_post($post_id);
    if ($band_post->post_type != 'band') {
        return;
    }
    $value = get_field('are_you_sure');
    update_field('cancel', 'no');
    update_field('are_you_sure', 'no');
    // Cancel the performance if user is sure
    if ($value == 'yes') {
        $host = APF_get_band_host($post_id, $band_post, 'by_link', array());
        APF_schedule_band($band_post, $band_post->post_title, $host, array(), False); // False => Cancel
    }
    return;
}
add_action('acf/save_post', 'APF_validate_band_cancel', 20);

/*
 * Prevent duplicate band names by converting status to draft.
 * The only possible draft band posts are duplicate names,
 * So elsewhere we generate appropriate admin notice error message
 */
function APF_validate_band_name($data, $postarr)
{
    if ($data['post_type'] != 'band') {
        return $data;
    }
    if ($postarr['ID'] != 0) {
        $duplicate_name = get_posts(array(
            'numberposts' => - 1,
            'post_type' => 'band',
            'post_status' => 'publish',
            'name' => sanitize_title($postarr['post_title']),
            'exclude' => array(
                $postarr['ID']
            )
        ));
        if (! empty($duplicate_name)) {
            $data['post_status'] = 'draft';
        }
    }
    return $data;
}
add_filter('wp_insert_post_data', 'APF_validate_band_name', 10, 2);

/*
 * Provide admin notices for special post situations outside regular validation
 * 1. Duplicate band name => error
 * 2. ... nothing else for now
 */
function APF_admin_notice_special()
{
    $screen = get_current_screen();
    
    if ('band' == $screen->post_type) {
        if ('post' == $screen->base) {
            $post = get_post();
            if ($post->post_status == 'draft') {
                $message = $post->post_title . ' already registered. Choose a unique name.'?><div
	class="notice notice-error">
	<p><?php echo $message; ?></p>
</div><?php
            }
        }
    }
}
add_action('admin_notices', 'APF_admin_notice_special');

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