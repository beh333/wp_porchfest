<?php
/**
 * The template for displaying archive pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since 1.0
 * @version 1.0
 */
get_header();
?>

<div class="wrap">

	<?php if ( have_posts() ) : ?>
		<header class="page-header">
			<?php
    the_archive_title('<h1 class="page-title">', '</h1>');
    the_archive_description('<div class="taxonomy-description">', '</div>');
    ?>
		</header>
	<!-- .page-header -->
	<?php endif; ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

<?php
include_once 'google-map-helpers.php';
if (have_posts()) {
    ?><div class="acf-map"><?php
    while (have_posts()) {
        the_post();
        $location = get_field('map_marker');
        ?>
		<div class="marker" data-lat="<?php echo $location['lat']; ?>"
				data-lng="<?php echo $location['lng']; ?>">
			<?php get_template_part( 'template-parts/post/content', 'porch' ); ?>
		</div><?php
    }
    ?>
    </div><?php
    
    the_posts_pagination(array(
        'prev_text' => twentyseventeen_get_svg(array('icon' => 'arrow-left')) . '<span class="screen-reader-text">' . __('Previous page', 'twentyseventeen') . '</span>',
        'next_text' => '<span class="screen-reader-text">' . __('Next page', 'twentyseventeen') . '</span>' . twentyseventeen_get_svg(array('icon' => 'arrow-right')),
        'before_page_number' => '<span class="meta-nav screen-reader-text">' . __('Page', 'twentyseventeen') . ' </span>'
    ));
} else {
    get_template_part('template-parts/post/content', 'none');
}
?>		
		</main>
		<!-- #main -->
	</div>
	<!-- #primary -->
	<?php get_sidebar(); ?>
</div>
<!-- .wrap -->

<?php

get_footer();
