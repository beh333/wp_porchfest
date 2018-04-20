<?php

function APF_my_listings_menu_item($items, $menu)
{
    // Don't add anything in admin area.
    if (is_admin()) {
        return $items;
    }
    
    $menu_name = 'Main Menu';
    
    // If no menu found, just return the items without adding anything
    if ($menu->name != $menu_name && $menu->slug != $menu_name) {
        return $items;
    }
    
    if (get_current_user_id()) {
        $url = get_author_posts_url(get_current_user_id());
        if ($url) {
            $subitem = array(
                'text' => 'My Listings',
                'url' => $url
            );
            $parent_menu = 'View Listings';
            $parent_object_id = get_wp_object_id($parent_menu, 'nav_menu_item');
        }
    }
    
    // Find the menu item ID corresponding to the given post/page object ID
    // If no post/page found, the subitems won't have any parent (will be on 1st level)
    $parent_menu_item_id = 0;
    foreach ($items as $item) {
        if ($parent_object_id == $item->object_id) {
            $parent_menu_item_id = $item->ID;
            break;
        }
    }
    
    $menu_order = count($items) + 1;
    
    // Create objects containing all (and only) those properties from WP_Post
    // used by WP to create a menu item
    $items[] = (object) array(
        'ID' => $menu_order + 1000000000, // ID that WP won't use
        'title' => $subitem['text'],
        'url' => $subitem['url'],
        'menu_item_parent' => $parent_menu_item_id,
        'menu_order' => $menu_order,
        // These are not necessary, but PHP warning will be thrown if undefined
        'type' => '',
        'object' => '',
        'object_id' => '',
        'db_id' => '',
        'classes' => ''
    );
    $menu_order ++;
    return $items;
}

add_filter('wp_get_nav_menu_items', 'APF_my_listings_menu_item', 10, 2);

/**
 * Returns the WordPress ID of any post type or page by its title or name
 * In the case you provide an ID it will "validate" it looking for any post with that ID
 *
 * @param mixed $post_identifier
 *            The title, name or ID of the post/page
 * @param string $post_type
 *            The post type to look for (default: page)
 *            
 * @return int The ID of the post/page if any, or 0
 */
function get_wp_object_id($post_identifier, $post_type = 'page')
{
    $post_id = 0;
    
    if (get_page_by_title($post_identifier, OBJECT, $post_type)) {
        $post_id = get_page_by_title($post_identifier, OBJECT, $post_type)->ID;
    } else if (get_page_by_path($post_identifier, OBJECT, $post_type)) {
        $post_id = get_page_by_path($post_identifier, OBJECT, $post_type)->ID;
    } else if (get_post($post_identifier)) {
        $post_id = get_post($post_identifier)->ID;
    }
    
    return $post_id;
}
