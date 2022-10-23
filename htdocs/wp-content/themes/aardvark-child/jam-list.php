<?php
/* Template Name: Jam List */
//Test ici encore

//Include the package jam config from the root directory
require_once $_SERVER['DOCUMENT_ROOT'] . '/JamConfig.php';

get_header();

/**
 * Get settings
 *
 */
$settings = get_query_var('ghostpool_page_settings');
if ($settings && is_array($settings)) {
    extract($settings);
}

$compiler = getHandleBars();

$all_packages_name = array_unique(array_merge(
    PACKAGE_JAM_CATEGORIES_NOMINATIONS['best-package'],
    PACKAGE_JAM_CATEGORIES_NOMINATIONS['useful'],
    PACKAGE_JAM_CATEGORIES_NOMINATIONS['polished'],
    PACKAGE_JAM_CATEGORIES_NOMINATIONS['mind-blowing'],
    PACKAGE_JAM_CATEGORIES_NOMINATIONS['wacky'],
    PACKAGE_JAM_CATEGORIES_NOMINATIONS['gorgeous'],
    PACKAGE_JAM_CATEGORIES_NOMINATIONS['massive'],
    PACKAGE_JAM_CATEGORIES_NOMINATIONS['educational'],
    PACKAGE_JAM_CATEGORIES_NOMINATIONS['integratable'],
    PACKAGE_JAM_CATEGORIES_NOMINATIONS['first-package']
));

//Get all the packages nominated for the jam from every category
$args = array(
    'post_type' => 'package',
    'post_name__in' => $all_packages_name,
    'posts_per_page' => -1,
);
$query = new WP_Query($args);

//If the user is loggedin, get the user's votes
if (is_user_logged_in()) {
    $user = wp_get_current_user();
    $user_id = $user->ID;
    $user_votes = get_user_meta($user_id, 'package_jam_votes', true);
} else {
    $user_votes = array();
}

?>

<?php ghostpool_page_header(get_the_ID(), $header, $header_bg, $header_height);?>

<?php if ('gp-minimal-page-header' !== $header) {ghostpool_page_title('', $header);}?>

<div id="gp-content-wrapper" class="gp-container">

	<?php do_action('ghostpool_begin_content_wrapper');?>

	<div id="gp-inner-container">
		<div id="gp-content">
			<?php
				if ( ! function_exists( 'elementor_theme_do_location' ) OR ! elementor_theme_do_location( 'single' ) ) {
					get_template_part( 'lib/sections/single/page-content' ); 
				}	
			?>
			<div id="jam-list">
				<?php
					if ($query->have_posts()) {
				?>
						<div id="creators-container">
						<?php
						$elements = [];
						$category_list_elements = [];
						foreach (PACKAGE_JAM_CATEGORIES as $category => $category_name) {
							$category_list_elements[$category] = array(
								'category' => $category,
								'category_name' => $category_name,
								'category_description' => PACKAGE_JAM_CATEGORY_DESCRIPTION[$category],
								'package-rows' => array(),
								'css_display' => $category == 'best-package' ? 'block' : 'none',
								'votes_left' => !empty($user_votes[$category]) && $user_votes[$category] ? 3 - count($user_votes[$category]) : 3,
								'is_active' => $category == 'best-package' ? 'jam-category-active' : '',
								'is_logged_in' => is_user_logged_in(),
							);
						}

						while ($query->have_posts()) {
							$query->the_post();
							$post = get_post();
							$elements_row = package_jam_row_generate_data($post);

							//Add the package to the correct categories

							foreach ($elements_row['categories'] as $category) {
								//Duplicate the entry for safe modification
								$package_row = $elements_row;
								//Add a flag if the user voted for this package
								if (isset($user_votes[$category]) && in_array($post->post_name, $user_votes[$category])) {
									$package_row['user_voted'] = true;
								} else {
									$package_row['user_voted'] = false;
								}

								//Disable the vote button if the user already voted for 3 packages
								if (isset($user_votes[$category]) && count($user_votes[$category]) >= 3) {
									$package_row['votes_open'] = 'disabled';
								} else {
									$package_row['votes_open'] = '';
								}
								$category_list_elements[$category]['package-rows'][] = $package_row;
							}
						}

						//Now we shuffle all category list elements
						foreach (PACKAGE_JAM_CATEGORIES as $category => $category_name) {
							shuffle($category_list_elements[$category]['package-rows']);
						}

						$elements['category_list_elements'] = $category_list_elements;
						echo $compiler->render('jam-list', $elements);
						?>
						</div>
				<?php
					} else {
						echo "No Results Found";
					}
				?>
			</div>
		</div>
		<?php get_sidebar('right');?>
	</div>

	<?php do_action('ghostpool_end_content_wrapper');?>

	<div class="gp-clear"></div>

</div>

<?php
wp_enqueue_script('package-jam-js', get_stylesheet_directory_uri() . '/js/package-jam.js', array('jquery'), FHUB_RELEASE_TIMESTAMP, true);
wp_localize_script('package-jam-js', 'DATA', array(
    'restNonce' => wp_create_nonce('wp_rest'),
    'restUrl' => rest_url(),
	'is_logged_in' => is_user_logged_in(),
));
get_footer();
