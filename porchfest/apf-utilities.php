<?php

/*
 * Include porch and band content types in standard wp search pages
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

