<?php
/**
 * Template part for displaying posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since 1.0
 * @version 1.2
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
	if ( is_sticky() && is_home() ) :
		echo twentyseventeen_get_svg( array( 'icon' => 'thumb-tack' ) );
	endif;
	?>
	<header class="entry-header"><div class='APF-listing-header'>
		<?php
		if ( 'post' === get_post_type() ) {
			echo '<div class="entry-meta">';
				if ( is_single() ) {
					twentyseventeen_posted_on();
				} else {
					echo twentyseventeen_time_link();
					twentyseventeen_edit_link();
				};
			echo '</div><!-- .entry-meta -->';
		};

		if ( is_single() ) {
			the_title( '<h1 class="entry-title">', '</h1>' );
		} elseif ( is_front_page() && is_home() ) {
			the_title( '<h3 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h3>' );
		} else {
			the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
		}
		?>
	</div></header><!-- .entry-header -->

	<?php if ( '' !== get_the_post_thumbnail() && ! is_single() ) : ?>
		<div class="post-thumbnail">
			<a href="<?php the_permalink(); ?>">
				<?php the_post_thumbnail( 'twentyseventeen-featured-image' ); ?>
			</a>
		</div><!-- .post-thumbnail -->
	<?php endif; ?>

	<div class="entry-content">
		<div class='APF-listing-description'>
			<?php

			the_content( sprintf(
				__( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'twentyseventeen' ),
				get_the_title()
			) ); ?>
		</div>
		 
		<div class='APF-listing-major-info'>
			<div class='APF-match'><?php 
				$all_slots = array( array( "status" => get_field('status_of_slot_1'),
							   "perf_times" => get_field('perf_times_1'),
							   "band_post" => get_field('band_link_1'),
							   "band_name" => get_field('band_name_1')
							   ),
						    array( "status" => get_field('status_of_slot_2'),
							   "perf_times" => get_field('perf_times_2'),
							   "band_post" => get_field('band_link_2'),
							   "band_name" => get_field('band_name_2')
							   )
						   );
				APF_display_all_bands_for_porch( $all_slots ); ?>
			</div>
			<div class='APF-genre'><?php the_terms( $post->ID, 'post_tag', 'Genre(s): ', ', ', ' ' ); ?></div>
		</div>

		<div class='APF-listing-minor-info'>
			<div class='APF-rain'><?php the_terms( $post->ID, 'raindate', 'Rain date: '); ?></div>
			<div class='APF-misc'><?php $field = get_field_object('capacity');  echo $field['label'] . ': ' . $field['value']; ?></div>
		</div><?php
			
		/*
		 * Back to standard content template
		 */
		wp_link_pages( array(
			'before'      => '<div class="page-links">' . __( 'Pages:', 'twentyseventeen' ),
			'after'       => '</div>',
			'link_before' => '<span class="page-number">',
			'link_after'  => '</span>',
		) );
		?>
	</div><!-- .entry-content -->

	<div class="APF-listing-footer"><?php twentyseventeen_entry_footer(); ?></div>

</article><!-- #post-## -->
