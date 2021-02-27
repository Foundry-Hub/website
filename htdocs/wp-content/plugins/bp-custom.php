<?php

// Set BP to use wp_mail
add_filter( 'bp_email_use_wp_mail', '__return_true' );
 
// Set messages to HTML
remove_filter( 'wp_mail_content_type', 'set_html_content_type' );
add_filter( 'wp_mail_content_type', 'set_html_content_type' );
function set_html_content_type() {
    return 'text/html';
}
 
// Use HTML template
add_filter( 'bp_email_get_content_plaintext', 'get_bp_email_content_plaintext', 10, 4 );
function get_bp_email_content_plaintext( $content = '', $property = 'content_plaintext', $transform = 'replace-tokens', $bp_email ) {
    if ( ! did_action( 'bp_send_email' ) ) {
        return $content;
    }
    return $bp_email->get_template( 'add-content' );
}

/*
 * BP Auto Login on Activation
 * Plugin URI: http://buddydev.com/plugins/bp-autologin-on-activation/
 * Author: Brajesh Singh(BuddyDev.com)
 * Author URI: http://buddydev.com/members/sbrajesh
 * Version: 1.0.3
 * Description: This plugin automatically logs in the user and redirects them to their profile when they activate their account
 * License: GPL
 */
function bp_autologin_on_activation( $user_id, $key, $user ) {

	if ( defined( 'DOING_AJAX' ) || is_admin() ) {
		return;
	}

	$bp = buddypress();


	//simulate Bp activation
	/* Check for an uploaded avatar and move that to the correct user folder, just do what bp does */
	if ( is_multisite() ) {
		$hashed_key = wp_hash( $key );
	} else {
		$hashed_key = wp_hash( $user_id );
	}
	/* Check if the avatar folder exists. If it does, move rename it, move it and delete the signup avatar dir */
	if ( file_exists( BP_AVATAR_UPLOAD_PATH . '/avatars/signups/' . $hashed_key ) ) {
		@rename( BP_AVATAR_UPLOAD_PATH . '/avatars/signups/' . $hashed_key, BP_AVATAR_UPLOAD_PATH . '/avatars/' . $user_id );
	}

	bp_core_add_message( __( 'Your account is now active!', 'buddypress' ) );

	$bp->activation_complete = true;
    //now login and redirect
    
    if(headers_sent())
        throw new \ErrorException('Headers already sent');

	wp_set_auth_cookie( $user_id, true );

	bp_core_redirect( apply_filters( 'bpdev_autoactivate_redirect_url', get_home_url(), $user_id ) );
}

add_action( 'bp_core_activated_user', 'bp_autologin_on_activation', 40, 3 );

define('BP_DEFAULT_COMPONENT', 'profile' );

