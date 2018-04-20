<?php
/**
 * Template part for displaying map markers
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since 1.0
 * @version 1.2
 */

$post_type = get_post_type();
if ('porch' == $post_type) {
    $location = get_field('map_marker');
} elseif ('band' == $post_type) {
    $host_id = get_field('porch_link');
    $location = get_field('map_marker',$host_id);
    if (!$location) {
        return;
    }
} else {
    return;
}

?>
<div class="marker" data-lat="<?php echo $location['lat']; ?>"
		data-lng="<?php echo $location['lng']; ?>">
	<?php get_template_part( 'template-parts/post/content', 'infowindow' ); ?>
</div>
