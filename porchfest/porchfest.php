<?php

/**
 * @package PorchFest_PLUS
 * @version 1.2
 */

/*
 * Plugin Name: Porchfest PLUS
 * Plugin URI:
 * Description: Manage Porchfest bands and porches with Wordpress
 *
 * Configure two custom post types: band and porch.
 * Add advanced custom fields.
 * Then use this plugin to keep band and porch info validated and synchronized.
 *
 * Author: Bruce Hoppe
 * Version: 1.2
 * Author URI:
 */
include_once 'apf-utilities.php';
include_once 'apf-validations.php';
include_once 'apf-menus.php';
include_once 'googlemaps_api.php';

/*
 * Right before saving updated porch info,
 * Make sure not to leave orphans in the schedule
 */
function APF_post_beforesaving($post_id)
{
    global $APF_porch_slots;
    
    if (! $_POST['acf']) {
        return;
    }
    //APF_initialize_status();
    APF_update_POST_title($post_id);
    if ($_POST['post_type'] == 'porch') {
        // clear out band schedules so we don't leave orphans
        foreach ($APF_porch_slots as $slot) {
            $band_post = APF_get_field('band_link', $slot); // gets old value before $_POST
            if ($band_post) {
                APF_schedule_band($band_post, $band_post->post_title, get_post($post_id), $terms, False);
            }
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
 * Clean up fields for consistency right after saving a porch or band.
 * New band gets autolinked to a host that had named it on its schedule.
 */
function APF_post_aftersaving($post_id)
{
    global $APF_porch_slots;
    global $APF_looking_term;
    global $APF_scheduled_term;
    
    $this_post = get_post($post_id);
    
    /*
     * BAND: Check unscheduled band and auto-schedule it if it is named by a porch
     */
    if ($this_post->post_type == 'band') {
        // If band already scheduled on a porch then no autoscheduling
        if (get_field('porch_link')) {
            wp_set_post_terms($post_id, array(
                $APF_scheduled_term
            ), 'status', False);
            return;
        }
        // Otherwise look for porch that says it's hosting this band
        $host = APF_get_band_host_by('post', $this_post, 'name', array());
        if ($host) {
            // Band is named by $host so find the exact $slot
            foreach ($APF_porch_slots as $slot) {
                $band_name = APF_get_field('band_name', $slot, $host->ID);
                if (sanitize_title($band_name) == $this_post->post_name) {
                    $terms = APF_get_field('perf_times', $slot, $host->ID);
                    APF_schedule_band($this_post, $this_post->post_title, $host, $terms, True);
                    // Update host info: band is linked, not named
                    APF_update_field('band_name', $slot, '', $host->ID);
                    APF_update_field('band_link', $slot, $post_id, $host->ID);
                    APF_update_field('status_of_slot', $slot, 'Have a band', $host->ID);
                    return;
                }
            }
        } /*
           * Otherwise status of band is 'Looking for a match'
           */
        else {
            wp_set_post_terms($post_id, array(
                $APF_looking_term
            ), 'status', False);
            return;
        }
    } /*
       * PORCH: Lots of housekeeping to keep fields consistent
       */
    elseif ($this_post->post_type == 'porch') {
        
        // Re-initialize porch slot status
        $status = APF_set_porch_status($this_post->ID);

        // And then update porch categories based on perf_times
        $results = wp_set_post_categories($this_post->ID, array(), False);
        foreach ($APF_porch_slots as $slot) {
            $terms[$slot] = APF_get_field('perf_times', $slot, $this_post->ID);
            if (! empty($terms[$slot])) {
                foreach ($terms[$slot] as $t) {
                    $results = wp_set_post_categories($this_post->ID, array(
                        $t
                    ), True);
                }
            } else {
                APF_update_field('status_of_slot', $slot, 'NA');
                $status[$slot] = 'NA';
            }
        }
        /*
         * Update band info for every slot
         */
        foreach ($APF_porch_slots as $slot) {
            if ('Have a band' == $status[$slot]) {
                APF_update_field('band_name', $slot, '');
                $band_post_id = APF_get_field('band_link', $slot);
                $band_post = get_post($band_post_id);
                APF_schedule_band($band_post, $band_post->post_title, $this_post, $terms[$slot], True);
            } elseif ('Have an unlisted band' == $status[$slot]) {
                APF_update_field('band_link', $slot, null);
                APF_schedule_band(False, APF_get_field('band_name', $slot), $this_post, $terms[$slot], True);
            } elseif ('Looking for a band' == $status[$slot]) {
                APF_update_field('band_name', $slot, '');
                APF_update_field('band_link', $slot, null);
            } elseif ('NA' == $status[$slot]) {
                APF_update_field('band_name', $slot, '');
                APF_update_field('band_link', $slot, null);
                APF_update_field('perf_times', $slot, null);
            } // else error
        }
    }
}
add_action('acf/save_post', 'APF_post_aftersaving', 20);

function APF_set_porch_status($porch_id){
    global $APF_porch_slots;
    global $APF_looking_term;
    global $APF_scheduled_term;

    // Re-initialize porch slot status
    wp_set_post_terms($porch_id, array(), 'status', False);
    foreach ($APF_porch_slots as $slot) {
        $status[$slot] = APF_get_field('status_of_slot', $slot, $porch_id);
        if ('Looking for a band' == $status[$slot]) {
            $results = wp_set_post_terms($porch_id, array(
                $APF_looking_term
            ), 'status', True);
        } elseif (('Have a band' == $status[$slot]) || ('Have an unlisted band' == $status[$slot])) {
            $results = wp_set_post_terms($porch_id, array(
                $APF_scheduled_term
            ), 'status', True);
        }
    }
    return($status);
}

/*
 * Copy band times into its post categories.
 * Merge multiple slots of times into the categories for the one post.
 * If $looks_good is false then CANCEL the band
 */
function APF_schedule_band($band_post, $band_name, $porch_post, $terms, $looks_good)
{
    global $APF_porch_slots;
    global $APF_looking_term;
    global $APF_scheduled_term;
    
    // Adding schedule
    if ($looks_good) {
        // Only need to work if band has its own post
        if ($band_post) {        
            // Band post keeps its own record of its porch
            // So that porch_address is displayed in band edit form
            update_field('porch_link', $porch_post->ID, $band_post->ID);
            update_field('porch_address', $porch_post->post_title, $band_post->ID);
            // reset status and reset categories for band post with $terms
            wp_set_post_terms($band_post->ID, array(
                $APF_scheduled_term
            ), 'status', False);
            wp_set_post_categories($band_post->ID, array(), False);
            foreach ($terms as $term) {
                wp_set_post_categories($band_post->ID, array(
                    $term
                ), True);
            }
            // to do? Set porch tags by band?
        }
    // Otherwise $looks_good is False and we're canceling schedule
    } else {
        if ($band_post) {
            update_field('porch_link', null, $band_post->ID);
            update_field('porch_address', '', $band_post->ID);
            // Set band status to Looking for match
            wp_set_post_terms($band_post->ID, array(
                $APF_looking_term
            ), 'status', False);
            // Find the exact porch slot for this band and unschedule it
            foreach ($APF_porch_slots as $slot) {
                $band_link = APF_get_field('band_link', $slot, $porch_post->ID);
                if ($band_link->ID == $band_post->ID) {
                    APF_update_field('status_of_slot', $slot, 'Looking for a band', $porch_post->ID);
                    APF_update_field('band_link', $slot, null, $porch_post->ID);
                }
            }
            // Update porch status
            APF_set_porch_status($porch_post->ID);
        }
        // else if no $band_post then canceling requires no action here
    }
}

/*
 * Find host of a band accounting for names, links, and slots
 *
 * @band_ref_method: is $band_value id or name
 * @host_ref_method: hosts can schedule band by name or id, which are we searching
 *
 * With name, to do: approximate string matching
 *
 * Return one matching post, or Return FALSE if no match
 */
function APF_get_band_host_by($band_ref_method, $band_value, $host_ref_method, $exclude)
{
    global $APF_porch_slots;
    
    // build meta query based on $host_ref_method
    $meta_query = array(
        'relation' => 'OR'
    );
    // search by band name
    if ('name' == $host_ref_method || 'both' == $host_ref_method) {
        $band_name = APF_get_band_name_from($band_ref_method, $band_value);
        if ($band_name != '') {
            foreach ($APF_porch_slots as $slot) {
                $meta_query[] = array(
                    'key' => 'band_name_' . $slot,
                    'value' => $band_name,
                    'compare' => '='
                );
            }
        }
    }
    // search by band id
    if ('id' == $host_ref_method || 'both' == $host_ref_method) {
        $band_id = APF_get_band_id_from($band_ref_method, $band_value);
        if ($band_id != 0) {
            foreach ($APF_porch_slots as $slot) {
                $meta_query[] = array(
                    'key' => 'band_link_' . $slot,
                    'value' => $band_id,
                    'compare' => '='
                );
            }
        }
    }
    // Search the database for band host with the $meta_query
    if (count($meta_query) >= 2) {
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
    } else {
        return false;
    }
}

/*
 * Return band name, given id, post, or name
 */
function APF_get_band_name_from($band_ref_method, $band_value)
{
    if ('name' == $band_ref_method) {
        $band_name = $band_value;
    } elseif ('post' == $band_ref_method) {
        $band_name = $band_value->post_title;
    } elseif ('id' == $band_ref_method) {
        $band_post = get_post($band_value);
        if ($band_post) {
            $band_name = $band_post->post_title;
        } else {
            $band_name = '';
        }
    }
    return $band_name;
}

/*
 * Return band id, given id, post, or name
 */
function APF_get_band_id_from($band_ref_method, $band_value)
{
    if ('name' == $band_ref_method) {
        $bands_w_name = get_posts(array(
            'numberposts' => 1,
            'post_type' => 'band',
            'post_status' => 'publish',
            'name' => sanitize_title($band_value)
        ));
        if (! empty($bands_w_name)) {
            $band_id = $bands_w_name[0]->ID;
        } else {
            $band_id = 0;
        }
    } elseif ('post' == $band_ref_method) {
        $band_id = $band_value->ID;
    } elseif ('id' == $band_ref_method) {
        $band_id = $band_value;
    }
    return $band_id;
}


function APF_initialize_status()
{
    global $APF_looking_term;
    global $APF_scheduled_term;
    
    $all_porches = get_posts(array(
        'numberposts' => -1,
        'post_type' => 'porch',
        'post_status' => 'publish'
    ));
    foreach ($all_porches as $porch) { 
        APF_set_porch_status($porch->ID);
    }
    
    $all_bands = get_posts(array(
        'numberposts' => -1,
        'post_type' => 'band',
        'post_status' => 'publish'
    ));
    foreach ($all_bands as $band) {
        if (get_field('porch_link', $band->ID)) {
            wp_set_post_terms($band->ID, array(
                $APF_scheduled_term
            ), 'status', False);
        } else {
            wp_set_post_terms($band->ID, array(
                $APF_looking_term
            ), 'status', False);
        }
    } 
}

?>