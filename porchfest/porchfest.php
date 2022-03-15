<?php

/**
 * @package PorchFest_PLUS
 * @version 1.4
 */

/*
 * Plugin Name: Porchfest PLUS
 * Plugin URI:
 * Description: Manage Porchfest porches, bands, and exhibits with Wordpress
 *
 * Configure three custom post types: porch, band, and exhibit.
 * Add advanced custom fields.
 * Then use this plugin to keep info validated and synchronized.
 *
 * Author: Bruce Hoppe
 * Version: 1.4
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
    //APF_initialize_data();
    APF_update_POST_title($post_id);
    if ($_POST['post_type'] == 'porch') {
        // clear out band schedules so we don't leave orphans
        foreach ($APF_porch_slots as $slot) {
            $band_post = APF_get_field('band_link', $slot); // gets old value before $_POST
            if ($band_post) {
                APF_cancel_band($band_post, get_post($post_id));
            }
        }
        // clear out exhibit so we don't leave orphan
        $exhibit_post = get_field('exhibit_link'); // gets old value before $_POST
        if ($exhibit_post) {
            APF_cancel_exhibit($exhibit_post, get_post($post_id));
        }
    }
}
add_action('acf/save_post', 'APF_post_beforesaving', 2);

/*
 * As part of pre-save, set title and name
 * Porch title/name = sanitized & shortened address.
 * Always update band or exhibit name/slug based on its title
 */
function APF_update_POST_title($post_id)
{
    $map_marker_key = 'field_5aa4febaecbb3';
    
    if (($_POST['post_type'] == 'band') || ($_POST['post_type'] == 'exhibit')) {
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
        $address = APF_shorten_address(json_decode(stripslashes($map_marker), True)['address']);
        $my_post = array(
            'ID' => $post_id,
            'post_title' => $address,
            'post_name' => sanitize_title($address)
        );
        wp_update_post($my_post);
    }
}

function set_html_content_type() { return 'text/html'; }

function APF_thanks_for_listing($listing_type, $post_id, $post) 
{   
    $title = esc_html($post->post_title);
    $author = new WP_User($post->post_author);
    $subject = 'Thanks for registering your ' . $listing_type . '. *** SAVE THIS EMAIL ***';
    $message = '<h2>Thanks for Participating in Arlington Porchfest!</h2>';
    $message .= '<p>Dear ' . $author->display_name . ',</p>';
    $message .= '<p>Thank you for registering your ' . $listing_type . ', &quot;' . $title . ',&quot; with Arlington Porchfest. ';
    $post_url = get_permalink( $post_id );
    $message .= '<p>You can view your ' . $listing_type . ' <a href="' . $post_url . '">here</a>. ';
    $edit_url = add_query_arg( array('post'=>$post_id, 'action'=>'edit'), admin_url('post.php') );
    $message .= 'You can edit your ' . $listing_type . ' <a href="' . $edit_url . '">here</a>.</p>';
    $message .= '<p>You must be logged in as &quot;' . $author->user_login . '&quot; in order to edit your ' . $listing_type . '.</p>';
    $message .= '<h4>Supporting Arlington Porchfest</h4>';
    $message .= '<p>Arlington Porchfest is a free, community event powered by the time, energy, and support of lots of musicians, porch hosts, volunteers and friends - like you! If you know community members interested in supporting this event, please feel free to refer them to ACA’s website where they can learn more about making a <a href="https://www.acarts.org/donate" target="_blank">tax-deductible donation</a> to ACA, becoming a <a href="https://www.acarts.org/arlington-porchfest" target="_blank">sponsor of Arlington Porchfest</a>, or <a href="https://www.acarts.org/volunteer" target="_blank">volunteering</a> at public events like these!</p>';
    $from = 'Arlington Porchfest';
    $from_email = get_option('admin_email');
    $headers = 'From: ' . $from . ' <' . $from_email . '>' . "\r\n";
    // sending email in html format
    add_filter( 'wp_mail_content_type', 'set_html_content_type' );
    wp_mail( $author->user_email, $subject, $message, $headers);
    remove_filter( 'wp_mail_content_type', 'set_html_content_type' );
}

/*
 * The main starting point for synchronizing info between listings.
 * Porch times go into bands and exhibits.
 * Band and exhibit genres go into porch.
 */
