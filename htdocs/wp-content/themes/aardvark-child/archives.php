<?php
/* Template Name: Archives */
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

$args = array('post_type' => 'post');
$args['search_filter_id'] = 100005962;
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
							Found <?php echo $query->found_posts; ?> posts
						</div>
						<div id="archivebox-container">
						<?php
						while ($query->have_posts())
						{
							$query->the_post();
							$post = get_post();

							$categories = [];
							foreach(get_the_category() as $categoryTerm){
								$categories[] = [
									"slug" => $categoryTerm->slug,
									"name" => $categoryTerm->name
								];
							}

							$elements = [
								"link" => get_the_permalink($post),
								"cover_url" => get_the_post_thumbnail_url($post,"large"),
								"author" => get_the_authors(),
								"date" => get_the_date(),
								"nbComments" => get_comments_number($post),
								"categories" => get_the_category(),
								"title" => get_the_title()
							];
							echo $compiler->render("archive-box", $elements);
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
