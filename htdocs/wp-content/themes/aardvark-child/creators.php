<?php
/* Template Name: Creator Browser */
get_header();
add_filter( 'excerpt_length', function( $length ) { return 30; } );
/**
 * Get settings
 *
 */
$settings = get_query_var( 'ghostpool_page_settings' );
if ( $settings &&  is_array( $settings ) ) {
	extract( $settings );
} 

$compiler = getHandleBars();
$templateName = "creator-list";

$args = array('post_type' => 'creator');
$args['search_filter_id'] = 100001901;
$query = new WP_Query($args);

?>

<?php ghostpool_page_header( get_the_ID(), $header, $header_bg, $header_height ); ?>

<?php if ( 'gp-minimal-page-header' !== $header ) { ghostpool_page_title( '', $header ); } ?>

<div id="gp-content-wrapper" class="gp-container">

	<?php do_action( 'ghostpool_begin_content_wrapper' ); ?>

	<div id="gp-inner-container">
		<div id="gp-content">
			<div id="creators-list">
				<?php
					if ( $query->have_posts() )
					{
						?>
						<div class="pkgs-results-number">
							Found <?php echo $query->found_posts; ?> creators
						</div>
						<div id="creators-container">
						<?php
						while ($query->have_posts())
						{
							$query->the_post();
							$post = get_post();

							$elements = [
								"post_title" => $post->post_title,
								"endorsements" => get_field("endorsements"),
								"comment_count" => $post->comment_count,
								"excerpt" => get_the_excerpt(),
								"creator_tags" => get_the_terms(get_the_ID(), "creator_tags"),
								"cover" => wp_get_attachment_url(get_post_thumbnail_id())
							];
							echo $compiler->render("creator-row", $elements);
						}
						?>
						</div>
						<div class="pkgs-page-info">
							<?php
								wp_pagenavi( array( 'query' => $query ) );
							?>
						</div>
						<?php
					}
					else
					{
						echo "No Results Found";
					}
				?>
			</div>
		</div>
		<?php get_sidebar( 'right' ); ?>
	</div>

	<?php do_action( 'ghostpool_end_content_wrapper' ); ?>

	<div class="gp-clear"></div>

</div>

<?php get_footer();
