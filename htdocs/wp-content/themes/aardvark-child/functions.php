<?php
require ABSPATH . '../vendor/autoload.php';
require ABSPATH . '/config-hub.php';
use Handlebars\Handlebars;
use Handlebars\Loader\FilesystemLoader;

/*
 * Add your own functions here. You can also copy some of the theme functions into this file and WordPress will use these functions instead of the original functions.
 */

/**
 * Load child theme style.css
 *
 */
if (!function_exists('ghostpool_enqueue_child_styles')) {
    function ghostpool_enqueue_child_styles()
    {
        if(defined('FHUB_RELEASE_TIMESTAMP'))
            $version = FHUB_RELEASE_TIMESTAMP;
        else
            $version = AARDVARK_THEME_VERSION;
        wp_enqueue_style('ghostpool-style', get_template_directory_uri() . '/style.css', array(), AARDVARK_THEME_VERSION);
        wp_enqueue_style('ghostpool-child-style', get_stylesheet_directory_uri() . '/style.css', array('ghostpool-style'), $version);
        wp_style_add_data('ghostpool-child-style', 'rtl', 'replace');
    }
}
add_action('wp_enqueue_scripts', 'ghostpool_enqueue_child_styles');

/**
 * Load translation file in child theme
 *
 */
if (!function_exists('ghostpool_child_theme_language')) {
    function ghostpool_child_theme_language()
    {
        $language_directory = get_stylesheet_directory() . '/languages';
        load_child_theme_textdomain('aardvark', $language_directory);
    }
}
add_action('after_setup_theme', 'ghostpool_child_theme_language');

//https://css-tricks.com/wordpress-fragment-caching-revisited/
// TODO: Currently unused -- Keeping for now, remove before launch --
function fragment_cache($key, $ttl, $function)
{
    if (is_user_logged_in()) {
        call_user_func($function);
        return;
    }
    $key = apply_filters('fragment_cache_prefix', 'fragment_cache_') . $key;
    $output = get_transient($key);
    if (empty($output)) {
        ob_start();
        call_user_func($function);
        $output = ob_get_clean();
        set_transient($key, $output, $ttl);
    }
    echo $output;
}

/**
 * Display a timediff in english
 * Ex: 3 years ago
 */
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = $datetime;
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }

    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

/**
 * Retrieve the Handlebars object with its custom helpers
 */
function getHandleBars()
{
    # Set the partials files
    $partialsDir = get_stylesheet_directory() . "/templates";
    $partialsLoader = new FilesystemLoader($partialsDir,
        [
            "extension" => "mustache",
        ]
    );

    $handlebars = new Handlebars([
        "loader" => $partialsLoader,
        "partials_loader" => $partialsLoader,
    ]);

    //handlebars helpers
    $handlebars->addHelper("extension",
        function ($template, $context, $args, $source) {
            return pathinfo($context->get($args))['extension'];
        }
    );

    $handlebars->addHelper("slugify",
        function ($template, $context, $args, $source) {
            return sanitize_title($context->get($args), "error");
        }
    );

    $handlebars->addHelper("urlencode",
        function ($template, $context, $args, $source) {
            return urlencode($context->get($args));
        }
    );

    return $handlebars;
}

/**
 * Get the metadata stored for a 1:1 post/user join
 */
function get_post_user_meta($post_id, $user_id, $key = '', $force = false)
{
    global $wpdb;
    if ($user_id === 0) {
        return false;
    }

    $meta_cache = wp_cache_get($post_id . "_" . $user_id . "_" . $key);

    if (!$meta_cache || $force) {
        $_post_user_meta = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM wp_posts_users_meta WHERE post_id = %d AND user_id = %d AND meta_key = %s",
                $post_id,
                $user_id,
                $key
            ));
        wp_cache_set($post_id . "_" . $user_id . "_" . $key, $_post_user_meta, '', 60 * 60);
        return $_post_user_meta;
    } else {
        return $meta_cache;
    }

}

