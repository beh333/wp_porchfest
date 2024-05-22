<?php

/**
 * Redirect user after successful login.
 *
 * @param string $redirect_to URL to redirect to.
 * @param string $request URL the user is coming from.
 * @param object $user Logged user's data.
 * @return string
 */
function APF_login_redirect( $redirect_to, $request, $user ) {
    //is there a user to check?
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        return home_url();
    }
    return $redirect_to;
} 
add_filter( 'login_redirect', 'APF_login_redirect', 10, 3 );

/**
 * Redirect user after successful logout.
 *
 * @param string $redirect_to URL to redirect to.
 * @param string $request URL the user is coming from.
 * @param object $user Logged user's data.
 * @return string
 */
function APF_logout_redirect( $redirect_to, $request, $user ) {
    //is there a user to check?
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        return home_url();
    }
    return $redirect_to;
} 
add_filter( 'logout_redirect', 'APF_logout_redirect', 10, 3 );

function APF_assign_porchfester_author_func($query_args, $r){
    $query_args['who'] = 'porchfester';
    return $query_args;
}
add_filter('wp_dropdown_users_args', 'APF_assign_porchfester_author_func', 10, 2);

function APF_tag_cloud_limit($args){
    // Check if taxonomy option of the widget is set to tags
    if ( isset($args['taxonomy']) && $args['taxonomy'] == 'post_tag' ){
        $args['number'] = 9999; // Number of tags to show
    }
    return $args;
}
add_filter( 'widget_tag_cloud_args', 'APF_tag_cloud_limit' );

