<?php
/**
 * Template part for displaying posts with excerpts
 *
 * Used in Search Results and for Recent Posts in Front Page panels.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since 1.0
 * @version 1.2
 */
global $post
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<header class="entry-header">
		<?php if ( 'post' === get_post_type() ) : ?>
			<div class="entry-meta">
				<?php
				echo twentyseventeen_time_link();
				twentyseventeen_edit_link();
				?>
			</div><!-- .entry-meta -->
		<?php elseif ( 'page' === get_post_type() && get_edit_post_link() ) : ?>
			<div class="entry-meta">
				<?php twentyseventeen_edit_link(); ?>
			</div><!-- .entry-meta -->
		<?php endif;
		APF_post_title();
	?></header><!-- .entry-header -->


	<div class="entry-summary">
		<div class="APF-listing-description">
		<?php the_excerpt(); ?></div><?php 
		APF_major_listing_info(); ?>
		<div class='APF-listing-minor-info'>
    		<div class='APF-rain'><?php the_terms( $post->ID, 'raindate', 'Rain date: '); ?></div>
    		<div class='APF-misc'><?php 
                  $post_type = get_post_type();
                  $size = False;
                  if ('porch' == $post_type) {
                      $size = 'capacity';
                  } elseif ('band' == $post_type) {
                      $size = 'size';
                  }  
                  if ($size) {
                      $field = get_field_object($size); echo $field['label'] . ': ' . $field['value']; 
                  }?></div><!-- APF-misc -->
		</div><!-- APF-listing-minor-info -->
	</div><!-- .entry-summary -->

	<?php
		// Author bio.
		if ( is_single() && get_the_author_meta( 'description' ) ) :
			get_template_part( 'author-bio' );
		endif;
	?>

	<div class="APF-listing-footer">
    	<footer class="entry-footer">
    		<?php twentyseventeen_comments(); ?>
    		<?php edit_post_link( __( 'Edit', 'twentyseventeen' ), '<span class="edit-link">', '</span>' ); ?>
    	</footer><!-- .entry-footer -->
	</div><!-- .APF-listing-footer -->
	
</article><!-- #post-## -->
