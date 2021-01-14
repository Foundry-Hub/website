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