/**
 * Set the metadata for a 1:1 post/user join
 * Used for storing endorsements and other datas related to a package and a user
 */
function set_post_user_meta($post_id, $user_id, $key = '', $value = '')
{
    global $wpdb;
    if ($user_id === 0) {
        return false;
    }

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO wp_posts_users_meta VALUES (%d, %d, %s, %s) ON DUPLICATE KEY UPDATE meta_value = %s",
            $post_id,
            $user_id,
            $key,
            $value,
            $value
        )
    );
    wp_cache_set($post_id . "_" . $user_id . "_" . $key, $value, '', 60 * 60);
    return true;
}

/**
 * Ajax action: Endorse a package
 */
add_action('wp_ajax_endorse', 'package_endorse');
function package_endorse()
{
    $post_id = $_POST['post_id'];
    $user_id = get_current_user_id();

    if ($user_id === 0 || !is_numeric($post_id)) {
        wp_die();
    }

    $post_id = absint($post_id);
    if (!$post_id) {
        wp_die();
    }

    $endorsed = get_post_user_meta($post_id, $user_id, "endorsed", true);

    if (!$endorsed) {
        set_post_user_meta($post_id, $user_id, "endorsed", true);
        $count = (int) get_post_meta($post_id, "endorsements", true);
        $count++;
        update_post_meta($post_id, "endorsements", $count);
    }
    wp_die();
}

/**
 * Cron to update packages from the master list (Bazaar)
 * Run every 5 minutes
 * This function store a the latest update date in the database and run it against the Bazaar "updated" property it to know if a package has been updated
 * since the last cron execution
 * TODO: This function is not resilient. Need to add alerts (email should be enough) in case of failure to update, and add a failsafe if the cron takes more than 5 minutes to execute
 */