function APF_post_aftersaving($post_id)
{
    $this_post = get_post($post_id);
    if ('band' == $this_post->post_type) {
        APF_band_aftersaving($post_id, $this_post);
    } elseif ('exhibit' == $this_post->post_type) {
        APF_exhibit_aftersaving($post_id, $this_post);
    } elseif ('porch' == $this_post->post_type) {
        APF_porch_aftersaving($post_id, $this_post);
    }
    // Send confirmation email to post owner/author
    APF_thanks_for_listing($this_post->post_type, $post_id, $this_post);
}
add_action('acf/save_post', 'APF_post_aftersaving', 20);

/*
 * Band-driven info synchronizing
 * * New band is checked for auto-scheduling
 * * Cancelling band is removed from porch
 * * Porch genre id updated based on band genre
 */ 
function APF_band_aftersaving($post_id, $this_post)
{
    global $APF_porch_slots;
    global $APF_scheduled_performance;
    
    update_field('title_for_sorting', $this_post->post_title, $post_id);
    
    // If already scheduled on a porch then no autoscheduling
    $porch_id = False;
    $porch_post = get_field('porch_link', $post_id);
    if ($porch_post) {
        $porch_id = $porch_post->ID;
    }
    if ($porch_id) {
        wp_set_post_terms($post_id, array(
            $APF_scheduled_performance
        ), 'status', False);
    // Band autoschedule
    } else {
        // If not already scheduled then look for porch that says it's hosting this band
        $host = APF_get_band_host_by('post', $this_post, 'name', array());
        if ($host) {
            $porch_id = $host->ID;
            // Band is named by $host so find the exact $slot
            foreach ($APF_porch_slots as $slot) {
                $band_name = APF_get_field('band_name', $slot, $porch_id);
                if (sanitize_title($band_name) == $this_post->post_name) {
                    $terms = APF_get_field('perf_times', $slot, $porch_id);
                    APF_schedule_band($this_post, $host, $terms);
                    // Update host info: band is linked, not named
                    APF_update_field('band_name', $slot, '', $porch_id);
                    APF_update_field('band_link', $slot, $post_id, $porch_id);
                    APF_update_field('status_of_slot', $slot, 'Have a band', $host->ID);
                }
            }
        // No host yet. Status of band is 'Looking for a porch'
        } else {
            APF_set_listing_looking($post_id);
        }
    }
    // Update porch genre to incorporate any changes in this band's genre
    if ($porch_id) {
        APF_set_porch_genre($porch_id, 'band', False);
    }
}

/*
 * Exhibit-driven info synchronizing
 * * New exhibit is checked for auto-scheduling
 * * Cancelling exhibit is removed from porch
 * * Porch genre id updated based on exhibit genre
 */ 
 function APF_exhibit_aftersaving($post_id, $this_post)
{
    global $APF_scheduled_exhibit;
    
    update_field('title_for_sorting', $this_post->post_title, $post_id);

    // If already scheduled on a porch then no autoscheduling
    $porch_id = False;
    $porch_post = get_field('porch_link', $post_id);
    if ($porch_post) {
        $porch_id = $porch_post->ID;
    }
    if ($porch_id) {
        wp_set_post_terms($post_id, array(
            $APF_scheduled_exhibit
        ), 'status', False);
    // Autoschedule
    } else {
        $host = APF_get_exhibit_host_by('post', $this_post, 'name', array());
        if ($host) {
            $porch_id = $host->ID;
            APF_schedule_exhibit($this_post, $host);
            update_field('exhibit_name', '', $porch_id);
            update_field('exhibit_link', $post_id, $porch_id);
            update_field('status_of_exhibit', 'Have an exhibit', $porch_id);
        // No host yet. Status of exhibit is 'Looking for a porch'
        } else {
            APF_set_listing_looking($post_id);
        }
    }
    // Update porch genre to incorporate any changes in this exhibit's genre
    if ($porch_id) {
        APF_set_porch_genre($porch_id, 'exhibit', False);
    }
} 

/*
 * Change listing status to looking for a porch, over all possible times
 */
