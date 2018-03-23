<?php

/**
 * Simple helper function for make menu item objects
 *
 * @param $title -
 *            menu item title
 * @param $url -
 *            menu item url
 * @param $order -
 *            where the item should appear in the menu
 * @param int $parent
 *            - the item's parent item
 * @return \stdClass
 */
function _custom_nav_menu_item($title, $url, $order, $parent = 0)
{
    $item = new stdClass();
    $item->ID = 1000000 + $order + $parent;
    $item->db_id = $item->ID;
    $item->title = $title;
    $item->url = $url;
    $item->menu_order = $order;
    $item->menu_item_parent = $parent;
    $item->type = '';
    $item->object = '';
    $item->object_id = '';
    $item->classes = array();
    $item->target = '';
    $item->attr_title = '';
    $item->description = '';
    $item->xfn = '';
    $item->status = '';
    return $item;
}

function custom_nav_menu_items($items, $menu)
{
    // only add item to a specific menu
    if ($menu->slug == 'search-menu') {
        
        // only add profile link if user is logged in
        if (get_current_user_id()) {
            $items[] = _custom_nav_menu_item('My Page', get_author_posts_url(get_current_user_id()), 99);
        }
    }
    
    return $items;
}
// add_filter( 'wp_get_nav_menu_items', 'custom_nav_menu_items', 20, 2 );
