<?php
use GraphQL\Client;
use GraphQL\Exception\QueryError;
use GraphQL\Query;
require ABSPATH . '/config-hub.php';
get_header();

/**
 * Get settings
 *
 */
$settings = get_query_var('ghostpool_page_settings');
if ($settings && is_array($settings)) {
    extract($settings);
}

// Classes
$css_classes = array(
    'gp-posts-wrapper',
    'gp-archive-wrapper',
    $format,
    $style,
    $alignment,
);
$css_classes = trim(implode(' ', array_filter(array_unique($css_classes))));

?>

<?php ghostpool_page_header('', $header, $header_bg, $header_height);?>

<?php if ('gp-minimal-page-header' !== $header) {ghostpool_page_title('', $header);}?>

<div id="gp-content-wrapper" class="gp-container">

	<?php do_action('ghostpool_begin_content_wrapper');?>

	<div id="gp-inner-container">

		<div id="gp-content">

			<?php if ('gp-minimal-page-header' === $header) {ghostpool_page_title('', $header);}?>

			<?php if (!function_exists('elementor_theme_do_location') or !elementor_theme_do_location('archive')) {?>

				<?php if (isset($_GET['s']) && ($_GET['s'] != '')) {?>

					<div class="<?php echo esc_attr($css_classes); ?>" data-type="search"<?php if (function_exists('ghostpool_filter_variables')) {echo ghostpool_filter_variables($settings);}?>>

						<div id="gp-new-search">
							<div class="gp-divider-title"><?php esc_html_e('New Search', 'aardvark');?></div>
							<?php get_search_form();?>
						</div>

						<?php ghostpool_filter($settings);?>

						<div class="gp-section-loop <?php echo sanitize_html_class(ghostpool_option('ajax')); ?>">

							<?php if (have_posts()): ?>

								<?php global $wp_query;
                                echo ghostpool_search_results_total($wp_query->found_posts);?>

								<div class="gp-section-loop-inner">
									<?php if ($format == 'gp-posts-masonry') {?><div class="gp-gutter-size"></div><?php }?>
									<?php while (have_posts()): the_post();
                                        get_template_part('lib/sections/taxonomies/post-loop-standard');
                                    endwhile;?>
								</div>

								<?php echo ghostpool_pagination($wp_query->max_num_pages, $pagination); ?>

							<?php else: ?>

								<strong class="gp-no-items-found"><?php esc_html_e('No items found on Foundry Hub.', 'aardvark');?></strong>

							<?php endif;?>

                            <?php
                            //Search on the Wiki (foundryvtt.wiki)
                            $client = new Client(
                                'https://foundryvtt.wiki/graphql/',
                                ['Authorization' => "Bearer ".WIKI_API_TOKEN]
                            );

                            $gql = (new Query('pages'))
                                ->setSelectionSet(
                                    [
                                        (new Query('search'))
                                            ->setArguments(['query' => $_GET['s']])
                                            ->setSelectionSet(
                                                [
                                                    (new Query('results'))
                                                        ->setSelectionSet(
                                                            [
                                                                'id',
                                                                'title',
                                                                'description',
                                                                'path',
                                                                'locale',
                                                            ]
                                                        ),
                                                ]
                                            ),
                                    ]
                                );
                            // Run query to get results
                            try {
                                $results = $client->runQuery($gql);
                                $results->reformatResults(true);
                                // Reformat the results to an array and get the results of part of the array
                                $wikiArticles = $results->getData()['pages']['search']['results'];
                            }
                            catch (QueryError $exception) {
                                $wikiArticles = [];
                            }

                            if(!empty($wikiArticles)){
                                ?>
                                <h1>Results found on <a href="https://foundryvtt.wiki">foundryvtt.wiki</a></h1>
                                <p>These articles from the Foundry Wiki project matches your terms.</p>
                                <?php
                                foreach($wikiArticles as $article){
                                ?>
                                <section class="gp-post-item">
                                    <div class="gp-loop-content">
                                        <h2 class="gp-loop-title"><a href="https://foundryvtt.wiki/<?php echo $article['locale'].'/'.$article['path']; ?>" title="<?php echo $article['title']; ?>" target="_blank"><?php echo $article['title']; ?></a></h2>
                                        <div class="gp-loop-text">
                                            <p><?php echo $article['description']; ?></p>
                                        </div>
                                    </div>
                                </section>
                                <?php
                                }
                            }
                            ?>
						</div>

					</div>

				<?php } else {?>

					<p><?php esc_html_e('You left the search box empty, please enter a valid term.', 'aardvark');?></p>

				<?php }?>

			<?php }?>

		</div>

		<?php get_sidebar('left');?>

		<?php get_sidebar('right');?>

	</div>

	<?php do_action('ghostpool_end_content_wrapper');?>

	<div class="gp-clear"></div>

</div>

<?php get_footer();