add_action('packages_update_all', 'cron_package_update_all');
function cron_package_update_all()
{
    global $wpdb;
    $request = wp_remote_get('https://eu.forge-vtt.com/api/bazaar/');
    if (!is_wp_error($request)) {
        $body = wp_remote_retrieve_body($request);
        $data = json_decode($body, true, 512, JSON_INVALID_UTF8_IGNORE);

        //Last update timestamp
        $lastUpdate = (int) get_option("packages_last_update");
        echo "Last update: $lastUpdate <br>";
        $maxUpdate = $lastUpdate;
        $currentListOfPackage = [];
        foreach ($data['packages'] as $pkg) {
            $currentListOfPackage[] = $pkg['name'];
            //The bazaar "updated" is more recent than the FHub timestamp. New stuff got added or updated for this package
            if ($pkg['updated'] > $lastUpdate) {

                if ($pkg['updated'] > $maxUpdate) {
                    $maxUpdate = $pkg['updated'];
                }
                echo "Package needs to be updated: " . $pkg['name'] . " <br>";
                //Check if the post exists in the BDD.
                $post_id = $wpdb->get_var(
                    $wpdb->prepare("SELECT ID FROM wp_posts WHERE post_type = 'package' AND post_name = %s",
                        $pkg['name']
                    )
                );

                //prepapre metadatas

                //from the bazaar "package" endpoint
                $meta = [];
                $meta["author"] = $pkg['authors'];
                $meta["type"] = $pkg['type'];
                $meta["real_name"] = $pkg['name'];
                if (isset($pkg['systems'])) {
                    $meta["systems"] = $pkg['systems'];
                }

                if (isset($pkg['media'])) {
                    foreach ($pkg['media'] as $media) {
                        if ($media['type'] == 'cover') {
                            $meta['cover'] = $media['url'];
                        }

                        if ($media['type'] == 'icon') {
                            $meta['icon'] = $media['url'];
                        }
                    }
                }
                $meta['installs'] = $pkg['installs'];
                $meta['latest'] = $pkg['latest'];
                $meta['created'] = $pkg['created'];
                $meta['updated'] = $pkg['updated'];
                $meta['description_full'] = $pkg['description_full'];
                $meta['url'] = $pkg['url'];
                if (isset($pkg['premium'])) {
                    $meta['premium'] = $pkg['premium'];
                }

                if (isset($pkg['library'])) {
                    $meta['library'] = $pkg['library'];
                }

                if (isset($pkg['languages'])) {
                    $meta['languages'] = $pkg['languages'];
                }

                $tags = array_values($pkg['tags']);
                foreach($tags as &$tag){
                    $tag = sanitize_title($tag);
                }
                //from the "manifest" file
                $requestManifest = wp_remote_get('https://eu.forge-vtt.com/api/bazaar/manifest/' . $pkg['name'] . '?manifest=1');
                if (!is_wp_error($request)) {
                    echo "Retrived manifest for: " . $pkg['name'] . " <br>";
                    $body_manifest = wp_remote_retrieve_body($requestManifest);
                    $manifest = json_decode($body_manifest, true, 512, JSON_INVALID_UTF8_IGNORE);
                    $manifest = $manifest['manifest'];

                    if (isset($manifest['authors'])) {
                        $meta['authors_full'] = $manifest['authors'];
                    }

                    if (isset($manifest['download'])) {
                        $meta['download'] = $manifest['download'];
                    }

                    if (isset($manifest['dependencies'])) {
                        $meta['dependencies'] = $manifest['dependencies'];
                    }

                    if(isset($manifest['minimumCoreVersion']))
                        $meta['minimumCoreVersion'] = $manifest['minimumCoreVersion'];

                    if(isset($manifest['compatibleCoreVersion']))
                        $meta['compatibleCoreVersion'] = $manifest['compatibleCoreVersion'];

                    if (isset($manifest['bugs'])) {
                        $meta['bugs'] = $manifest['bugs'];
                    }

                    if (isset($manifest['readme'])) {
                        $meta['readme'] = $manifest['readme'];
                    }

                    $meta['manifest'] = $manifest['manifest'];
                    if (isset($manifest['media'])) {
                        $meta['media'] = $manifest['media'];
                    }
                }

                //If it doesn't exist, insert a new one
                if (is_null($post_id)) {
                    echo "New package, insert: " . $pkg['name'] . " <br>";
                    $meta['endorsements'] = 0;
                    $post_id = wp_insert_post(
                        array(
                            'post_author' => 1,
                            'post_title' => $pkg['title'],
                            'post_content' => $pkg['short_description'],
                            'post_status' => 'publish',
                            'post_type' => 'package',
                            'comment_status' => 'open',
                            'ping_status' => 'closed',
                            'post_name' => sanitize_title($pkg['name']),
                            //'tags_input' => $tags,
                            'meta_input' => $meta,
                        )
                    );
                } else { //Or update the existing post
                    echo "Existing package, update: " . $pkg['name'] . " <br>";
                    $data = array(
                        'ID' => $post_id,
                        'post_title' => $pkg['title'],
                        'post_content' => $pkg['short_description'],
                        'post_status' => 'publish',
                        //'tags_input' => $tags,
                        'tax-input' => array( 
                            'package_tags' => $tags
                        ),
                        'meta_input' => $meta,
                    );

                    wp_update_post($data);
                }
                wp_set_object_terms($post_id, $tags, 'package_tags');
            }
        }
        //Once we're done, we save the max update value
        update_option("packages_last_update", $maxUpdate);
        echo "Updating LastUpdate to: $maxUpdate <br>";

        //Now we try to find deleted packages to unpublish them
        $publishedPackages = $wpdb->get_col("SELECT post_name FROM wp_posts WHERE post_type = 'package' AND post_status = 'publish'");
        $deletedPackages = array_diff($publishedPackages, $currentListOfPackage);
        echo 'Unpublishing deleted packages<br>';
        $cronquery = 'UPDATE wp_posts SET post_status = "private" WHERE post_type="package" AND post_name IN ("'.implode('","',$deletedPackages).'")';
        if(count($deletedPackages)){
            echo $cronquery.'<br>';
            $wpdb->query($cronquery);
        }
        else
            echo 'No deleted packages<br>';
    }
}

