<?php

/*
 * This version of Wordpress "the loop" is shared by index, archive, and search.php
 * All of them look for "view" type in $_GET: map, table, or excerpt (default)
 */
function APF_the_loop() {
    // get the view type from the URL string
    $view_type = $_GET['view'];
    if (isset($view_type)) {
        $view_type = strtolower($view_type);
    }
    if ('map' == $view_type) {
        include_once 'google-map-helpers.php';
        ?><div class="acf-map"><?php
    } elseif ('table' == $view_type) {
        ?><div class="APF-table"><?php
        $fields = array(
            'Title' => array(
                'get_the_title'
            ),
            'Author' => array(
                'get_the_author_meta',
                'display_name'
            ),
            'Email' => array(
                'get_the_author_meta',
                'user_email'
            )
        );
        echo '<table><tr>';
        foreach ($fields as $key => $func_to_get_value) {
            echo '<th>' . $key . '</th>';
        }
        echo '</tr>';
    } else {
        $view_type = 'excerpt';
    }
    
    /* Start the Loop */
    while (have_posts()) {
        the_post();
        if ('table' == $view_type) {
            echo '<tr>';
            foreach ($fields as $key => $func_to_get_value) {
                if (1 == count($func_to_get_value)) {
                    $value = call_user_func($func_to_get_value[0]);
                } elseif (2 == count($func_to_get_value)) {
                    $value = call_user_func($func_to_get_value[0], $func_to_get_value[1]);
                }
                echo '<td>' . $value . '</td>';
            }
            echo '</tr>';
        } else {
            get_template_part('template-parts/post/content', $view_type);
        }
    } // End of the loop.
    
    if ('map' == $view_type) {
        echo '</div>';
    } elseif ('table' == $view_type) {
        echo '</table></div>';
    }
    
    the_posts_pagination(array(
        'prev_text' => twentyseventeen_get_svg(array('icon' => 'arrow-left')) . '<span class="screen-reader-text">' . __('Previous page', 'twentyseventeen') . '</span>',
        'next_text' => '<span class="screen-reader-text">' . __('Next page', 'twentyseventeen') . '</span>' . twentyseventeen_get_svg(array('icon' => 'arrow-right')),
        'before_page_number' => '<span class="meta-nav screen-reader-text">' . __('Page', 'twentyseventeen') . ' </span>'
    ));
}

if (! function_exists('twentyseventeen_comments')) :

    /**
     * Adapted from twentyfifteen_entry_meta
     */
    function twentyseventeen_comments()
    {
        if ('post' == get_post_type()) {
            if (is_singular() || is_multi_author()) {
                printf('<span class="byline"><span class="author vcard"><span class="screen-reader-text">%1$s </span><a class="url fn n" href="%2$s">%3$s</a></span></span>', _x('Author', 'Used before post author name.', 'twentyfifteen'), esc_url(get_author_posts_url(get_the_author_meta('ID'))), get_the_author());
            }
        }
        
        if (! is_single() && ! post_password_required() && (comments_open() || get_comments_number())) {
            echo '<span class="comments-link">';
            /* translators: %s: post title */
            comments_popup_link(sprintf(__('Leave a comment<span class="screen-reader-text"> on %s</span>', 'twentyfifteen'), get_the_title()));
            echo '</span>';
        }
    }
endif;


    // add_action('acf/render_field_settings/type=text', 'add_readonly_and_disabled_to_text_field');
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

// add_action('acf/render_field_settings/type=post_object', 'add_readonly_and_disabled_to_po_field');
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
        return;
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