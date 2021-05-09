<?php
/* Template Name: Videos */
get_header();

/**
 * Get settings
 *
 */
$settings = get_query_var( 'ghostpool_page_settings' );
if ( $settings &&  is_array( $settings ) ) {
	extract( $settings );
} 

$compiler = getHandleBars();

$args = array('post_type' => 'video');
$args['search_filter_id'] = 100006127;
$query = new WP_Query($args);

?>

<?php ghostpool_page_header( get_the_ID(), $header, $header_bg, $header_height ); ?>

<?php if ( 'gp-minimal-page-header' !== $header ) { ghostpool_page_title( '', $header ); } ?>

<div id="gp-content-wrapper" class="gp-container">

	<?php do_action( 'ghostpool_begin_content_wrapper' ); ?>

	<div id="gp-inner-container">
		<div id="gp-content">
			<div id="pkgs-list">
				<?php
					if ( $query->have_posts() )
					{
						?>
						<div class="pkgs-results-number">
							Found <?php echo $query->found_posts; ?> videos
						</div>
						<div id="videoarchive-container">
						<?php
						while ($query->have_posts())
						{
							$query->the_post();
							$post = get_post();

							$elements = [
								"link" => get_the_permalink($post),
								"cover_url" => get_the_post_thumbnail_url($post,"large"),
								"author" => get_the_authors(),
								"date" => get_the_date(),
								"categories" => get_the_terms($post,"video_categories"),
								"title" => get_the_title()
							];
							echo $compiler->render("video-box", $elements);
						}
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
