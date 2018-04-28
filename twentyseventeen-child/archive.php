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

global $wp_query;

get_header(); ?>

<div class="wrap">

	<?php if ( have_posts() ) : ?>
		<header class="page-header">
			<?php
				the_archive_title( '<h1 class="page-title">', '</h1>' );
				the_archive_description( '<div class="taxonomy-description">', '</div>' );
				APF_view_tabs();
			?>
		</header><!-- .page-header -->
	<?php endif; ?>
	
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
		<?php
        if (have_posts()) {
            APF_the_loop();
        } else { 
            get_template_part( 'template-parts/post/content', 'none' );
        } ?>
		</main>
		<!-- #main -->
	</div>
	<!-- #primary -->
	<?php get_sidebar(); ?>
</div>
<!-- .wrap -->

<?php get_footer();
