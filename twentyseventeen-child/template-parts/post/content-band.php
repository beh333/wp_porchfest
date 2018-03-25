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
		if( current_user_can('editor') ||
		    current_user_can('manager') ||
		    current_user_can('administrator') ) {
		        // stuff here for admins or editors
		        the_author(); echo '</br>';
		        the_author_meta('user_email');
		}?>
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
			) );
?>
		</div>

		<div class='APF-listing-major-info'>
			<div class='APF-match'><?php
                // Custom field 'porch_link' gives us host of band without need to query
                $porch_post = get_field('porch_link');
                if ($porch_post) {
                    $post = $porch_post;
                    setup_postdata($post);
                    ?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a> @ <?php
                    wp_reset_postdata();
                    $perf_times = get_the_term_list($post->ID, 'category', '', ', ', ' ');
                    if ($perf_times) {
                        ?><?php echo $perf_times; ?><?php
                    } else {
                        ?> Time TBA <?php
                    }
                } else {
                    echo 'Looking for a porch';
                } ?>
            </div>

			<div class='APF-genre'><?php the_terms( $post->ID, 'post_tag', 'Genre(s): ', ', ', ' ' ); ?></div>
		</div>

		<div class='APF-listing-minor-info'>
			<div class='APF-rain'><?php the_terms( $post->ID, 'raindate', 'Rain date: '); ?></div>
			<div class='APF-misc'><?php $field = get_field_object('size');  echo $field['label'] . ': ' . $field['value']; ?></div>
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

	<?php
	if ( is_single() ) {
		twentyseventeen_entry_footer();
	}
	?>

</article><!-- #post-## -->
