<?php

// Page options		
$display_image = ( get_post_meta( get_the_ID(), 'gp_featured_image', true ) && get_post_meta( get_the_ID(), 'gp_featured_image', true ) != 'default' ) ? get_post_meta( get_the_ID(), 'gp_featured_image', true ) : ghostpool_option( 'post_featured_image' );
$image = ghostpool_image_data( ghostpool_option( 'post_image_size' ) );	
$image_source = ghostpool_option( 'post_image_source' );

?>

<article <?php post_class(); ?>>
	
	<?php if ( ! function_exists( 'pmpro_has_membership_access' ) OR ( function_exists( 'pmpro_has_membership_access' ) && pmpro_has_membership_access() ) ) { ?>
	
		<?php if ( ( has_post_thumbnail() OR $image_source ) && $display_image == 'enabled' && get_post_format() != 'gallery' && get_post_format() != 'video' ) { ?>

			<div class="gp-post-thumbnail gp-entry-featured">
				<?php if ( $image_source && get_post_meta( get_the_ID(), $image_source, true ) ) {
					$image_id = get_post_meta( get_the_ID(), $image_source, true );
					echo wp_get_attachment_image( $image_id, $image['name'] );
				} elseif ( has_post_thumbnail() ) {
					echo get_the_post_thumbnail( get_the_ID(), $image['name'] );
				} ?>
				<?php $attachment_id = get_post( get_post_thumbnail_id() ); if ( isset( $attachment_id->post_excerpt ) ) { ?><div class="wp-caption-text"><?php echo esc_attr( $attachment_id->post_excerpt ); ?></div><?php } ?>
			</div>

		<?php } elseif ( get_post_format() == 'video' ) { ?>

			<div class="gp-entry-featured">
				<?php get_template_part( 'lib/sections/single/entry-video' ); ?>
			</div>
			
		<?php } elseif ( get_post_format() == 'gallery' ) { ?>

			<div class="gp-entry-featured">
				<?php get_template_part( 'lib/sections/single/entry-gallery' ); ?>
			</div>

		<?php } ?>
	
		<?php if ( get_post_format() == 'audio' ) { ?>

			<div class="gp-entry-featured">
				<?php get_template_part( 'lib/sections/single/entry-audio' ); ?>
			</div>
				
		<?php } ?>
				
	<?php } ?>
														
	<div class="gp-entry-content">

		<?php if ( have_posts() ) : while ( have_posts() ) : the_post();
			the_content();
		endwhile;
		endif; ?>

		<?php wp_link_pages( array(
			'before' => '<div class="gp-entry-pagination">',
			'after'  => '</div>',
			'next_or_number' => 'ghostpool_next_and_number',
			'nextpagelink' => '',
			'previouspagelink' => '',
		) ); ?>

    </div>
    <?php
        $related_items = get_field('related_items');
        if($related_items){
    ?>
        <div id="related-items-wrapper">
            <div class="gp-divider-title-bg">
                <div class="gp-divider-title">Related pages</div>
            </div>
            <div class="related-items">
                <?php //related items
                $compiler = getHandleBars();
                $related_items = get_field('related_items');
                add_filter( 'excerpt_length', function( $length ) { return 30; } );
                foreach($related_items as $item){
                    if($item->post_type == "package"){
                        $elements = package_box_generate_data($item);
                        echo $compiler->render("package-box", $elements);
                    }
                    else if($item->post_type == "creator"){
                        $elements = creator_box_generate_data($item);
                        echo $compiler->render("creator-box", $elements);
                    }
                }
                ?>
            </div>
        </div>
    <?php
        }
    ?>
	
	<?php if ( ! function_exists( 'pmpro_has_membership_access' ) OR ( function_exists( 'pmpro_has_membership_access' ) && pmpro_has_membership_access() ) ) { ?>

		<?php if ( '1' == ghostpool_option( 'post_meta', 'tags' ) ) {
			the_tags( '<div class="gp-entry-tags">' . esc_html__( 'Tags: ', 'aardvark' ), ', ', '</div>' );
		} ?>

		<?php if ( function_exists( 'ghostpool_share_icons' ) && 'enabled' === ghostpool_option( 'post_share_icons' ) ) {
			echo ghostpool_share_icons();
		} ?>
	
		<?php if ( ghostpool_option( 'post_author_info' ) == 'enabled' ) {
			get_template_part( 'lib/sections/single/author-info' );
		} ?>

		<?php if ( function_exists( 'ghostpool_voting' ) && 'enabled' === ghostpool_option( 'post_voting' ) ) {
			echo ghostpool_voting( get_the_ID(), ghostpool_option( 'post_voting_title' ) );
		} ?>	

		<?php if ( 'enabled' === ghostpool_option( 'post_navigation' ) ) {
			echo ghostpool_post_navigation();				
		} ?>

		<?php if ( 'enabled' === ghostpool_option( 'post_related_items' ) ) {
			get_template_part( 'lib/sections/single/related-items' );
		} ?>

		<?php comments_template(); ?>
		
	<?php } ?>	

</article>