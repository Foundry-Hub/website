<?php
/* Template Name: Creator Single View*/
wp_enqueue_style('lightgallery', get_stylesheet_directory_uri() . '/css/lightgallery.css', [], FHUB_RELEASE_TIMESTAMP);
get_header();

$compiler = getHandleBars();
/**
 * Get settings
 *
 */
$settings = get_query_var('ghostpool_page_settings');
if ($settings && is_array($settings)) {
    extract($settings);
}

$creator = get_post()->to_array();
$creator_meta = get_post_meta($creator['ID']);

$canEndorse = !(get_current_user_id() !== 0 ? get_post_user_meta(get_the_ID(), get_current_user_id(), "endorsed") : false);

$images = get_field("showcase");
$showcase = [];
if(!empty($images))
	foreach ($images as $image) {
		$showcase[] = [
			"url" => esc_url($image['url']),
			"thumbnail" => esc_url($image['sizes']['thumbnail']),
		];
}
$links = [];
if (have_rows('links')) {
    while (have_rows('links')): the_row();
		$channel = get_sub_field('channel');
		$icon = "";
		$channel_text = $channel;
        switch ($channel) {
			case "discord":
				$icon = 'fab fa-discord';
				break;
            case "facebook":
				$icon = 'fab fa-facebook-square';
				break;
            case "flickr":
				$icon = 'fab fa-flickr';
				break;
            case "instagram":
				$icon = 'fab fa-instagram-square';
				break;
            case "linkedin":
				$icon = 'fab fa-linkedin';
				$channel_text = "LinkedIn";
				break;
            case "patreon":
				$icon = 'fab fa-patreon';
				break;
            case "paypal":
				$icon = 'fab fa-paypal';
				break;
            case "pinterest":
				$icon = 'fab fa-pinterest-square';
				break;
            case "reddit":
				$icon = 'fab fa-reddit-square';
				break;
            case "roll20":
				$icon = 'fas fa-dice-d20';
				$channel_text = "Roll20 Marketplace";
				break;
            case "tumblr":
				$icon = 'fab fa-tumblr-square';
				break;
            case "twitter":
				$icon = 'fab fa-twitter-square';
				break;
            case "vimeo":
				$icon = 'fab fa-vimeo-square';
				break;
            case "youtube":
				$icon = 'fab fa-youtube-square';
				break;
			case "dtrpg":
				$icon = 'fas fa-external-link-square-alt';
				$channel_text = 'DriveThruRPG';
				break;
			case "thetokenvault":
				$icon = 'fas fa-external-link-square-alt';
				$channel_text = 'The Token Vault';
				break;
			case "gitlab":
				$icon = 'fab fa-gitlab';
				$channel_text = 'Gitlab';
				break;
			case "github":
				$icon = 'fab fa-github-square';
				$channel_text = 'Github';
				break;
            default:
				$icon = 'fas fa-external-link-square-alt';
				break;
        }
        $links[] = [
            "channel" => $channel_text,
            "url" => get_sub_field('url'),
            "icon" => $icon,
        ];
    endwhile;
}

ob_start();
comments_template();
$commentsHTML = ob_get_clean(); 

$relatedPostObject = get_posts(array(
	'post_type' => 'post',
	'meta_query' => array(
		array(
			'key' => 'related_items', // name of custom field
			'value' => '"' . get_the_ID() . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
			'compare' => 'LIKE'
		)
	)
));

$relatedPostElements = [];
foreach($relatedPostObject as $related){
	$relatedPostElements[] = post_box_generate_data($related);
}

$creator_packages = get_field('creator_packages');
$relatedPackagesElements = [];
if(is_array($creator_packages) && !empty($creator_packages)){
	foreach($creator_packages as $item){
		$relatedPackagesElements[] = package_box_generate_data($item);
	}
}

$content = apply_filters('the_content', $creator['post_content']);

$elements = [
    "creator" => $creator,
	"content" => $content,
	"links" => $links,
    "endorsements" => get_field("endorsements"),
    "cover" => wp_get_attachment_url(get_post_thumbnail_id()),
    "endorsementDisabled" => $canEndorse ? '' : 'disabled="disabled"',
    "endorsementText" => $canEndorse ? "Endorse" : "Endorsed!",
	"screenshots" => $showcase,
	"creator_tags" => get_the_terms(get_the_ID(), "creator_tags"),
	"editURL" => get_edit_post_link(),
	"comments" => $commentsHTML,
	"relatedPost" => $relatedPostElements,
    "nbRelatedPost" => count($relatedPostElements),
	"relatedPackages" => $relatedPackagesElements,
	"nbRelatedPackages" => count($relatedPackagesElements)
];

?>

<?php ghostpool_page_header(get_the_ID(), $header, $header_bg, $header_height);?>

<div id="gp-content-wrapper" class="gp-container">

	<?php do_action('ghostpool_begin_content_wrapper');?>

	<div id="gp-inner-container">

		<div id="pkg-single">

			<?php
			echo $compiler->render("single-creator", $elements);
			?>
		</div>

	</div>

	<?php do_action('ghostpool_end_content_wrapper');?>

	<div class="gp-clear"></div>

</div>

<?php
wp_enqueue_script('lightgallery-js', get_stylesheet_directory_uri() . '/js/lightgallery.min.js', array('jquery'), FHUB_RELEASE_TIMESTAMP, true);
wp_enqueue_script('package-single-js', get_stylesheet_directory_uri() . '/js/single-package.js', [], FHUB_RELEASE_TIMESTAMP);
wp_add_inline_script( 'package-single-js', 'const DATA = ' . json_encode( array(
    'nonce' => wp_create_nonce('package-nonce'),
    'ajaxUrl' => admin_url( 'admin-ajax.php' )
)), 'before');
get_footer();