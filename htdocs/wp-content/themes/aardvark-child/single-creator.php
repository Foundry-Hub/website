<?php 
/* Template Name: Creator Single View*/
wp_enqueue_style('lightgallery', get_stylesheet_directory_uri() . '/css/lightgallery.css');
get_header();

$compiler = getHandleBars();
/**
 * Get settings
 *
 */
$settings = get_query_var( 'ghostpool_page_settings' );
if ( $settings &&  is_array( $settings ) ) {
	extract( $settings );
} 

$creator = get_post()->to_array();
$creator_meta = get_post_meta($creator['ID']);

$elements = [
    "creator"=>$creator,
    "meta"=> $creator_meta
];
?>

<?php ghostpool_page_header( get_the_ID(), $header, $header_bg, $header_height ); ?>

<?php if ( 'gp-minimal-page-header' !== $header ) { ghostpool_page_title( '', $header ); } ?>

<div id="gp-content-wrapper" class="gp-container">
	
	<?php do_action( 'ghostpool_begin_content_wrapper' ); ?>
	
	<div id="gp-inner-container">

		<div id="gp-content">
		
			<?php if ( 'gp-minimal-page-header' === $header ) { ghostpool_page_title( '', $header ); } ?>
		
			<?php
                echo $compiler->render("single-creator", $elements);
                comments_template();
            ?>
		</div>

		<?php get_sidebar( 'left' ); ?>

		<?php get_sidebar( 'right' ); ?>

	</div>

	<?php do_action( 'ghostpool_end_content_wrapper' ); ?>
	
	<div class="gp-clear"></div>

</div>

<?php get_footer();