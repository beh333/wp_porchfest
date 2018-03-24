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
        
        $band_post = APF_get_field('band_link', 1); // gets old value before $_POST
        if ($band_post) {
            APF_schedule_band($band_post, $band_post->post_title, get_post($post_id), $terms, False);
        }
        $band_post = APF_get_field('band_link', 2); // gets old value
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
     * BAND: Auto schedule registered band if host has named it already
     */
    if ($this_post->post_type == 'band') {
        $host = APF_get_band_host_by('post', $this_post, 'name', array());
        if ($host) {
            $band_name_1 = APF_get_field('band_name', 1, $host->ID);
            $band_name_2 = APF_get_field('band_name', 2, $host->ID);
            if (sanitize_title($band_name_1) == $this_post->post_name) {
                $terms_1 = APF_get_field('perf_times', 1, $host->ID);
                APF_schedule_band($this_post, $this_post->post_title, $host, $terms_1, True);
                // Update host info: band is linked, not named
                APF_update_field('band_name', 1, '', $host->ID);
                APF_update_field('band_link', 1, $post_id, $host->ID);
                APF_update_field('status_of_slot', 1, 'Have a band', $host->ID);
                return;
            } elseif (sanitize_title($band_name_2) == $this_post->post_name) {
                $terms_2 = APF_get_field('perf_times', 2, $host->ID);
                APF_schedule_band($this_post, $this_post->post_title, $host, $terms_2, True);
                // Update host info: band is linked, not named
                APF_update_field('band_name', 2, '', $host->ID);
                APF_update_field('band_link', 2, $post_id, $host->ID);
                APF_update_field('status_of_slot', 2, 'Have a band', $host->ID);
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
        
        $status_1 = APF_get_field('status_of_slot', 1, $this_post->ID);
        $status_2 = APF_get_field('status_of_slot', 2, $this_post->ID);
        
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
        $terms_1 = APF_get_field('perf_times', 1);
        $terms_2 = APF_get_field('perf_times', 2);
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
        APF_update_field('status_of_slot', 2, 'NA');
    }
    /*
     * Update band info based on slot 1
     */
    switch ($status_1) {
        case 'Have a band':
            APF_update_field('band_name', 1, '');
            $band_post_id = APF_get_field('band_link', 1);
            $band_post = get_post($band_post_id);
            APF_schedule_band($band_post, $band_post->post_title, $this_post, $terms_1, True);
            break;
        case 'Have an unlisted band':
            APF_update_field('band_link', 1, null);
            APF_schedule_band(False, APF_get_field('band_name', 1), $this_post, $terms_1, True);
            break;
        case 'Looking for a band':
            APF_update_field('band_name', 1, '');
            APF_update_field('band_link', 1, null);
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
            APF_update_field('band_name', 2, '');
            $band_post_id = APF_get_field('band_link', 1);
            $band_post = get_post($band_post_id);
            APF_schedule_band($band_post, $band_post->post_title, $this_post, $terms_2, True);
            break;
        case 'Have an unlisted band':
            APF_update_field('band_link', 2, null);
            APF_schedule_band(False, APF_get_field('band_name', 2), $this_post, $terms_2, True);
            break;
        case 'Looking for a band':
            APF_update_field('band_name', 2, '');
            APF_update_field('band_link', 2, null);
            break;
        case 'NA':
            APF_update_field('band_name', 2, '');
            APF_update_field('band_link', 2, null);
            APF_update_field('perf_times', 2, null);
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
            $band_link_1 = APF_get_field('band_link', 1, $porch_post->ID);
            $band_link_2 = APF_get_field('band_link', 2, $porch_post->ID);
            if ($band_link_1->ID == $band_post->ID) {
                APF_update_field('status_of_slot', 1, 'Looking for a band', $porch_post->ID);
                APF_update_field('band_link', 1, null, $porch_post->ID);
            } elseif ($band_link_2->ID == $band_post->ID) {
                APF_update_field('status_of_slot', 2, 'Looking for a band', $porch_post->ID);
                APF_update_field('band_link', 2, null, $porch_post->ID);
            }
        }
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
    // $host_ref_method determines wp query design
    // here is search by band name
    if ('name' == $host_ref_method) {
        $band_name = APF_get_band_name_from($band_ref_method, $band_value);
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key' => 'band_name_1',
                'value' => $band_name, // sanitized??
                'compare' => '='
            ),
            array(
                'key' => 'band_name_2',
                'value' => $band_name,
                'compare' => '='
            ),
            array(
                'key' => 'band_name_3',
                'value' => $band_name,
                'compare' => '='
            ),
            array(
                'key' => 'band_name_4',
                'value' => $band_name,
                'compare' => '='
            )
        );
        // Or we can search by band id
    } elseif ('id' == $host_ref_method) {
        $band_id = APF_get_band_id_from($band_ref_method, $band_value);
        if ($band_id == 0) {
            return false;
        }
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
            ),
            array(
                'key' => 'band_link_3',
                'value' => $band_id,
                'compare' => '='
            ),
            array(
                'key' => 'band_link_4',
                'value' => $band_id,
                'compare' => '='
            )
        );
        // Or we can search by both band name and band id
    } elseif ('both' == $host_ref_method) {
        $band_name = APF_get_band_name_from($band_ref_method, $band_value);
        $band_id = APF_get_band_id_from($band_ref_method, $band_value);
        // If no valid $band_id then search purely by $band_name
        if ($band_id == 0) {
            return APF_get_band_host_by('name', $band_name, 'name', $exclude);
        }
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key' => 'band_name_1',
                'value' => $band_name, // sanitized??
                'compare' => '='
            ),
            array(
                'key' => 'band_name_2',
                'value' => $band_name,
                'compare' => '='
            ),
            array(
                'key' => 'band_name_3',
                'value' => $band_name,
                'compare' => '='
            ),
            array(
                'key' => 'band_name_4',
                'value' => $band_name,
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
            ),
            array(
                'key' => 'band_link_3',
                'value' => $band_id,
                'compare' => '='
            ),
            array(
                'key' => 'band_link_4',
                'value' => $band_id,
                'compare' => '='
            )
        );
        // Invalid $host_ref_method => error
    } else {
        return false;
    }
    
    // Search the database for band host with the $meta_query
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
 * Return band name, given id, post, or name
 */
function APF_get_band_name_from($band_ref_method, $band_value)
{
    if ('name' == $band_ref_method) {
        $band_name = $band_value;
    } elseif ('post' == $band_ref_method) {
        $band_name = $band_value->title;
    } elseif ('id' == $band_ref_method) {
        $band_post = get_post($band_value);
        $band_name = $band_post->title;
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

?>