function APF_remove_admin_bar() {
    if (!current_user_can('edit_others_posts')) {
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'APF_remove_admin_bar');

function remove_menus() {
    if (!current_user_can('edit_others_posts')) {
        remove_menu_page( 'index.php' );                  //Dashboard
        remove_menu_page( 'jetpack' );                    //Jetpack*
        remove_menu_page( 'edit.php' );                   //Posts
        remove_menu_page( 'upload.php' );                 //Media
        remove_menu_page( 'edit.php?post_type=page' );    //Pages
        remove_menu_page( 'edit.php?post_type=porch' );    //Pages
        remove_menu_page( 'edit.php?post_type=band' );    //Pages
        remove_menu_page( 'edit.php?post_type=exhibit' );    //Pages
        remove_menu_page( 'edit-comments.php' );          //Comments
        remove_menu_page( 'themes.php' );                 //Appearance
        remove_menu_page( 'plugins.php' );                //Plugins
        remove_menu_page( 'users.php' );                  //Users
        remove_menu_page( 'tools.php' );                  //Tools
        remove_menu_page( 'options-general.php' );        //Settings
    }
}
add_action( 'admin_menu', 'remove_menus' );

function hide_update_notice_to_all_but_admin_users()
{
    if (!current_user_can('update_core')) {
        remove_action( 'admin_notices', 'update_nag', 3 );
    }
}
add_action( 'admin_head', 'hide_update_notice_to_all_but_admin_users', 1 );

function ds_admin_theme_style() {
    if (!current_user_can('manage_options')) {
        echo '<style>.update-nag, .updated, .error, .is-dismissible { display: none; }</style>';
    }
}
/*
 * Do not disable admin notices, so that porchfesters see "view post" notices
 * i.e. comment out the two lines below
 */
//add_action('admin_enqueue_scripts', 'ds_admin_theme_style');
//add_action('login_enqueue_scripts', 'ds_admin_theme_style');

function APF_post_title()
{
    $image_url = APF_listing_icon(get_post_type());
    if ($image_url) {
        $title_class = 'APF-entry-title';
    } else {
        $title_class = 'entry_title';
    }
    $marker_with_label = APF_marker_with_label();
    if ( is_single() ) {
        the_title( '<h1 class="' . $title_class . '">', $marker_with_label.'</h1>' );
    } elseif ( is_front_page() && is_home() ) {
        // The excerpt is being displayed within a front page section, so it's a lower hierarchy than h2.
        the_title( sprintf( '<h3 class="' . $title_class . '"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a>'.$marker_with_label.'</h3>' );
    } else {
        the_title( sprintf( '<h2 class="' . $title_class . '"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a>'.$marker_with_label.'</h2>' );
    }
    if ($image_url) {
        if( current_user_can('editor') ||
            current_user_can('manager') ||
            current_user_can('administrator') ) {
                // stuff here for admins or editors ?>
                <div class="APF-post-author">
                	<ul><li><?php the_author(); ?></li><li><?php the_author_meta('user_email'); ?></li></ul>
                </div><?php
        }
    }
}

function APF_listing_icon($listing_type)
{
    $image_url = False;
    if ('porch' == $listing_type) {
        $image_url = site_url("wp-content/uploads/2019/03/home-e1551965192848.png");
    } elseif ('band' == $listing_type) {
        $image_url = site_url("wp-content/uploads/2019/02/guitar-e1551311827812.png");
    } elseif ('exhibit' == $listing_type) {
        $image_url = site_url("wp-content/uploads/2019/02/palette-e1551311695241.png");
    } else {
        $image_url = False;
    }
    if ($image_url) {?>
    <div class="APF-post-icon">
    	<a href="<?php the_permalink(); ?>"><img src="<?php echo $image_url; ?>"></a>
    </div><!-- .APF-post-icon --><?php 
    }
    return $image_url;
}

function APF_marker_with_label()
{
    $marker_label = '_';
    /*
     * Use this code after marker labels are present (one line)
     */
    //$marker_label = get_field('marker_label');
     
    if ($marker_label == '_') {
        return '';
    } elseif (($marker_label == '') || ($marker_label == '9999')) {
        return '<div class="APF-not-printed">New</div>';
    } else {
        return '<div class="APF-marker-label">'.$marker_label.'</div>';
    }   
}

function APF_major_listing_info()
{
    global $post;
    global $APF_porch_slots;
    ?>
<div class='APF-listing-major-info'>
	<div class='APF-match'><?php
	$listing_type = get_post_type();
    if (('band' == $listing_type) || ('exhibit' == $listing_type)) {
        // Custom field 'porch_link' gives us host of band without need to query
        $porch_post = get_field('porch_link');
        if ($porch_post) {
            $post = $porch_post;
            setup_postdata($post);
            ?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a> @ <?php
            wp_reset_postdata();
            $perf_times = APF_get_listing_times($listing_type, $post->ID);
            if ($perf_times) {
                ?><?php echo $perf_times; ?><?php
            } else {
                ?> Time TBA <?php
            }
        } else {
            echo 'Looking for a porch';
        }
    } elseif ('porch' == $listing_type) {
        $all_slots = array();
        foreach ($APF_porch_slots as $slot) {
            $all_slots[] = array(
                'status' => APF_get_field('status_of_slot', $slot),
                'perf_times' => APF_get_field('perf_times', $slot),
                'band_post' => APF_get_field('band_link', $slot),
                'band_name' => APF_get_field('band_name', $slot)
            );
        }
        APF_display_all_bands_for_porch($all_slots);
    }
    ?></div>
    <div class='APF-genre'><?php 
        if (('porch' == $listing_type) || ('band' == $listing_type)) {
            the_terms( $post->ID, 'performance_genre', 'Genre(s): ', ', ', ' ' ); 
        } ?>
    </div>
    <div class='APF-exhibit'><?php 
	/*
	 * display_exhibit_for_porch
	 */
	$exhibit_interest = get_field('interested_in_exhibiting');
	if ('Interested in exhibiting' == $exhibit_interest) {
	    $exhibit_status = get_field('status_of_exhibit');
	    if ($exhibit_status == 'Looking for an exhibit') {
	        echo ''; /* echo 'Looking for visual arts exhibit'; */
	    } else {
	        echo 'Visual arts exhibit: ';
	        if ($exhibit_status == 'Have an exhibit') {
	            $post = get_field('exhibit_link');
	            setup_postdata($post);
	            ?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><?php
                    wp_reset_postdata();
                } elseif ($exhibit_status == 'Have an unlisted exhibit') {
                    $exhibit_name = get_field('exhibit_name');
                    echo $exhibit_name;
                }
            }
        }
    ?></div>
    <div class='APF-exhibit-genre'><?php 
        if (('porch' == $listing_type) || ('exhibit' == $listing_type)) {
            the_terms( $post->ID, 'exhibit_genre', 'Genre(s): ', ', ', ' ' ); 
        } ?>
    </div>
</div><?php
}

function APF_get_perf_times($post_id)
{
    return APF_get_listing_times('band', $post_id);
}

function APF_get_exhibit_times($post_id)
{
    return APF_get_listing_times('exhibit', $post_id);
}
    
function APF_get_listing_times($listing_type, $post_id)
{
    global $APF_scheduled_performance;
    global $APF_scheduled_exhibit;
    
    $APF_status_terms = array($APF_scheduled_performance, $APF_scheduled_exhibit);
    $terms = get_the_terms($post_id, 'scheduled');
    if ($terms && ! is_wp_error($terms)) {
        $term_list = array();
        foreach ($terms as $term) {
            if (! in_array($term->term_id, $APF_status_terms)) {
                $term_link = get_term_link($term);
                $term_list[] = '<a href="' . esc_url($term_link) . '">' . $term->name . '</a>';
            }
        }
    }
    return join(", ", $term_list);
}

function APF_view_tabs()
{
    if (!APF_blog_page()) {
        ?>
    	<div class="APF-view-tabs">
        	<!--
               Use this tab after map codes are present
			
            <input type="button" onclick="location.href='<?php echo add_query_arg(array('apf-orderby'=>'num','view'=>'excerpt'));?>';"
        		value="List 1-9" /> 
            -->
        	<input type="button" onclick="location.href='<?php echo add_query_arg(array('apf-orderby'=>'alpha','view'=>'excerpt'));?>';"
        		value="List A-Z" /> 
            <!--
                Use this tab after registration has closed
			
        	<input type="button" onclick="location.href='<?php echo add_query_arg(array('apf-orderby'=>'new','view'=>'excerpt'));?>';"
        		value="List New" /> 
            -->
        	<input type="button" onclick="location.href='<?php echo add_query_arg('view', 'map');?>';"
        		value="Map" /><?php
            if (current_user_can('editor') || current_user_can('manager') || current_user_can('administrator')) {
                // stuff here for admins or editors
                ?><input type="button"
        		onclick="location.href='<?php echo add_query_arg('view', 'table');?>';"
        		value="Table" /><?php
            }?>
        </div><?php
    }
}

function APF_tab_active_status()
{
    return;
}

function APF_blog_page()
{
    return (strpos(site_url('blog/'), $_SERVER['REQUEST_URI']) != False);
}

/*
 * This version of Wordpress "the loop" is shared by index, archive, and search.php
 * All of them look for "view" type in $_GET: map, table, or excerpt (default)
 */
function APF_the_loop()
{   
    if (APF_blog_page()) {
        if (have_posts()) {
            /* Start the Loop */
            while (have_posts()) {
                the_post();
                get_template_part( 'template-parts/post/content', get_post_format() );
            }
            the_posts_pagination(
                array(
                    'prev_text'          => twentyseventeen_get_svg( array( 'icon' => 'arrow-left' ) ) . '<span class="screen-reader-text">' . __( 'Previous page', 'twentyseventeen' ) . '</span>',
                    'next_text'          => '<span class="screen-reader-text">' . __( 'Next page', 'twentyseventeen' ) . '</span>' . twentyseventeen_get_svg( array( 'icon' => 'arrow-right' ) ),
                    'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'twentyseventeen' ) . ' </span>',
                )
                );
            
        } else {
            get_template_part( 'template-parts/post/content', 'none' );
        }
    } else {
        // $old_view = $APF_view_type;
        if (isset($_GET['view'])) {
            $view_type = $_GET['view'];
            $APF_view_type = strtolower($view_type);
        } else { // if (!isset($APF_view_type)) {
            $APF_view_type = 'excerpt';
        }
        
        if ('map' == $APF_view_type) {
            include_once 'google-map-helpers.php';
            ?><div class="acf-map"><?php
        /*
         * 'pins' view is hidden admin capability for exporting print map data
         */
        } elseif ('pins' == $APF_view_type) {
            ?><div class="APF-table"><?php
            $fields = array(
                'Title' => array(
                    'the_title'
                ),
                'Lat' => array(
                    'APF_the_listing_loc',
                    'lat'
                ),
                'Lng' => array(
                    'APF_the_listing_loc',
                    'lng'
                ),
                'Performance' => array(
                    'APF_major_listing_info'
                )
            );
            echo '<table><tr>';
            foreach ($fields as $key => $func_to_get_value) {
                echo '<th>' . $key . '</th>';
            }
            echo '</tr>';
        } elseif ('table' == $APF_view_type) {
            ?><div class="APF-table"><?php
            $fields = array(
                'Title' => array(
                    'the_title'
                ),
                'Author' => array(
                    'the_author_meta',
                    'display_name'
                ),
                'Email' => array(
                    'the_author_meta',
                    'user_email'
                )
            );
            echo '<table><tr>';
            foreach ($fields as $key => $func_to_get_value) {
                echo '<th>' . $key . '</th>';
            }
            echo '</tr>';
        } else {
            $APF_view_type = 'excerpt';
        }
        
        /* Start the Loop */
        while (have_posts()) {
            the_post();
            if (('table' == $APF_view_type) || ('pins' == $APF_view_type)) {
                echo '<tr>';
                foreach ($fields as $key => $func_to_get_value) {
                    ?><td><?php
                    if (1 == count($func_to_get_value)) {
                        call_user_func($func_to_get_value[0]);
                    } elseif (2 == count($func_to_get_value)) {
                        call_user_func($func_to_get_value[0], $func_to_get_value[1]);
                    }
                    ?></td><?php
                }
                echo '</tr>';
            } else {
                get_template_part('template-parts/post/content', $APF_view_type);
            }
        } // End of the loop.
        
        if ('map' == $APF_view_type) {
            echo '</div>';
        } elseif (('table' == $APF_view_type) || ('pins' == $APF_view_type)) {
            echo '</table></div>';
        }
        
        the_posts_pagination(array(
            'prev_text' => twentyseventeen_get_svg(array(
                'icon' => 'arrow-left'
            )) . '<span class="screen-reader-text">' . __('Previous page', 'twentyseventeen') . '</span>',
            'next_text' => '<span class="screen-reader-text">' . __('Next page', 'twentyseventeen') . '</span>' . twentyseventeen_get_svg(array(
                'icon' => 'arrow-right'
            )),
            'before_page_number' => '<span class="meta-nav screen-reader-text">' . __('Page', 'twentyseventeen') . ' </span>'
        ));
    }
}

function APF_the_listing_loc($coord_type)
{
    $post_type = get_post_type();
    if ('porch' == $post_type) {
        $location = get_field('map_marker');
    } elseif (('band' == $post_type) || ('exhibit' == $post_type)) {
        $host_id = get_field('porch_link');
        $location = get_field('map_marker', $host_id);
    }
    if (! $location) {
        return (False);
    } else {
        echo $location[$coord_type];
        return ($location[$coord_type]);
    }
}

if (! function_exists('twentyseventeen_comments')) :

    /**
     * Adapted from twentyfifteen_entry_meta
     */
    function twentyseventeen_comments()
    {
        global $post;
        $post_type = get_post_type();
        //if (is_singular()) {
        //    printf('<span class="byline"><span class="author vcard"><span class="screen-reader-text">%1$s </span><a class="url fn n" href="%2$s">' . 'Author: ' . '%3$s</a></span></span>', _x('Author', 'Used before post author name.', 'twentyseventeen'), esc_url(get_author_posts_url(get_the_author_meta('ID'))), get_the_author());
        //}
        /*
         * Use this code on event day for attendance reporting
         */
        $event_day = false;
        if ($event_day) {
            echo '<span class="APF-email-attendance"><a href="mailto:attendance@arlingtonporchfest.org?subject=Attendance estimate for ' . $post->post_title . '&body=Hi Porchfest Organizers, the number of people I see now at ' . $post->post_title . ' is the following: ">Click & email attendance estimate!</a></span></br>';
        } elseif (! is_single() && ! post_password_required() && (comments_open() || get_comments_number())) {
            echo '<span class="comments-link">';
           
            if (('band' == $post_type) || ('porch' == $post_type) || ('exhibit' == $post_type)) {
                comments_popup_link(sprintf(__('Contact this ' . get_post_type() . '<span class="screen-reader-text"> on %s</span>', 'twentyseventeen'), get_the_title()));
            } else {
                comments_popup_link(sprintf(__('Leave a comment<span class="screen-reader-text"> on %s</span>', 'twentyseventeen'), get_the_title()));
            }
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

function APF_display_porch_times($terms, $slot_status)
{
    if ('Looking for a band' == $slot_status) {
        $taxonomy = 'looking';
    } else {
        $taxonomy = 'scheduled';
    }
    if ($terms) {
        foreach ($terms as $term) {
            $t = get_term($term, 'category')->name;
            $t_link = get_term_link($t, $taxonomy);
            ?>
<a href="<?php echo $t_link; ?>"><?php echo $t; ?></a> <?php
        }
    } else {
        echo 'Time not yet scheduled';
    }
}

function APF_display_one_band_for_porch($slot)
{
    global $post;
    if ($slot['status'] == 'NA') {
        return;
    } elseif ($slot['status'] == 'Looking for a band') {
        /*
         * Use this one line of code to hide looking slots? (What about style.css?)
         *
        return;
         */
        echo '<div class="APF-looking-slot">Looking for a band @ ';
    } elseif ($slot['status'] == 'Have a band') {
        if ($slot['band_post']) {
            $post = $slot['band_post'];
            setup_postdata($post);
            ?>
<div class="APF-scheduled-slot"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
@ 
                	<?php
            
            wp_reset_postdata();
        } else {
            echo 'Missing band name @ ';
        }
    } elseif ($slot['status'] == 'Have an unlisted band') {
        if ($slot['band_name']) {
            echo '<div class="APF-scheduled-slot">'.$slot['band_name'] . ' @ ';
        }
    } else {
        return;
    }
    if ($slot['status'] != 'NA') {
        ?><?php APF_display_porch_times( $slot['perf_times'], $slot['status'] ); ?></div><?php
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
    wp_enqueue_style( 
        'twentyseventeen', 
        get_parent_theme_file_uri( 'style.css' ),
        array(),
        wp_get_theme()->get( 'Version' ),
        'all'
    );
}

add_action('wp_enqueue_scripts', 'APF_enqueue_styles');

?>