/**
 * Change the OpenGraph image.
 * The dynamic part of the hook name. $network, is the network slug. Can be facebook or twitter.
 *
 * @param string $attachment_url The image we are about to add.
 */
add_filter("rank_math/opengraph/facebook/image", 'override_opengraph_image');
add_filter("rank_math/opengraph/twitter/image", 'override_opengraph_image');

function override_opengraph_image($attachment_url)
{
    global $post, $template;

    if (pathinfo($template)['filename'] == "single-package") {
        if (isset($post->cover)) {
            return $post->cover;
        }

        if (isset($post->icon)) {
            return $post->icon;
        }

    }
    return $attachment_url;
}

/**
 * Change the "Author" data from the oembed
 * Visible on a Discord embed
 */
function filter_oembed_response_data_author($data, $post, $width, $height)
{

    if ($post->post_type == "package") {
        $data['author_name'] = "Author: " . implode(", ", $post->author);
        $data['author_url'] = "";
    } else {
        unset($data['author_name']);
        unset($data['author_url']);
    }

    return $data;
};
add_filter('oembed_response_data', 'filter_oembed_response_data_author', 10, 4);

/**
 * Custom Widgets for displaying packages
 */

// Creating the widget
class fhub_widget_packages extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('fhub_widget_packages','Package List',array('description' => 'Display Foundry packages'));
    }

    // Creating widget front-end

    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', $instance['title']);

        // before and after widget arguments are defined by themes
        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        //DO CODE HERE!!

        echo $args['after_widget'];
    }

    // Widget Backend
    public function form($instance)
    {
        if (isset($instance['title'])) {
            $title = $instance['title'];
        } else {
            $title = 'New title';
        }
        // Widget admin form
        ?>
    <p>
    <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:');?></label>
    <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
    </p>
    <?php
}

    // Updating widget replacing old instances with new
    public function update($new_instance, $old_instance)
    {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        return $instance;
    }
}

// Register and load the widget
function fhub_load_widget()
{
    register_widget('fhub_widget_packages');
}
add_action('widgets_init', 'fhub_load_widget');


/**
 * Hide unwanted admin menu for users
 */
/*function custom_menu_page_removing() {
    if(is_admin()){
        remove_menu_page('vc-welcome');
        
        if(!current_user_can('administrator')){
            remove_menu_page( 'tools.php' );  
            remove_submenu_page('index.php','relevanssi_admin_search');
        }
    }
}
add_action( 'admin_init', 'custom_menu_page_removing' );  */

add_filter( 'body_class', function( $classes ){
    foreach( (array) wp_get_current_user()->roles as $role ){
        $classes[] = "user-role-$role";
    }
    return $classes;
});

/**
 * Add the user role as a body class to customize the admin area to improve friendliness
 */
add_filter( 'admin_body_class', function( $classes ){
    foreach( (array) wp_get_current_user()->roles as $role ){
        $classes .= " user-role-$role ";
    }
    return $classes;      
});

/**
 * Custom CSS for Admin Area
 */
function load_admin_style() {
    wp_enqueue_style( 'admin_css', get_stylesheet_directory_uri() . '/css/admin-style.css', false, '1.0.0' );
}
add_action( 'admin_enqueue_scripts', 'load_admin_style' );

/**
 * Custom report action for the forum
 */
