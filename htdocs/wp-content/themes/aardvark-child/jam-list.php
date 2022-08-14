<?php
/* Template Name: Jam List */

//Include the package jam config from the root directory
require_once($_SERVER['DOCUMENT_ROOT'] . '/JamConfig.php');

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

//Get all the packages nominated for the jam from every category
$args = array(
    'post_type' => 'package',
    'post__in' => array_merge(
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
    )
);
$query = new WP_Query($args);

?>

<?php ghostpool_page_header( get_the_ID(), $header, $header_bg, $header_height ); ?>

<?php if ( 'gp-minimal-page-header' !== $header ) { ghostpool_page_title( '', $header ); } ?>

<div id="gp-content-wrapper" class="gp-container">

	<?php do_action( 'ghostpool_begin_content_wrapper' ); ?>

	<div id="gp-inner-container">
		<div id="gp-content">
			<div id="jam-list">
				<?php
					if ( $query->have_posts() )
					{
						?>
						<div id="creators-container">
						<?php
                        $category_list_elements = array();
                        foreach(PACKAGE_JAM_CATEGORIES as $category => $category_name)
                        {
                            $category_list_elements[$category] = array(
                                'category' => $category,
                                'category_name' => $category_name,
                                'package-rows' => array()
                            );
                        }
                        
						while ($query->have_posts())
						{
							$query->the_post();
							$post = get_post();
							add_filter( 'excerpt_length', function( $length ) { return 160; } );
							$elements = package_jam_row_generate_data($post);
							
                            //Add the package to the correct categories

                            foreach($elements['categories'] as $category)
                            {
                                $category_list_elements[$category]['package-rows'][] = $elements;
                            }
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
