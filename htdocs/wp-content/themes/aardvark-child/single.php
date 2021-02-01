<?php get_header();

/**
 * Get settings
 *
 */
$settings = get_query_var( 'ghostpool_page_settings' );
if ( $settings &&  is_array( $settings ) ) {
	extract( $settings );
} ?>

<?php ghostpool_page_header( get_the_ID(), $header, $header_bg, $header_height ); ?>

<?php if ( 'gp-minimal-page-header' !== $header ) { ghostpool_page_title( '', $header ); } ?>

<div id="gp-content-wrapper" class="gp-container">
	
	<?php do_action( 'ghostpool_begin_content_wrapper' ); ?>
	
	<div id="gp-inner-container">

		<div id="gp-content">
		
			<?php if ( 'gp-minimal-page-header' === $header ) { ghostpool_page_title( '', $header ); } ?>
		
			<?php if ( ! function_exists( 'elementor_theme_do_location' ) OR ! elementor_theme_do_location( 'single' ) ) {
				get_template_part( 'lib/sections/single/post-content' ); 
			} ?>
		</div>

		<?php get_sidebar( 'left' ); ?>

		<?php get_sidebar( 'right' ); ?>

	</div>

	<?php do_action( 'ghostpool_end_content_wrapper' ); ?>
	
	<div class="gp-clear"></div>

</div>

<?php get_footer();