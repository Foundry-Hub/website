<?php
/* Template Name: Package Single View */
wp_enqueue_style('lightgallery', get_stylesheet_directory_uri() . '/css/lightgallery.css', [], FHUB_RELEASE_TIMESTAMP);
wp_enqueue_style('jquery-modal', get_stylesheet_directory_uri() . '/css/modal.css', [], FHUB_RELEASE_TIMESTAMP);
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
$packageName = get_query_var('package');

$post_id = $wpdb->get_var(
    $wpdb->prepare("SELECT ID FROM wp_posts WHERE post_type = 'package' AND post_name = %s",
        $packageName
    )
);

if (is_null($post_id)) {
    $elements = [
        "message" => "Couldn't retrieve package data. Please verify package name or try again later.",
    ];
    $templateName = "error";
} else {
    $post = get_post($post_id);
    $meta = get_post_meta($post_id);

    

    foreach($meta as $key => $value){
        $unserialized = null;
        if(substr($value[0],0,2)=="a:")
            $unserialized = unserialize($value[0],["allowed_classes" => ["Array"]]);
            
        $meta[$key] = empty($unserialized) ? (string)$value[0]:$unserialized;
    }

    //format the updated and created timestamps
    $date_updated = new DateTime();
    $date_updated->setTimestamp($meta['updated'] / 1000);
    $date_created = new DateTime();
    $date_created->setTimestamp($meta['created'] / 1000);

    //sort the languages
    $languages = $meta['languages'];
    if(!empty($languages) && is_array($languages)){
        usort($languages, function ($item1, $item2) {
            return $item1['name'] <=> $item2['name'];
        });
    }

    $authors = $meta['authors_full'];
    if(!is_array($authors))
        $authors = [];
    if (!$authors || !is_array($authors[0])) {
        foreach ($post->author as $name) {
            $authors[] = ["name" => $name];
        }
    } else {
        foreach ($authors as $key => $author) {
            if (!empty($author["email"])) {
                $authors[$key]["email"] = antispambot($author["email"]);
            }

            if (!empty($author["twitter"])) {
                $authors[$key]["twitter"] = str_replace("@", "", $author["twitter"]);
            }

            if (!empty($author["reddit"])) {
                $authors[$key]["reddit"] = str_replace("u/", "", $author["reddit"]);
            }

            if (!empty($author["discord"])) {
                $authors[$key]["discord"] = str_replace("@", "", $author["discord"]);
            }

            if (!empty($author["ko-fi"])) {
                $authors[$key]["ko-fi"] = str_replace("https://ko-fi.com/", "", $author["ko-fi"]);
            }

        }
    }
    $canEndorse = !(get_current_user_id() !== 0 ? get_post_user_meta(get_the_ID(), get_current_user_id(), "endorsed") : false);

    if (!$meta['cover']) {
        $cover = "/wp-content/themes/aardvark-child/images/nocover-big.webp";
    } else {
        $cover = $meta['cover'];
    }

    //Get all screenshots
    $allScreenshots = [];
    if(is_array($meta['media']) && !empty($meta['media']))
        foreach($meta['media'] as $value)
            if($value['type'] == "screenshot"){

                //For ManifestPlus compatibility with previous revisions
                if(isset($value['link']))
                    $value['url'] = $value['link'];

                $allScreenshots[] = $value;
            }

    //detect video types and create needed metadata
    $allVideos = [];
    if(is_array($meta['media']) && !empty($meta['media']))
        foreach($meta['media'] as $value)
            if($value['type'] == "video"){

                //For ManifestPlus compatibility with previous revisions
                if(isset($value['link']))
                    $value['url'] = $value['link'];

                $allVideos[] = $value;
            }
                

    $videos = $loopedVideos = $youtube = $vimeo = [];
    if (!empty($allVideos)) {
        foreach ($allVideos as $vid) {
            if (empty($vid['url'])) {
                continue;
            }

            $type = "video";
            $urlInfo = parse_url($vid['url']);
            if ($urlInfo === false) {
                continue;
            }

            if ($urlInfo['host'] == "www.youtube.com" || $urlInfo['host'] == "youtu.be") {
                $type = "youtube";
            } else if ($urlInfo['host'] == "vimeo.com") {
                $type = "vimeo";
            } else if (!empty($vid['loop'])) {
                $type = "loopedVideo";
            }

            switch ($type) {
                case "youtube":
                    if ($urlInfo['host'] == "youtu.be") {
                        $vid['id'] = $urlInfo['path'];
                    } else {
                        parse_str($urlInfo['query'], $getArray);
                        $vid['id'] = $getArray['v'];
                    }
                    $youtube[] = $vid;
                    break;
                case "vimeo":
                    $arr_vimeo = unserialize(file_get_contents("https://vimeo.com/api/v2/video/" . $urlInfo['path'] . ".php"));
                    if (!$arr_vimeo) {
                        $vid['thumbnail'] = "/wp-content/themes/aardvark-child/images/nothumbnail-video.webp";
                    } else {
                        $vid['thumbnail'] = $arr_vimeo[0]['thumbnail_medium'];
                    }
                    $vimeo[] = $vid;
                    break;
                case "loopedVideo":
                    if (empty($vid['thumbnail'])) {
                        $vid['thumbnail'] = "/wp-content/themes/aardvark-child/images/nothumbnail-video.webp";
                    }

                    $loopedVideos[] = $vid;
                    break;
                default:
                    if (empty($vid['thumbnail'])) {
                        $vid['thumbnail'] = "/wp-content/themes/aardvark-child/images/nothumbnail-video.webp";
                    }

                    $videos[] = $vid;
            }
        }
    }

    //get file size
    $filesize = get_transient("fhub_single_filesize_" . $post->ID);
    if ($filesize === false) {
        if (!empty($meta['download'])) {
            $headers = get_headers($meta['download'], true);
            $bytes = $headers['Content-Length'];
            if (is_numeric($bytes)) {
                $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
                for ($i = 0; $bytes > 1024; $i++) {
                    $bytes /= 1024;
                }

                $filesize = round($bytes, 2) . ' ' . $units[$i];
            } else {
                $filesize = 0;
            }

        } else {
            $filesize = 0;
        }
        set_transient("fhub_single_filesize_" . $post->ID, $filesize, 60 * 60 * 24);
    }

    //get package title for dependencies
    if (is_array($meta['dependencies']) && !empty($meta['dependencies'])) {
        $dependencies = $meta['dependencies'];
        foreach ($dependencies as $key => $dependency) {
            $title = get_transient("fhub_single_title_" . $dependency['name']);
            if ($title === false) {
                $title = $wpdb->get_var($wpdb->prepare("SELECT post_title FROM $wpdb->posts WHERE post_name = %s AND post_type='package'", $dependency['name']));
                set_transient("fhub_single_title_" . $dependency['name'], $title, 60 * 60 * 24);
            }
            $dependencies[$key]['title'] = $title;
        }
    }
    else
        $dependencies = [];

    switch ($meta['type']) {
        case "world":
            $typeIcon = "fa-globe";
            break;
        case "system":
            $typeIcon = "fa-dice-d20";
            break;
        default:
            $typeIcon = "fa-puzzle-piece";
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

    $btnBuyPrice = "";
    if(!empty($meta['premium']) && $meta['premium'] == "marketplace"){
        if(!empty($meta['price'])){
            $btnBuyPrice = "$".$meta['price'];
        } else {
            $btnBuyPrice = "Pay what you want";
        }
    }

    $elements = [
        "postID" => get_the_ID(),
        "loggedOut" => get_current_user_id() === 0,
        "package" => $meta,
        "tags" => get_the_terms(get_the_ID(), "package_tags"),
        "title" => html_entity_decode($post->post_title),
        "cover" => $cover,
        "videos" => $videos,
        "loopedVideos" => $loopedVideos,
        "youtube" => $youtube,
        "vimeo" => $vimeo,
        "screenshots" => $allScreenshots,
        "nbEndorsements" => $meta['endorsements'] ? $meta['endorsements'] : 0,
        "endorsementDisabled" => $canEndorse ? '' : 'disabled="disabled"',
        "endorsementText" => $canEndorse ? "Endorse" : "Endorsed!",
        "nbComments" => get_comments_number(),
        "latestVersion" => $meta['latest'],
        "authors" => $authors,
        "updated" => time_elapsed_string($date_updated),
        "updated_details" => $date_updated->format("l jS \of F Y h:i:s A T"),
        "created" => time_elapsed_string($date_created),
        "created_details" => $date_created->format("l jS \of F Y h:i:s A T"),
        "languages" => $languages,
        "hasAccessForge" => !empty($_COOKIE['forge_accesstoken']),
        "installs_supported" => !(!empty($meta['premium']) && $meta['premium'] == "protected"),
        "patreon_protected" => !empty($meta['premium']) && $meta['premium'] == "patreon",
        "marketplace_protected" => !empty($meta['premium']) && $meta['premium'] == "marketplace",
        "classicdownload_supported" => empty($meta['premium']),
        "btnBuyPrice" => $btnBuyPrice,
        "filesize" => $filesize,
        "dependencies" => $dependencies,
        "typeIcon" => $typeIcon,
        "comments" => $commentsHTML,
        "relatedPost" => $relatedPostElements,
        "nbRelatedPost" => count($relatedPostElements)
    ];
    $elements["gallery"] = $elements["screenshots"] || $elements["videos"];

    //Readme / Changelog / License attempt to lazyload markdown
    if(!empty($meta['readme'])){
        $elements['readme_raw'] = get_git_raw_link($meta['readme']);
    }
    if(!empty($meta['changelog'])){
        $elements['changelog_raw'] = get_git_raw_link($meta['changelog']);
    }
    if(!empty($meta['license'])){
        $elements['license_raw'] = get_git_raw_link($meta['license']);
    }

    $templateName = "single-package";

    $jsPackage = [
        "package" => [
            "name" => $meta['real_name'],
            "type" => $meta['type']
        ]
    ];
}
if (empty($_COOKIE['forge_accesstoken'])) {
    $_COOKIE['forge_accesstoken'] = "";
}

?>
<?php ghostpool_page_header(get_the_ID(), $header, $header_bg, $header_height);?>

<div id="gp-content-wrapper" class="gp-container">

	<?php do_action('ghostpool_begin_content_wrapper');?>

	<div id="gp-inner-container">

            <div id="pkg-single">
                <?php
                echo $compiler->render($templateName, $elements);
                
                ?>
            </div>
	</div>

	<?php do_action('ghostpool_end_content_wrapper');?>

	<div class="gp-clear"></div>

</div>

<?php
wp_enqueue_script('lightgallery-js', get_stylesheet_directory_uri() . '/js/lightgallery.min.js', array('jquery'), FHUB_RELEASE_TIMESTAMP, true);
wp_enqueue_script('jquery-modal-js', get_stylesheet_directory_uri() . '/js/jquery-modal.min.js', [], FHUB_RELEASE_TIMESTAMP);
wp_enqueue_script('package-single-js', get_stylesheet_directory_uri() . '/js/single-package.js', [], FHUB_RELEASE_TIMESTAMP);
wp_add_inline_script( 'package-single-js', 'const DATA = ' . json_encode( array(
    'nonce' => wp_create_nonce('package-nonce'),
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'singlePackage' => $jsPackage
)), 'before');
wp_enqueue_script('forge-js', get_stylesheet_directory_uri() . '/js/forge.js', [], FHUB_RELEASE_TIMESTAMP);
get_footer();