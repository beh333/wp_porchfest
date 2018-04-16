<?php

if ( ! function_exists( 'twentyseventeen_comments' ) ) :
/**
 * Adapted from twentyfifteen_entry_meta
 */
function twentyseventeen_comments() {
    
    if ( 'post' == get_post_type() ) {
        if ( is_singular() || is_multi_author() ) {
            printf( '<span class="byline"><span class="author vcard"><span class="screen-reader-text">%1$s </span><a class="url fn n" href="%2$s">%3$s</a></span></span>',
                _x( 'Author', 'Used before post author name.', 'twentyfifteen' ),
                esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
                get_the_author()
                );
        }    
    }
    
    if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
        echo '<span class="comments-link">';
        /* translators: %s: post title */
        comments_popup_link( sprintf( __( 'Leave a comment<span class="screen-reader-text"> on %s</span>', 'twentyfifteen' ), get_the_title() ) );
        echo '</span>';
    }
}
endif;


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