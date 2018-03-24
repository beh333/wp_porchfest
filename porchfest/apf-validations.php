<?php

/*
 * Validate band name is unique
 * If not unique then post is draft, and error thrown with update messages
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
 * Validate porch has a unique location
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
 * Validate porch slot times do not overlap
 * (This is done very roughly for now)
 */
function APF_validate_slot_number($slot_for_validating, $valid, $value, $field, $input)
{
    global $APF_max_slot;
    global $APF_porch_slot_key;
    
    // bail early if value is already invalid
    if (! $valid) {
        return $valid;
    }
    $perf_times_for_validating = $value;
    if (empty($perf_times_for_validating)) {
        if (1 == $slot_for_validating || in_array($_POST['acf'][$APF_porch_slot_key['status_of_slot'][$slot_for_validating]], array(
            'Have a band',
            'Have an unlisted band',
            'Looking for a band'
        ))) {
            $valid = 'Please select at least one hour';
        }
        return $valid;
    } else {
        // Gaps OK between bands/slots but not within a band's performance
        $first_time = min($perf_times_for_validating);
        $last_time = max($perf_times_for_validating);
        if (count($perf_times_for_validating) != $last_time - $first_time + 1) {
            $valid = 'Please assign each band a contiguous set of hours without gaps';
            return $valid;
        }
        // Each band/slot must be non-overlapping and later than its predecessors
        for ($slot = $slot_for_validating + 1; $slot <= $APF_max_slot; $slot = $slot + 1) {
            $status_of_slot = $_POST['acf'][$APF_porch_slot_key['status_of_slot'][$slot]];
            if (in_array($status_of_slot, array(
                'Have a band',
                'Have an unlisted band',
                'Looking for a band'
            ))) {
                $perf_times = $_POST['acf'][$APF_porch_slot_key['perf_times'][$slot]];
                foreach ($perf_times_for_validating as $t1) {
                    foreach ($perf_times as $t2) {
                        if ($t1 == $t2) {
                            $valid = get_term_by('id', $t1, 'category')->name . ' cannot be assigned to multiple bands';
                            return $valid;
                        }
                        if ($t1 > $t2) {
                            $valid = 'Please schedule bands in order. Currently band ' . $slot . ' is scheduled before band ' . $slot_for_validating;
                            return $valid;
                        }
                    }
                }
            }
        }
    }
    return $valid;
}

function APF_validate_slot_1($valid, $value, $field, $input)
{
    return (APF_validate_slot_number(1, $valid, $value, $field, $input));
}
add_filter('acf/validate_value/name=perf_times_1', 'APF_validate_slot_1', 10, 4);

function APF_validate_slot_2($valid, $value, $field, $input)
{
    return (APF_validate_slot_number(2, $valid, $value, $field, $input));
}
add_filter('acf/validate_value/name=perf_times_2', 'APF_validate_slot_2', 11, 4);

function APF_validate_slot_3($valid, $value, $field, $input)
{
    return (APF_validate_slot_number(3, $valid, $value, $field, $input));
}
add_filter('acf/validate_value/name=perf_times_3', 'APF_validate_slot_3', 12, 4);

function APF_validate_slot_4($valid, $value, $field, $input)
{
    return (APF_validate_slot_number(4, $valid, $value, $field, $input));
}
add_filter('acf/validate_value/name=perf_times_4', 'APF_validate_slot_4', 13, 4);

/*
 * Validate porch links to band that is available.
 *
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
    $porch_post = APF_get_band_host_by('id', $value, 'id', array(
        $_POST['post_ID']
    ));
    if ($porch_post) {
        $valid = $band_post->post_title . ' already scheduled at ' . $porch_post->post_title;
    }
    return $valid;
}
add_filter('acf/validate_value/name=band_link_1', 'APF_validate_linked_match', 10, 4);
add_filter('acf/validate_value/name=band_link_2', 'APF_validate_linked_match', 10, 4);
add_filter('acf/validate_value/name=band_link_3', 'APF_validate_linked_match', 10, 4);
add_filter('acf/validate_value/name=band_link_4', 'APF_validate_linked_match', 10, 4);

/*
 * Validate when porch writes in band name then band is unregistered
 */
function APF_validate_named_match($valid, $value, $field, $input)
{
    // bail early if value is already invalid
    if (! $valid || ($value == '')) {
        return $valid;
    }
    // Is this band hosted elsewhere?
    $porch_post = APF_get_band_host_by('name', $value, 'both', array(
        $_POST['post_ID']
    ));
    // Yes it is
    if ($porch_post) {
        $valid = $value . ' already scheduled at ' . $porch_post->post_title;
    } // Not hosted elsewhere. Is it registered?
    else {
        $same_name = get_posts(array(
            'numberposts' => - 1,
            'post_type' => 'band',
            'name' => sanitize_title($value)
        ));
        // Yes it is
        if (! empty($same_name)) {
            $valid = $value . ' already registered. Please link to their listing';
        }
    }
    return $valid;
}
add_filter('acf/validate_value/name=band_name_1', 'APF_validate_named_match', 10, 4);
add_filter('acf/validate_value/name=band_name_2', 'APF_validate_named_match', 10, 4);
add_filter('acf/validate_value/name=band_name_3', 'APF_validate_named_match', 10, 4);
add_filter('acf/validate_value/name=band_name_4', 'APF_validate_named_match', 10, 4);

/*
 * Confirmed cancelation of a band performance
 */
function APF_confirm_band_cancel($post_id)
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
        $host = APF_get_band_host_by('id', $post_id, 'id', array());
        if ($host) {
            APF_schedule_band($band_post, $band_post->post_title, $host, array(), False); // False => Cancel
        } // else error
    }
    return;
}
add_action('acf/save_post', 'APF_confirm_band_cancel', 20);

/*
 * Special wp admin notice for draft post => dup band name
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