function APF_set_listing_looking($listing_id)
{
    global $APF_looking_for_porch;
    
    // Status is looking for a porch
    wp_set_post_terms($listing_id, array(
        $APF_looking_for_porch
    ), 'status', False);
    // Looking for porch over all possible times
    // to do: programmatically list all possible times
    wp_set_post_terms($listing_id, array('12pm','1pm','2pm','3pm','4pm','5pm'), 'looking', False);
    // Not scheduled at any time
    wp_set_post_terms($listing_id, array(), 'scheduled', False);
}

/*
 * Porch-driven info synchronizing
 * * Update porch taxonomies based on slots' statuses and times
 * * Update band statuses and times based on slots
 * * Update exhibit status (and times?)
 */
function APF_porch_aftersaving($post_id, $this_post)
{
    global $APF_porch_slots;

    // APF_initialize_new_fields();
    $address = $this_post->post_title;
    update_field('title_for_sorting', preg_replace('/^\s*[0-9\-]+\w?\s*/', '', $address));
    // update_field 'marker_label'
    $posttags = get_the_tags($post_id);
    if ($posttags) {
        update_field('marker_label', $posttags[0]->name, $post_id);
        update_field('num_order_code', $posttags[0]->name, $post_id);
    } else {
        update_field('marker_label', '9999', $post_id);
        update_field('num_order_code', '9999', $post_id);
    }
    
    // Re-initialize porch status and all band-scheduling and exhibit-scheduling porch fields
    $porch_schedule = APF_set_porch_schedule($post_id);
    $status = $porch_schedule['slot_status'];
    $terms = $porch_schedule['slot_times'];
    
    /*
     * Update band info for every slot
     */
    // Re-initialize porch performance genre to empty; it gets filled by APF_schedule below
    wp_set_post_terms($post_id, array(), 'performance_genre', False);
    foreach ($APF_porch_slots as $slot) {
        if ('Have a band' == $status[$slot]) {
            APF_update_field('band_name', $slot, '');
            $band_post_id = APF_get_field('band_link', $slot);
            $band_post = get_post($band_post_id);
            APF_schedule_band($band_post, $this_post, $terms[$slot]);
        } elseif ('Have an unlisted band' == $status[$slot]) {
            APF_update_field('band_link', $slot, null);
            // APF_schedule_band does nothing with unlisted band
        } elseif ('Looking for a band' == $status[$slot]) {
            APF_update_field('band_name', $slot, '');
            APF_update_field('band_link', $slot, null);
        } elseif ('NA' == $status[$slot]) {
            APF_update_field('band_name', $slot, '');
            APF_update_field('band_link', $slot, null);
            APF_update_field('perf_times', $slot, null);
        } // else error
    }
    
    /*
     * Update exhibit info; note that exhibit schedule inherits from porch times set above
     */
    // Re-initialize porch exhibit genre to empty; it gets filled by APF_schedule below
    wp_set_post_terms($post_id, array(), 'exhibit_genre', False);
    // Is porch even interested in exhibiting?
    $exhibit_interest = get_field('interested_in_exhibiting');
    if ('Interested in exhibiting' == $exhibit_interest) {
        $exhibit_status = get_field('status_of_exhibit');
        if ('Have an exhibit' == $exhibit_status) {
            update_field('exhibit_name', '');
            $exhibit_post_id = get_field('exhibit_link');
            if ($exhibit_post_id) {
                $exhibit_post = get_post($exhibit_post_id);
                APF_schedule_exhibit($exhibit_post, $this_post);
            }
        } elseif ('Have an unlisted exhibit' == $exhibit_status) {
            update_field('exhibit_link', null);
            // APF_schedule_exhibit does nothing with unlisted exhibit
        } elseif ('Looking for an exhibit' == $exhibit_status) {
            update_field('exhibit_name', '');
            update_field('exhibit_link', null);
        }
    } else { // Not interested in exhibiting, so re-initialize all porch exhibit fields
        update_field('status_of_exhibit', 'Looking for an exhibit');
        update_field('exhibit_name', '');
        update_field('exhibit_link', null);
    }
}

/*
 * Set porch genre(s) (either performance or exhibit) based on listings it is hosting
 */
