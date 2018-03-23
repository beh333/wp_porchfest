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
        }
        $band_post = get_field('band_link_2'); // gets old value
        if ($band_post) {
            APF_schedule_band($band_post, $band_post->post_title, get_post($post_id), $terms, False);
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
    // Adding schedule 
    if ($looks_good) {
        // Only need to work if band has its own post
        if ($band_post) {
            // Band post keeps its own record of its porch
            // So that porch_address is displayed in band edit form
            update_field('porch_link', $porch_post->ID, $band_post->ID);
            update_field('porch_address', $porch_post->post_title, $band_post->ID);
            // reset categories for band post with $terms
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

?>