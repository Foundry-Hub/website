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
        if(!defined('FHUB_RELEASE_TIMESTAMP'))
            define('FHUB_RELEASE_TIMESTAMP', AARDVARK_THEME_VERSION);

        wp_enqueue_style('ghostpool-style', get_template_directory_uri() . '/style.css', array(), AARDVARK_THEME_VERSION);
        wp_enqueue_style('ghostpool-child-style', get_stylesheet_directory_uri() . '/style.css', array('ghostpool-style'), FHUB_RELEASE_TIMESTAMP);
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
 * polyfill for php7
 */
if(!function_exists("str_starts_with")){
    function str_starts_with( $haystack, $needle ) {
        $length = strlen( $needle );
        return substr( $haystack, 0, $length ) === $needle;
    }
}

if(!function_exists("str_ends_with")){
    function str_ends_with( $haystack, $needle ) {
        $length = strlen( $needle );
        if( !$length ) {
            return true;
        }
        return substr( $haystack, -$length ) === $needle;
    }
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
            "INSERT INTO wp_posts_users_meta VALUES (%d, %d, %s, %s, NOW()) ON DUPLICATE KEY UPDATE meta_value = %s, updated = NOW()",
            $post_id,
            $user_id,
            $key,
            $value,
            $value
        )
    );
    return true;
}

/**
 * Ajax action: Endorse a package
 */
add_action('wp_ajax_endorse', 'package_endorse');
function package_endorse()
{
    if (!wp_verify_nonce($_POST['nonce'], 'package-nonce') ) {
        wp_die();
    }
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
        $count_week = (int) get_post_meta($post_id, "endorsements_week", true);
        $count_month = (int) get_post_meta($post_id, "endorsements_month", true);

        $metaValues = array(
            'endorsements'      => ++$count,
            'endorsements_week' => ++$count_week,
            'endorsements_month'=> ++$count_month
        );
        
        wp_update_post(array(
            'ID'        => $post_id,
            'meta_input'=> $metaValues,
        ));

        //Clear profile cache
        delete_transient("userEndorsements_".$user_id);
    }
    die();
}

/**
 * Ajax action: Forge API
 */