function APF_set_porch_genre($porch_id, $listing_type, $status_arg)
{
    global $APF_porch_slots;
    
    if ('band' == $listing_type) {
        // Get status of porch slots
        if ($status_arg) {
            $status = $status_arg;
        } else {
            $status = APF_get_porch_status($porch_id);
        }
        // Re-initialize performance genres to none
        wp_set_post_terms($porch_id, array(), 'performance_genre', False);
        // Go through every porch slot
        foreach ($APF_porch_slots as $slot) {
            // Update porch with band genre
            if ('Have a band' == $status[$slot]) {
                $band_id = APF_get_field('band_link', $slot, $porch_id);
                if ($band_id) {
                    $performance_genre = get_field('genre', $band_id);
                    wp_set_post_terms($porch_id, $performance_genre, 'performance_genre', True);
                }
            }
        }
    } elseif ('exhibit' == $listing_type) {
        // Re-initialize all exhibit genres to empty
        wp_set_post_terms($porch_id, array(), 'exhibit_genre', False);
        $exhibit_status = get_field('status_of_exhibit', $porch_id);
        // Update porch with exhibit genre
        if ('Have an exhibit' == $exhibit_status) {
            $exhibit_post_id = get_field('exhibit_link', $porch_id);
            if ($exhibit_post_id) {
                $exhibit_genre = get_field('genre', $exhibit_post_id);
                wp_set_post_terms($porch_id, $exhibit_genre, 'exhibit_genre', True);
            }
        }
    }
}

function APF_get_porch_status($porch_id)
{
    global $APF_porch_slots;
    
    $status = [];
    foreach ($APF_porch_slots as $slot) {
        $status[$slot] = APF_get_field('status_of_slot', $slot, $porch_id);
    }
    return $status;
}

/*
 * Set porch status ('scheduled' or 'looking') x (performance or exhibit)
 * Set all band-scheduling and exhibit-scheduling porch fields
 */
function APF_set_porch_schedule($porch_id)
{
    global $APF_porch_slots;
    global $APF_looking_for_band;
    global $APF_looking_for_exhibit;
    global $APF_scheduled_performance;
    global $APF_scheduled_exhibit;
    
    // Re-initialize porch status to empty
    $slot_status = [];
    $slot_times = [];
    wp_set_post_terms($porch_id, array(), 'status', False);

    /*
     * Update all band-scheduling porch fields
     * Start by initializing all times to empty and then loop over all slots
     */
    wp_set_post_categories($porch_id, array(), False);
    $scheduled_terms = [];
    $looking_terms = [];
    // Loop over all porch slots and collect scheduled and looking times
    foreach ($APF_porch_slots as $slot) {
        $slot_status[$slot] = APF_get_field('status_of_slot', $slot, $porch_id);
        $slot_times[$slot] = APF_get_field('perf_times', $slot, $porch_id);
        if (! empty($slot_times[$slot])) {
            // Update taxonomies depending on slot status. Category updates by ID and others update by name.
            // The values of perf_times options are cleverly set to match IDs of corresponding terms in 'category'.
            // The names of terms are set to match each other across 'schedule', 'looking', and 'category'.
            if (('Have a band' == $slot_status[$slot]) || ('Have an unlisted band' == $slot_status[$slot])) {
                // Set status to scheduled
                wp_set_post_terms($porch_id, array($APF_scheduled_performance), 'status', True);
                // Collect scheduled times
                foreach ($slot_times[$slot] as $t) {
                    wp_set_post_categories($porch_id, array($t), True);
                    $scheduled_terms[] = get_term($t, 'category')->name;
                }
            } elseif ('Looking for a band' == $slot_status[$slot]) {
                // Set status to looking
                wp_set_post_terms($porch_id, array($APF_looking_for_band), 'status', True);
                // Collect looking times
                foreach ($slot_times[$slot] as $t) {
                    wp_set_post_categories($porch_id, array($t), True);
                    $looking_terms[] = get_term($t, 'category')->name;
                }
            }
        // If no perf times then set slot status to NA
        } else {
            APF_update_field('status_of_slot', $slot, 'NA');
            $slot_status[$slot] = 'NA';
        }
    }
    // After scanning all slots, then update 'scheduled' and 'looking' taxonomies with collected times for this porch
    wp_set_post_terms($porch_id, $scheduled_terms, 'scheduled', False);
    wp_set_post_terms($porch_id, $looking_terms, 'looking', False);
    
    /*
     * Update all exhibit-scheduling porch fields
     */
    $exhibit_interest = get_field('interested_in_exhibiting', $porch_id);
    if ('Interested in exhibiting' == $exhibit_interest) {
        $exhibit_status = get_field('status_of_exhibit', $porch_id);
        if ('Looking for an exhibit' == $exhibit_status) {
            wp_set_post_terms($porch_id, array($APF_looking_for_exhibit), 'status', True);
        } elseif (('Have an exhibit' == $exhibit_status) || ('Have an unlisted exhibit' == $exhibit_status)) {
            wp_set_post_terms($porch_id, array($APF_scheduled_exhibit), 'status', True);
        }
    }
    return (array(
        'slot_status' => $slot_status,
        'slot_times' => $slot_times
    ));
}

