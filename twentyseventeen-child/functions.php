<?php
//add_action('acf/render_field_settings/type=text', 'add_readonly_and_disabled_to_text_field');

function add_readonly_and_disabled_to_text_field($field)
{
    acf_render_field_setting($field, array(
        'label' => __('Read Only?', 'acf'),
        'instructions' => '',
        'type' => 'radio',
        'name' => 'readonly',
        'choices' => array(
            1 => __("Yes", 'acf'),
            0 => __("No", 'acf')
        ),
        'default' => 0,
        'layout' => 'horizontal'
    ));
    acf_render_field_setting($field, array(
        'label' => __('Disabled?', 'acf'),
        'instructions' => '',
        'type' => 'radio',
        'name' => 'disabled',
        'choices' => array(
            1 => __("Yes", 'acf'),
            0 => __("No", 'acf')
        ),
        'default' => 0,
        'layout' => 'horizontal'
    ));
}

//add_action('acf/render_field_settings/type=post_object', 'add_readonly_and_disabled_to_po_field');

function add_readonly_and_disabled_to_po_field($field)
{
    acf_render_field_setting($field, array(
        'label' => __('Read Only?', 'acf'),
        'instructions' => '',
        'type' => 'radio',
        'name' => 'readonly',
        'choices' => array(
            1 => __("Yes", 'acf'),
            0 => __("No", 'acf')
        ),
        'default' => 0,
        'layout' => 'horizontal'
    ));
    acf_render_field_setting($field, array(
        'label' => __('Disabled?', 'acf'),
        'instructions' => '',
        'type' => 'radio',
        'name' => 'disabled',
        'choices' => array(
            1 => __("Yes", 'acf'),
            0 => __("No", 'acf')
        ),
        'default' => 0,
        'layout' => 'horizontal'
    ));
}

function APF_display_porch_times($terms)
{
    if ($terms) {
        foreach ($terms as $term) {
            $t = get_term((int) $term, 'category');
            $t_link = get_term_link((int) $term, 'category');
            ?>
<a href="<?php echo $t_link; ?>"><?php echo $t->name; ?></a> <?php
        }
    } else {
        echo 'Performance time not yet scheduled';
    }
}

function APF_display_one_band_for_porch($slot)
{
    global $post;

    if ($slot['status'] == 'NA') {
        return;
    } elseif ($slot['status'] == 'Looking for a band') {
        echo 'Looking for a band @ ';
    } elseif ($slot['status'] == 'Have a band') {
        if ($slot['band_post']) {
            $post = $slot['band_post'];
            setup_postdata($post);
            ?>
<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
@ 
                	<?php
            
wp_reset_postdata();
        } else {
            echo 'Missing band name @ ';
        }
    } elseif ($slot['status'] == 'Have an unlisted band') {
        if ($slot['band_name']) {
            echo $slot['band_name'] . ' @ ';
        }
    } else {
        echo 'Slot status error @ ';
    }
    if ($slot['status'] != 'NA') {
        ?><?php APF_display_porch_times( $slot['perf_times'] ); ?><?php
    }
}

function APF_display_all_bands_for_porch($slots)
{
    foreach ($slots as $slot) {
        ?><div class='APF-listing-slot'><?php APF_display_one_band_for_porch( $slot ); ?></div><?php
    }
}

function APF_enqueue_styles()
{
    $parent_style = 'twentyseventeen-style';
    
    wp_enqueue_style($parent_style, get_template_directory_uri() . '/style.css');
    wp_enqueue_style('child-style', get_stylesheet_directory_uri() . '/style.css', array(
        $parent_style
    ), wp_get_theme()->get('Version'));
}

add_action('wp_enqueue_scripts', 'APF_enqueue_styles');

?>