add_action('wp_ajax_forgeAPI', 'forgeAPI');
add_action('wp_ajax_nopriv_forgeAPI', 'forgeAPI');
function forgeAPI()
{
    if (!wp_verify_nonce($_POST['nonce'], 'package-nonce'))
        die();

    if(empty($_COOKIE['forge_accesstoken']))
        die();
                   
    if(!preg_match('/^(?>[a-z]{2}\.){0,1}forge-vtt\.com$/',$_POST['domain']))
        die();
    
    if(empty($_POST['method']))
        $method = $_POST['formData'] ? 'POST' : 'GET';
    else
        $method = $_POST['method'];

    $ch = curl_init();

    $headers = array();
    $headers[] = 'Authorization: Bearer '.$_COOKIE['forge_accesstoken'];

    curl_setopt($ch, CURLOPT_URL, "https://".$_POST['domain']."./api/".$_POST['endpoint']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    if($method=="POST"){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST['formData']);
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        $result = '{ code: '.curl_errno($ch).', error: '.curl_error($ch).' }';
    }
 
    curl_close($ch);
    echo $result;
    die();
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
    if(file_exists('/opt/bitnami/apps/wordpress/logs/UPDATE_RUNNING'))
        return;
    
    $file_running = fopen('/opt/bitnami/apps/wordpress/logs/UPDATE_RUNNING','x');
    fclose($file_running);

    $log = fopen('/opt/bitnami/apps/wordpress/logs/package_update_all.log','a');
    $request = wp_remote_get('https://eu.forge-vtt.com/api/bazaar/?full=1');
    if (!is_wp_error($request)) {
        $body = wp_remote_retrieve_body($request);
        $data = json_decode($body, true, 512, JSON_INVALID_UTF8_IGNORE);

        //Last update timestamp
        $lastUpdate = (int) get_option("packages_last_update");
        fwrite($log,date('d.m.Y h:i:s')." | ------------------------------------------------------ \n");
        fwrite($log,date('d.m.Y h:i:s')." | Last update: $lastUpdate \n");
        $maxUpdate = $lastUpdate;
        $currentListOfPackage = [];
        if(empty($data['packages'])){
            fwrite($log,date('d.m.Y h:i:s')." | ERROR - Empty package list. Aborting. \n");
            fclose($log);
            unlink('/opt/bitnami/apps/wordpress/logs/UPDATE_RUNNING');
            return;
        }

        foreach ($data['packages'] as $pkg) {
            //Make sure the package is supported on FHub
            if(!in_array($pkg['type'],['module','system','world']))
                continue;
            $currentListOfPackage[] = sanitize_title($pkg['name']);
            //The bazaar "updated" is more recent than the FHub timestamp. New stuff got added or updated for this package
            if ($pkg['updated'] > $lastUpdate) {

                if ($pkg['updated'] > $maxUpdate) {
                    $maxUpdate = $pkg['updated'];
                }
                fwrite($log,date('d.m.Y h:i:s')." | Package needs to be updated: " . $pkg['name'] . " \n");
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

                if (isset($pkg['price'])) {
                    $meta['price'] = $pkg['price'];
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
                    fwrite($log,date('d.m.Y h:i:s')." | Retrived manifest for: " . $pkg['name'] . " \n");
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

                    if (isset($manifest['changelog'])) {
                        $meta['changelog'] = $manifest['changelog'];
                    }

                    if (isset($manifest['license'])) {
                        $meta['license'] = $manifest['license'];
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
                    fwrite($log,date('d.m.Y h:i:s')." | New package, insert: " . $pkg['name'] . " \n");
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
                    fwrite($log,date('d.m.Y h:i:s')." | Existing package, update: " . $pkg['name'] . " \n");
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
        fwrite($log,date('d.m.Y h:i:s')." | Updating LastUpdate to: $maxUpdate  \n");

        //Now we try to find deleted packages to unpublish them
        $publishedPackages = $wpdb->get_col("SELECT post_name FROM wp_posts WHERE post_type = 'package' AND post_status = 'publish'");
        $deletedPackages = array_diff($publishedPackages, $currentListOfPackage);
        fwrite($log,date('d.m.Y h:i:s')." | Unpublishing deleted packages \n");
        $cronquery = 'UPDATE wp_posts SET post_status = "private" WHERE post_type="package" AND post_name IN ("'.implode('","',$deletedPackages).'")';
        if(count($deletedPackages)){
            fwrite($log,date('d.m.Y h:i:s')." | $cronquery \n");
            $wpdb->query($cronquery);
        }
        else
            fwrite($log,date('d.m.Y h:i:s')." | No deleted packages \n");
    }
    fclose($log);
    unlink('/opt/bitnami/apps/wordpress/logs/UPDATE_RUNNING');
}

/**
 * Cron to maintain endorsements week count
 * Called once a day
 */
add_action('update_endorsements_trend', 'cron_update_endorsements_trend');
function cron_update_endorsements_trend(){
    global $wpdb;

    $countsToRemove = $wpdb->get_results("SELECT COUNT(*) as nb, post_id FROM wp_posts_users_meta WHERE meta_key = 'endorsed' AND updated BETWEEN NOW() - INTERVAL 8 DAY AND NOW() - INTERVAL 7 DAY GROUP BY post_id", ARRAY_A);

    if(!empty($countsToRemove)){
        foreach($countsToRemove as $post){
            $wpdb->query("UPDATE wp_postmeta SET meta_value = meta_value - ".$post['nb']." WHERE meta_key = 'endorsements_week' AND post_id = ".$post['post_id']." LIMIT 1");
            echo 'Updated weekly endorsements for post '.$post['post_id'].': -'.$post['nb']."\n"; 
        }
    }

    $countsToRemove = $wpdb->get_results("SELECT COUNT(*) as nb, post_id FROM wp_posts_users_meta WHERE meta_key = 'endorsed' AND updated BETWEEN NOW() - INTERVAL 31 DAY AND NOW() - INTERVAL 30 DAY GROUP BY post_id", ARRAY_A);

    if(!empty($countsToRemove)){
        foreach($countsToRemove as $post){
            $wpdb->query("UPDATE wp_postmeta SET meta_value = meta_value - ".$post['nb']." WHERE meta_key = 'endorsements_month' AND post_id = ".$post['post_id']." LIMIT 1");
            echo 'Updated monthly endorsements for post '.$post['post_id'].': -'.$post['nb']."\n"; 
        }
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
        //Note: I'm sure there's a better way to do this but I'm tired and it works.
        if(isset($args['orderby']))
            $orderby = $args['orderby'];
        elseif(isset($instance['orderby']))
            $orderby = $instance['orderby'];
        else
            $orderby = 'installs';

        if(isset($args['maxitem']))
            $maxitem = $args['maxitem'];
        elseif(isset($instance['maxitem']))
            $maxitem = $instance['maxitem'];
        else
            $maxitem = 4;

        if(isset($args['direction']))
            $direction = $args['direction'];
        elseif(isset($instance['direction']))
            $direction = $instance['direction'];
        else
            $direction = 'horizontal';

        if(isset($args['packagetype']))
            $packagetype = $args['packagetype'];
        elseif(isset($instance['packagetype']))
            $packagetype = $instance['packagetype'];
        else
            $packagetype = 'module';

        if(!$query = wp_cache_get("widget_packages_".$orderby.$packagetype.$maxitem)){
            $args = [
                'post_type' => 'package',
                'meta_query' => [
                    [
                        'key' => $orderby,
                        'type' => 'DECIMAL(17,4)',
                        'compare' => 'EXISTS'
                    ],
                    [
                        'key' => 'type',
                        'type' => 'CHAR',
                        'value' => $packagetype
                    ]
                ],
                'orderby' => [
                    $orderby => 'DESC'
                ],
                'post_status' => ['publish'],
                'posts_per_page' => $maxitem,
                'no_found_rows' => true
            ];
            
            $query = new WP_Query($args);
            wp_cache_set("widget_packages_".$orderby.$packagetype.$maxitem,$query,'',3600);
        }
        $compiler = getHandleBars();
   
        echo '<div class="widget_package widget_package_'.$direction.'">';
        while($query->have_posts()){
            $query->the_post();
            $elements = package_box_generate_data($query->post);
            echo $compiler->render("package-box", $elements);
        }
        echo '</div>';
        wp_reset_postdata();
    }

    // Widget Backend
    public function form($instance)
    {
        if (isset($instance['title'])) {
            $title = $instance['title'];
        } else {
            $title = 'New title';
        }

        if (isset($instance['maxitem'])) {
            $maxitem = $instance['maxitem'];
        } else {
            $maxitem = 4;
        }

        if (isset($instance['direction'])) {
            $direction = $instance['direction'];
        } else {
            $direction = 'vertical';
        }

        if (isset($instance['orderby'])) {
            $orderby = $instance['orderby'];
        } else {
            $orderby = 'installs';
        }

        if (isset($instance['packagetype'])) {
            $packagetype = $instance['packagetype'];
        } else {
            $packagetype = 'module';
        }
        // Widget admin form
        ?>
        <p>
        <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:');?></label>
        <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
        <label for="<?php echo $this->get_field_id('maxitem'); ?>">Max items</label>
        <input class="widefat" id="<?php echo $this->get_field_id('maxitem'); ?>" name="<?php echo $this->get_field_name('maxitem'); ?>" type="text" value="<?php echo esc_attr($maxitem); ?>" />
        </p>
        <p>
        <label for="<?php echo $this->get_field_id('direction'); ?>">Direction</label>
        <input class="widefat" id="<?php echo $this->get_field_id('direction'); ?>" name="<?php echo $this->get_field_name('direction'); ?>" type="text" value="<?php echo esc_attr($direction); ?>" />
        </p>
        <p>
        <label for="<?php echo $this->get_field_id('orderby'); ?>">Order By</label>
        <input class="widefat" id="<?php echo $this->get_field_id('orderby'); ?>" name="<?php echo $this->get_field_name('orderby'); ?>" type="text" value="<?php echo esc_attr($orderby); ?>" />
        </p>
        <p>
        <label for="<?php echo $this->get_field_id('packagetype'); ?>">Package type</label>
        <input class="widefat" id="<?php echo $this->get_field_id('packagetype'); ?>" name="<?php echo $this->get_field_name('packagetype'); ?>" type="text" value="<?php echo esc_attr($packagetype); ?>" />
        </p>
        <?php
    }

    // Updating widget replacing old instances with new
    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['maxitem'] = (!empty($new_instance['maxitem'])) ? strip_tags($new_instance['maxitem']) : '';
        $instance['direction'] = (!empty($new_instance['direction'])) ? strip_tags($new_instance['direction']) : '';
        $instance['orderby'] = (!empty($new_instance['orderby'])) ? strip_tags($new_instance['orderby']) : '';
        $instance['packagetype'] = (!empty($new_instance['packagetype'])) ? strip_tags($new_instance['packagetype']) : '';
        return $instance;
    }
}

// Register and load the widget
function fhub_load_widget()
{
    register_widget('fhub_widget_packages');
}
add_action('widgets_init', 'fhub_load_widget');

//Create a shortcode for the widget
function call_fhub_widget_packages($args = []) {
    // normalize attribute keys, lowercase
    $args = array_change_key_case( (array) $args, CASE_LOWER );
    the_widget('fhub_widget_packages',[],$args);
}
add_shortcode('fhub_widget_packages', 'call_fhub_widget_packages');

//Create a shortcode fhub posts
function call_fhub_post_grid($args = []) {
    if(!$query = wp_cache_get("query_posts_home")){
        $args = [
            'post_type' => 'post',
            'category__not_in' => [60],
            'post_status' => ['publish'],
            'posts_per_page' => 10,
            'no_found_rows' => true
        ];
        
        $query = new WP_Query($args);
        wp_cache_set("query_posts_home",$query,'',3600);
    }
    $compiler = getHandleBars();
    add_filter( 'excerpt_length', function( $length ) { return 160; } );

    $html = '<div class="widget_post">';
    while($query->have_posts()){
        $query->the_post();
        $elements = post_box_generate_data($query->post);
        $html .= $compiler->render("single-box-home", $elements);
    }
    $html .= '</div>';
    wp_reset_postdata();
    return $html;
}
add_shortcode('fhub_post_grid', 'call_fhub_post_grid');


//Create a shortcode for a package
function call_fhub_package_box($args = []) {
    // normalize attribute keys, lowercase
    $args = array_change_key_case( (array) $args, CASE_LOWER );
    if(empty($args['name']))
        return;
    if(!$query = wp_cache_get("query_packagebox_".$args['name'])){
        $args = [
            'post_type' => 'package',
            'name' => $args['name'],
            'post_status' => ['publish'],
            'no_found_rows' => true,
            'posts_per_page' => 1,
        ];
        
        $query = new WP_Query($args);
        wp_cache_set("query_packagebox_".$args['name'],$query,'',3600);
    }
    $compiler = getHandleBars();
    add_filter( 'excerpt_length', function( $length ) { return 160; } );

    
    $html = '<div class="packagebox-inline">';
    while($query->have_posts()){
        $query->the_post();
        $elements = package_box_generate_data($query->post);
        $html .= $compiler->render("package-box", $elements);
    }
    $html .= '</div>';
    wp_reset_postdata();
    return $html;
}
add_shortcode('fhub_package_box', 'call_fhub_package_box');

/**
 * Hide unwanted admin menu for users
 */
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
        "excerpt" => wp_trim_words(get_the_excerpt($post),38,"[…]"),
        "creator_tags" => get_the_terms($post->ID, "creator_tags"),
        "cover" => wp_get_attachment_url(get_post_thumbnail_id($post)),
        "url" => get_permalink($post)
    ];
    return $elements;
}

/**
 * Generate News Box
 */
function post_box_generate_data($post){
    $the_categories = get_the_category($post->ID);
    $categories = [];
    foreach($the_categories as $category)
        $categories[] = ['name'=>$category->name, 'slug'=>$category->slug];
    

    $elements = [
        'ID' => $post->ID,
        'title' => $post->post_title,
        'url' => get_permalink($post->ID),
        'image' => wp_get_attachment_url(get_post_thumbnail_id($post->ID)),
        'datetime' => get_the_date( 'c', $post->ID),
        'datestr' => get_the_time( get_option( 'date_format' ), $post->ID),
        'comments' => $post->comment_count,
        'author' => ghostpool_author_name($post->ID),
        'excerpt' => wp_trim_words(get_the_excerpt($post->ID),38,"[…]"),
        'categories' => $categories
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
remove_action('show_admin_bar', 'wpforo_show_admin_bar');

/**
 * Fix 404 title on Activate page
 */
add_filter( 'rank_math/frontend/title', function( $title ) {
    if ( bp_is_current_component( 'activate' ) ) {
      return '';
    }
    return $title;
});

/**
 * Try to generate a RAW link to a .MD file hosted on github or gitlab
 * https://github.com/user/repo/blob/branch/FILE.md
 * https://raw.githubusercontent.com/user/repo/branch/FILE.md
 *
 * https://gitlab.com/user/repo/-/blob/branch/FILE.md
 * https://gitlab.com/user/repo/-/raw/branch/FILE.md
 */
function get_git_raw_link($url){
    if(str_ends_with($url,".md")){
        if(str_starts_with($url, "https://github.com/"))
            $url = str_replace(["https://github.com/","/blob/","/tree/"],["https://raw.githubusercontent.com/","/","/"],$url);
        elseif(str_starts_with($url, "https://gitlab.com/"))
            $url = str_replace("/blob/","/raw/",$url);
    }
    else
        $url = false;
    return $url;
}

/**
 * Ajax action: Load markdown file
 */
add_action('wp_ajax_load_markdown', 'file_load_markdown');
add_action('wp_ajax_nopriv_load_markdown', 'file_load_markdown');
function file_load_markdown()
{
    $file = @file_get_contents($_POST['url']);
    if($file === FALSE)
        return;
	$matches = [];
	preg_match_all('/\] ?\(([^\(\)]+)\)/', $file, $matches);
	// if there are links
	if(count($matches) == 2 && count($matches[0]) > 0) {
		$base =  explode('/', $_POST['url']);
		array_pop($base);
		$base = implode('/', $base);
		function is_relative($url) {
			$url = parse_url($url);
			return !isset($url['scheme']) && !isset($url['host']);
		}
		foreach(array_unique($matches[1]) as $url) {
			if(!is_relative($url)) continue;
			$file = str_replace($url, $base.'/'.$url, $file);
		}
	}
    $Parsedown = new Parsedown();
    $Parsedown->setSafeMode(true);
    echo $Parsedown->text($file);
    wp_die();
}

/**
* Google Analytics tag
*/
add_action( 'wp_head', 'add_gtags' );
function add_gtags(){
  ?>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-30929783-3"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'UA-30929783-3');
    </script>
  <?php
}

// Limit media library access
  
add_filter( 'ajax_query_attachments_args', 'wpb_show_current_user_attachments' );
function wpb_show_current_user_attachments( $query ) {
    $user_id = get_current_user_id();
    if ( $user_id && !current_user_can('activate_plugins') && !current_user_can('edit_others_posts') ) {
        $query['author'] = $user_id;
    }
    return $query;
} 

//Fix embed ratios
add_action( 'after_setup_theme', function() {
    add_theme_support( 'responsive-embeds' );
});

/**
 * Custom Endpoints API
 */

/**
 * Get package infos
 */
function api_get_package_info(WP_REST_Request $request){
    if(false === ($response = get_transient("api_package_info_".$request['package']))){
        $package = get_page_by_path($request['package'],OBJECT,'package');
        if($package){
            $response = [
                'endorsements'=> $package->endorsements,
                'installs'=> $package->installs,
                'comments'=> $package->comment_count,
                'url' => 'https://www.foundryvtt-hub.com/package/'.$request['package']
            ];
            set_transient("api_package_info_".$request['package'],$response,HOUR_IN_SECONDS);
        }
        else
            return new WP_Error( 'no_package', 'Invalid package', array( 'status' => 404 ));
    }
    return $response;
}

/**
 * Get package infos
 */
function api_get_package_shield(WP_REST_Request $request){
    $info = api_get_package_info($request);
    if(is_wp_error($info))
        return $info;

    $response = [
        'schemaVersion'=> 1,
        'logoSvg' => '<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024" preserveAspectRatio="xMidYMid meet"><g fill="#2a5080"><path d="M600.3 936.4C469.3 860.1 338.6 782.8 208 705.5 185.2 691.9 195.2 665 193.1 643.7 193.5 541.9 192 440.1 194.2 338.4 211.2 304.4 255.7 294.9 285.3 273.1 347.7 237.2 409.5 200.3 472.3 165.1 453.9 149.6 418.4 143.7 410 122.2 409.4 102.8 405.7 69.1 433.2 95.9 561.4 171.7 690.7 245.5 818.2 322.4c20.3 15.8 9.5 43 12.2 64.8-0.7 99.4 1.7 198.9-1.7 298.2-13.6 34.2-59 40.8-86.3 62.5C677.2 784.7 612.8 822.9 547.3 859.2c19.2 14.2 46.1 22.8 61.8 39.2-2.6 10.9 6.1 45.3-8.8 38zM514.5 832.6c91.9-53.6 185.1-105.3 276.8-159.2 4.4-74.9 1.4-150.3 2.2-225.4 0-31.9 0-63.8 0-95.8C699.5 298.2 606.6 242.3 512.5 188.7 476.2 205.5 442.8 229.1 407.5 248.2 348.5 282.5 289.4 316.6 230.2 350.5c0 107 0 214 0 321 82.8 51.9 168.8 99.1 252.7 149.6 10.2 3.2 20.3 17.6 31.6 11.5z"/><path d="m369.4 663.6c-3.8-10.5-1.5-22.4-1.5-33.5-0.5-5.9 2.7-9.5 8.4-7.9 96.7 0.2 193.5-0.5 290.2 0.6 8.5-0.9 17.9 0 15.7 10.8-0.9 9.8 3.2 24.2-3.6 30.9-53.2 1.7-106.4 0.8-159.6 1.1-47.9-0.2-95.9 0.5-143.8-1-1.9-0.2-4-0.1-5.8-1z"/><path d="m408.3 606.1c9.1-11.1 18.3-22.2 26.1-34.3 10.1-15.1 19.3-30.9 25.1-48.2 2.6-3.1 2.5-10.7 6.6-11.6 41.5 0.2 83 0.4 124.5 0.7 8.8 26.3 21.2 51.6 39 73.1 3.6 6.7 14 11.5 14.1 18.5-10.8 3.3-22.4 1.9-33.6 2.5-67 0-134.1 0.3-201.1-0.5L408.4 606.2Z"/><path d="M393.6 496.4c-0.4-0.9-0.5-31.9-0.4-68.9l0.4-67.2 131.8 0 131.8 0 0 68.5 0 68.5-131.6 0.4c-104.6 0.3-131.7 0-132.1-1.3z"/><path d="m672.6 472.1c-0.7-10.9-0.3-21.9-0.4-32.8 0.1-16.1 0.3-32.1 0.4-48.2 4 0.1 7.8 1.2 11.4 2.8 11.7 5 23.1 11 32.5 19.6 5.9 5.2 11.4 10.8 17.2 16.2-8.7 8.6-17.5 17.1-27.3 24.5-9.2 7-18.8 13.7-29.4 18.5-1.4 0.5-3.6 1.3-4.4-0.6z"/><path d="M365.4 462.5c-28.9-12.9-54.8-38.5-70.4-69.5-2.4-4.9-4.5-10.4-4.5-12.2l0-3.2 43.3 0c32 0 43.6 0.4 44.8 1.5 2.2 2.2 2.2 84.4 0 86.5-1.9 1.9-1.3 2.2-13.2-3.2z"/></g></svg>'
    ];
    switch($request['shield']){
        case "comments":
            $response['label'] = "Foundry Hub Comments";
            $response['message'] = $info['comments'];
            $response['color'] = "#31527D";
            break;
        default:
            $response['label'] = "Foundry Hub Endorsements";
            $response['message'] = $info['endorsements'];
            $response['color'] = "#447DC7";
    }
    return $response;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'hubapi/v1', '/package/(?P<package>[a-zA-Z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'api_get_package_info',
        'permission_callback' => '__return_true',
        'args' => array(
            'package' => array(
                'validate_callback' => function($param, $request, $key){
                    return sanitize_title($param) == $param;
                },
                'required' => true
            )
        )
    ));
});

add_action( 'rest_api_init', function () {
    register_rest_route( 'hubapi/v1', '/package/(?P<package>[a-zA-Z0-9-]+)/shield/(?P<shield>[a-zA-Z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'api_get_package_shield',
        'permission_callback' => '__return_true',
        'args' => array(
            'package' => array(
                'validate_callback' => function($param, $request, $key){
                    return sanitize_title($param) == $param;
                },
                'required' => true
            ),
            'shield' => array(
                'validate_callback' => function($param, $request, $key){
                    return in_array($param, ['endorsements','installs','comments']);
                },
                'default' => 'endorsements'
            )
        )
    ));
});