function APF_schedule_band($band_post, $porch_post, $terms)
{
    APF_schedule_listing('band', $band_post, $porch_post, $terms);
}

function APF_schedule_exhibit($exhibit_post, $porch_post)
{
    // Scheduled time of exhibit is inherited from hosting porch and includes both scheduled and looking times 
    //$both = wp_get_post_categories($porch_post->ID);
    $sched = array_unique(array_merge(array_map("APF_scheduled_names", wp_get_post_terms($porch_post->ID, 'scheduled')),
                                      array_map("APF_looking_names", wp_get_post_terms($porch_post->ID, 'looking'))));
    if (sizeof($sched) > 0) {
        APF_schedule_listing('exhibit', $exhibit_post, $porch_post, $sched);
    } else { // This case probably never happens. Just a precaution
        APF_set_listing_looking($exhibit_post->ID);
    }
}

function APF_scheduled_names($ID) {
    return get_term($ID, 'scheduled')->name;
}

function APF_looking_names($ID) {
    return get_term($ID, 'looking')->name;
}

function APF_category_names($ID) {
    return get_term($ID, 'category')->name;
}


/*
 * Schedule a band or exhibit on a porch 
 * * Set listing status
 * * Listing gets times from $terms arg
 * * Porch adds genres from the listing
 */
function APF_schedule_listing($listing_type, $listing_post, $porch_post, $terms)
{
    global $APF_scheduled_performance;
    global $APF_scheduled_exhibit;

    // Only need to work if listing has its own post
    if ($listing_post) {
        // listing post keeps its own record of its porch
        // So that porch_address is displayed in listing edit form
        update_field('porch_link', $porch_post->ID, $listing_post->ID);
        update_field('porch_address', $porch_post->post_title, $listing_post->ID);
        // Also store porch marker label with listing
        $porch_label = get_field('marker_label', $porch_post->ID);
        update_field('marker_label', $porch_label, $listing_post->ID);
        
        // Store porch weather plan with listing
        $weather_plan = wp_get_post_terms($porch_post->ID, 'weather')[0]->term_id;
        wp_set_post_terms($listing_post->ID, array($weather_plan), 'weather', False);

        // reset status for listing post
        if ('band' == $listing_type) {
            wp_set_post_terms($listing_post->ID, array(
                $APF_scheduled_performance
            ), 'status', False);
        } elseif ('exhibit' == $listing_type) {
            wp_set_post_terms($listing_post->ID, array(
                $APF_scheduled_exhibit
            ), 'status', False);
        }
        // Update taxonomies
        // Band or exhibit: this listing is no longer looking for porch at any time
        wp_set_post_terms($listing_post->ID, array(), 'looking', False);
        // Bands
        if ('band' == $listing_type) {
            $scheduled_terms = [];
            // Set times/categories for band
            wp_set_post_categories($listing_post->ID, array(), False);
            if ($terms) {
                // num_order_code gets marker_label as integer and start_time ID as decimal
                update_field('num_order_code', $porch_label . '.' . strval($terms[0]), $listing_post->ID);
                // Band terms are IDs from porch slot times. Convert to names ('12pm', '1pm' etc)
                foreach ($terms as $t) {
                    wp_set_post_categories($listing_post->ID, array($t), True);
                    $scheduled_terms[] = get_term($t, 'category')->name;
                }
            }
            // Band 'scheduled' taxonomy gets its assigned porch times
            wp_set_post_terms($listing_post->ID, $scheduled_terms, 'scheduled', False);
            // Porch 'performance_genre' gets all terms from band 'genre'
            wp_set_post_terms($porch_post->ID, get_field('genre', $listing_post->ID), 'performance_genre', True);
        // Exhibit
        } elseif ('exhibit' == $listing_type) {
            // Exhibit 'scheduled' taxonnomy gets its assigned porch times 
            if ($terms) {
                // num_order_code gets marker_label as integer and start_time ID as decimal
                update_field('num_order_code', $porch_label . '.' . strval($terms[0]), $listing_post->ID);
                wp_set_post_terms($listing_post->ID, $terms, 'scheduled', False);
            }
            // Porch 'exhibit_genre' gets all terms from exhibit 'genre'
            wp_set_post_terms($porch_post->ID, get_field('genre', $listing_post->ID), 'exhibit_genre', True);
        }
    }
}