remove_action('wp_ajax_wpforo_report_ajax', 'wpf_report');
add_action('wp_ajax_wpforo_report_ajax', 'wpf_report_custom');
function wpf_report_custom(){
    if(!is_user_logged_in()) return;
	
	if( !isset($_POST['reportmsg']) || !$_POST['reportmsg'] || !isset($_POST['postid']) || !$_POST['postid'] ){
		WPF()->notice->add('Error: please insert some text to report.', 'error');
		echo json_encode( WPF()->notice->get_notices() );
		exit();
    }
    $postid = intval($_POST['postid']);
    $webhookurl = WEBHOOK_FORUM_REPORT;
    $timestamp = date("c", strtotime("now"));
		
	$message = stripslashes(strip_tags(wpforo_kses(substr($_POST['reportmsg'], 0, 1000), 'email')));

    $json_data = json_encode([
        // Message
        "content" => "New report received",
        
        // Username
        "username" => "wpForo Report",

        // Embeds Array
        "embeds" => [
            [
                // Embed Title
                "title" => "Reported Message",

                // Embed Type
                "type" => "rich",

                // Embed Description
                "description" => $message,

                // URL of title link
                "url" => WPF()->post->get_post_url($postid),

                // Timestamp of embed must be formatted as ISO8601
                "timestamp" => $timestamp,

                // Embed left border color in HEX
                "color" => hexdec( "3366ff" ),

                // Author
                "author" => [
                    "name" => (WPF()->current_user['display_name'] ? WPF()->current_user['display_name'] : urldecode(WPF()->current_user['user_nicename'])),
                    "url" => WPF()->current_user['profile_url']
                ]
            ]
        ]

    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );


    $ch = curl_init( $webhookurl );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt( $ch, CURLOPT_POST, 1);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt( $ch, CURLOPT_HEADER, 0);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec( $ch );
    echo $response;
    curl_close( $ch );

    WPF()->notice->add('Message has been sent', 'success');
	echo json_encode( WPF()->notice->get_notices() );
	exit();
}

//** *Enable upload for webp image files.*/
function webp_upload_mimes($existing_mimes) {
    $existing_mimes['webp'] = 'image/webp';
    return $existing_mimes;
}
add_filter('mime_types', 'webp_upload_mimes');

//** * Enable preview / thumbnail for webp image files.*/
function webp_is_displayable($result, $path) {
    if ($result === false) {
        $displayable_image_types = array( IMAGETYPE_WEBP );
        $info = @getimagesize( $path );

        if (empty($info)) {
            $result = false;
        } elseif (!in_array($info[2], $displayable_image_types)) {
            $result = false;
        } else {
            $result = true;
        }
    }

    return $result;
}
add_filter('file_is_displayable_image', 'webp_is_displayable', 10, 2);

/**
 * Add flairs to topics (WIP)
 * Waiting for plugin update
 */
/*function add_topics_flair($topics){
    return $topics;
}
add_filter('wpforo_get_topics','add_topics_flair');*/

/**
 * Generate Package Box
 * @return Array $elements
 */
function package_box_generate_data($post){
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
        "nbComments" => $post->comment_count,
        "url"=>$cover,
        "coverSize"=>$coverSize,
        "premium" => $premium,
        "installs_supported" => !($premium == "protected")
    ];
    return $elements;
}

/**
 * Generate Creator Box
 * @return Array $elements
 */
function creator_box_generate_data($post){
    $elements = [
        "post_title" => $post->post_title,
        "endorsements" => get_field("endorsements", $post->ID),
        "comment_count" => $post->comment_count,
        "excerpt" => get_the_excerpt($post),
        "creator_tags" => get_the_terms($post->ID, "creator_tags"),
        "cover" => wp_get_attachment_url(get_post_thumbnail_id($post)),
        "url" => get_permalink($post)
    ];
    return $elements;
}

/**
 * Show admin bar for team members
 */
function admin_bar_control_function() {
    $user = wp_get_current_user();
    if ( !is_user_logged_in() || in_array( 'contributor', (array) $user->roles ) ) {
        show_admin_bar(false);
    } else {
        show_admin_bar(true);
    }
}
add_action('init', 'admin_bar_control_function', 20);

/**
 * Fix 404 title on Activate page
 */
add_filter( 'rank_math/frontend/title', function( $title ) {
    if ( bp_is_current_component( 'activate' ) ) {
      return '';
    }
    return $title;
});