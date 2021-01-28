<?php
/* Template Name: Package Browser */
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

$args = array('post_type' => 'package');
$args['search_filter_id'] = 100000773;
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
							Found <?php echo $query->found_posts; ?> packages
						</div>
						<div id="pkgs-box-container">
						<?php
						while ($query->have_posts())
						{
							$query->the_post();
							$post = get_post();

							$date_updated = new DateTime();
							$date_updated->setTimestamp($post->updated/1000);
							$date_created = new DateTime();
							$date_created->setTimestamp($post->created/1000);

							$cover = "/wp-content/themes/aardvark-child/images/nocover.webp";
							$coverSize = "cover";
							if($post->cover){
								$cover = $post->cover;
								$coverSize = "cover";
							} elseif ($post->icon){
								$cover = $post->icon;
								$coverSize = "contain";
							}

							$premium = $post->premium ? $post->premium : null;

							switch ($post->type) {
								case "world":
									$typeIcon = "fa-globe";
									break;
								case "system":
									$typeIcon = "fa-dice-d20";
									break;
								default:
									$typeIcon = "fa-puzzle-piece";
							}

							$elements = [
								"type" => $post->type,
								"typeIcon" => $typeIcon,
								"library" => $post->library,
								"name" => $post->post_name,
								"title" => html_entity_decode($post->post_title),
								"created" => $date_created->format('d M Y'),
								"updated" => $date_updated->format('d M Y'),
								"nbAuthors" => count($post->author),
								"authors" => implode(", ", $post->author),
								"description" => strip_tags(html_entity_decode($post->post_content)),
								"installs" => $post->installs,
								"endorsements" => $post->endorsements,
								"nbComments" => get_comments_number(),
								"url"=>$cover,
								"coverSize"=>$coverSize,
								"premium" => $premium,
								"installs_supported" => !($premium == "protected")
							];
							echo $compiler->render("package-box", $elements);
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