function APF_cancel_band($band_post, $porch_post)
{
    APF_cancel_listing('band', $band_post, $porch_post);
}

function APF_cancel_exhibit($exhibit_post, $porch_post)
{
    APF_cancel_listing('exhibit', $exhibit_post, $porch_post);
}

function APF_cancel_listing($listing_type, $listing_post, $porch_post)
{
    global $APF_porch_slots;
    
    if ($listing_post) {
        // Reinitialize porch link
        update_field('porch_link', null, $listing_post->ID);
        update_field('porch_address', '', $listing_post->ID);
        update_field('marker_label', '9999', $listing_post->ID);
        update_field('num_order_code', '9999', $listing_post->ID);
        wp_set_post_terms($listing_post->ID, array(), 'weather', False);

        // Set listing status to Looking for porch
        APF_set_listing_looking($listing_post->ID);
        // If band then find the exact porch slot and unschedule it
        if ('band' == $listing_type) {
            foreach ($APF_porch_slots as $slot) {
                $listing_link = APF_get_field('band_link', $slot, $porch_post->ID);
                if (is_object($listing_link)) {
                    // Remove band from porch slot. Be sure APF_set_porch_schedule gets called after this (see 13 lines down)
                    if ($listing_link->ID == $listing_post->ID) {
                        APF_update_field('status_of_slot', $slot, 'Looking for a band', $porch_post->ID);
                        APF_update_field('band_link', $slot, null, $porch_post->ID);
                    }
                }
            }
            // Otherwise if exhibit then unschedule porch for this exhibit
        } elseif ('exhibit' == $listing_type) {
            update_field('status_of_exhibit', 'Looking for an exhibit', $porch_post->ID);
            update_field('exhibit_link', null, $porch_post->ID);
        }
        // Update porch scheduling fields to reflect cancellation
        APF_set_porch_schedule($porch_post->ID);
    }
}

function APF_get_band_host_by($band_ref_method, $band_value, $host_ref_method, $exclude)
{
    return APF_get_listing_host_by('band', $band_ref_method, $band_value, $host_ref_method, $exclude);
}

function APF_get_exhibit_host_by($exhibit_ref_method, $exhibit_value, $host_ref_method, $exclude)
{
    return APF_get_listing_host_by('exhibit', $exhibit_ref_method, $exhibit_value, $host_ref_method, $exclude);
}

/*
 * Find host of a listing accounting for names, links, and slots
 *
 * @listing_ref_method: is $listing_value id or name
 * @host_ref_method: hosts can schedule listing by name or id, which are we searching
 *
 * With name, to do: approximate string matching
 *
 * Return one matching post, or Return FALSE if no match
 */
