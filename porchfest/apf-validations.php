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
 * Validate when porch writes in band name then band is unregistered
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
        $host = APF_get_band_host($post_id, $band_post, 'by_link', array());
        APF_schedule_band($band_post, $band_post->post_title, $host, array(), False); // False => Cancel
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