function APF_get_listing_host_by($listing_type, $listing_ref_method, $listing_value, $host_ref_method, $exclude)
{
    global $APF_porch_slots;
    
    // build meta query based on $host_ref_method
    $meta_query = array(
        'relation' => 'OR'
    );
    // search by listing name
    if ('name' == $host_ref_method || 'both' == $host_ref_method) {
        $listing_name = APF_get_listing_name_from($listing_ref_method, $listing_value);
        if ($listing_name != '') {
            if ('band' == $listing_type) {
                foreach ($APF_porch_slots as $slot) {
                    $meta_query[] = array(
                        'key' => 'band_name_' . $slot,
                        'value' => $listing_name,
                        'compare' => '='
                    );
                }
            } elseif ('exhibit' == $listing_type) {
                $meta_query[] = array(
                    'key' => 'exhibit_name',
                    'value' => $listing_name,
                    'compare' => '='
                );
            }
        }
    }
    // search by listing id
    if ('id' == $host_ref_method || 'both' == $host_ref_method) {
        $listing_id = APF_get_listing_id_from($listing_type, $listing_ref_method, $listing_value);
        if ($listing_id != 0) {
            if ('band' == $listing_type) {
                foreach ($APF_porch_slots as $slot) {
                    $meta_query[] = array(
                        'key' => 'band_link_' . $slot,
                        'value' => $listing_id,
                        'compare' => '='
                    );
                }
            } elseif ('exhibit' == $listing_type) {
                $meta_query[] = array(
                    'key' => 'exhibit_link',
                    'value' => $listing_id,
                    'compare' => '='
                );
            }
        }
    }
    // Search the database for listing host with the $meta_query
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
 * Return listing name, given id, post, or name
 */
function APF_get_listing_name_from($listing_ref_method, $listing_value)
{
    if ('name' == $listing_ref_method) {
        $listing_name = $listing_value;
    } elseif ('post' == $listing_ref_method) {
        $listing_name = $listing_value->post_title;
    } elseif ('id' == $listing_ref_method) {
        $listing_post = get_post($listing_value);
        if ($listing_post) {
            $listing_name = $listing_post->post_title;
        } else {
            $listing_name = '';
        }
    }
    return $listing_name;
}

/*
 * Return band id, given id, post, or name
 */
function APF_get_band_id_from($band_ref_method, $band_value)
{
    return APF_get_listing_id_from('band', $band_ref_method, $band_value);
}

/*
 * Return exhibit id, given id, post, or name
 */
function APF_get_exhibit_id_from($exhibit_ref_method, $exhibit_value)
{
    return APF_get_listing_id_from('exhibit', $exhibit_ref_method, $exhibit_value);
}

/*
 * Return listing id, given id, post, or name
 */
function APF_get_listing_id_from($listing_type, $listing_ref_method, $listing_value)
{
    if ('name' == $listing_ref_method) {
        $listings_w_name = get_posts(array(
            'numberposts' => 1,
            'post_type' => $listing_type,
            'post_status' => 'publish',
            'name' => sanitize_title($listing_value)
        ));
        if (! empty($listings_w_name)) {
            $listing_id = $listings_w_name[0]->ID;
        } else {
            $listing_id = 0;
        }
    } elseif ('post' == $listing_ref_method) {
        $listing_id = $listing_value->ID;
    } elseif ('id' == $listing_ref_method) {
        $listing_id = $listing_value;
    }
    return $listing_id;
}

function APF_initialize_new_fields()
{
    $all_porches = get_posts(array(
        'numberposts' => - 1,
        'post_type' => 'porch',
        'post_status' => 'publish'
    ));
    foreach ($all_porches as $porch) {
        $posttags = get_the_tags($porch->ID);
        if ($posttags) {
            update_field('marker_label', $posttags[0]->name, $porch->ID);
            update_field('num_order_code', $posttags[0]->name, $porch->ID);
        } else {
            update_field('marker_label', '9999', $porch->ID);
            update_field('num_order_code', '9999', $porch->ID);
        }
    }
    $all_bands = get_posts(array(
        'numberposts' => - 1,
        'post_type' => 'band',
        'post_status' => 'publish'
    ));
    foreach ($all_bands as $band) {
        $host_id = get_field('porch_link', $band->ID);
        if ($host_id) {
            $host_label = get_field('marker_label', $host_id);
            update_field('marker_label', $host_label, $band->ID);
            // num_order_code gets marker_label as integer and start_time ID as decimal
            $cats = get_the_category($band->ID);
            update_field('num_order_code', $host_label . '.' . strval($cats[0]->term_id), $band->ID);
        }
    }
    $all_exhibits = get_posts(array(
        'numberposts' => - 1,
        'post_type' => 'exhibit',
        'post_status' => 'publish'
    ));
    foreach ($all_exhibits as $exhibit) {
        $host_id = get_field('porch_link', $exhibit->ID);
        if ($host_id) {
            $host_label = get_field('marker_label', $host_id);
            update_field('marker_label', $host_label, $exhibit->ID);
            // num_order_code gets marker_label as integer but we ignore start_time for now
            update_field('num_order_code', $host_label, $exhibit->ID);
        }
    }
}
?>
