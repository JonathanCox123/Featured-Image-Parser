<?php
/*
Plugin Name: Featured Image Parser
Plugin URI:
Description: A plugin that parses website content for featured images, stores the links of those images into a database table for use for post types => links
Version: 1.0
Author: Jonathan Cox
Author URI: http://thedevknight.com
License: MIT
*/

/* gets the data from a URL */

error_reporting(E_ALL);
ini_set('display_errors', 1);

function test_url($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch); // $a will contain all headers

    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    return $url;
}

function get_data($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function save_relative_featured_image () {

    global $post;
    $id = $post->ID;

    $page_object = get_post( $id );
    $content = $page_object->post_content;

    @$content = preg_match_all( '/href\s*=\s*[\"\']([^\"\']+)/', $content, $links );
    $content = $links[1][0];

    if( !empty($content) && is_single($id) && get_post_format($id) == 'link' ) {
        $url = test_url(strval($content));

        $dom = new DOMDocument;
        @$dom->loadHTML(get_data($url));
        $html = $dom->saveHTML();
        $xpath = new DOMXPath($dom);
        $nlist = $xpath->query("//meta[@property='og:image']/@content");
        foreach ($nlist as $n) {
            echo $n->nodeValue;
        }

        if(url_is_image($nlist)){
           return true;
        }return false;

    }

}

function url_is_image( $url ) {
    if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return FALSE;
    }
    $ext = array( 'jpeg', 'jpg', 'gif', 'png' );
    $info = (array) pathinfo( parse_url( $url, PHP_URL_PATH ) );
    return isset( $info['extension'] )
        && in_array( strtolower( $info['extension'] ), $ext, TRUE );
}

function thumbnail_url_field( $html ) {
    global $post;
    $value = get_post_meta( $post->ID, '_thumbnail_ext_url', TRUE ) ? : "";
    $nonce = wp_create_nonce( 'thumbnail_ext_url_' . $post->ID . get_current_blog_id() );
    $html .= '<input type="hidden" name="thumbnail_ext_url_nonce" value="'
        . esc_attr( $nonce ) . '">';
    $html .= '<div><p>' . __('Or', 'txtdomain') . '</p>';
    $html .= '<p>' . __( 'Enter the url for external image', 'txtdomain' ) . '</p>';
    $html .= '<p><input type="url" name="thumbnail_ext_url" value="' . $value . '"></p>';
    if ( ! empty($value) && url_is_image( $value ) ) {
        $html .= '<p><img style="max-width:150px;height:auto;" src="'
            . esc_url($value) . '"></p>';
        $html .= '<p>' . __( 'Leave url blank to remove.', 'txtdomain' ) . '</p>';
    }
    $html .= '</div>';
    return $html;
}

function thumbnail_url_field_save( $pid, $post ) {
    $cap = $post->post_type === 'page' ? 'edit_page' : 'edit_post';
    if (
        ! current_user_can( $cap, $pid )
        || ! post_type_supports( $post->post_type, 'thumbnail' )
        || defined( 'DOING_AUTOSAVE' )
    ) {
        return;
    }
    $action = 'thumbnail_ext_url_' . $pid . get_current_blog_id();
    $nonce = filter_input( INPUT_POST, 'thumbnail_ext_url_nonce', FILTER_SANITIZE_STRING );
    $url = filter_input( INPUT_POST,  'thumbnail_ext_url', FILTER_VALIDATE_URL );
    if (
        empty( $nonce )
        || ! wp_verify_nonce( $nonce, $action )
        || ( ! empty( $url ) && ! url_is_image( $url ) )
    ) {
        return;
    }
    if ( ! empty( $url ) ) {
        update_post_meta( $pid, '_thumbnail_ext_url', esc_url($url) );
        if ( ! get_post_meta( $pid, '_thumbnail_id', TRUE ) ) {
            update_post_meta( $pid, '_thumbnail_id', 'by_url' );
        }
    } elseif ( get_post_meta( $pid, '_thumbnail_ext_url', TRUE ) ) {
        delete_post_meta( $pid, '_thumbnail_ext_url' );
        if ( get_post_meta( $pid, '_thumbnail_id', TRUE ) === 'by_url' ) {
            delete_post_meta( $pid, '_thumbnail_id' );
        }
    }
}

function thumbnail_external_replace( $html, $post_id ) {
    $url =  get_post_meta( $post_id, '_thumbnail_ext_url', TRUE );
    if ( empty( $url ) || !url_is_image( $url ) ) {
        return $html;
    }
    $alt = get_post_field( 'post_title', $post_id ) . ' ' .  __( 'thumbnail', 'txtdomain' );
    $attr = array( 'title' => $alt );
    #$attr = apply_filters( 'wp_get_attachment_image_attributes', $attr, NULL );
    $attr = array_map( 'esc_attr', $attr );
    $html = sprintf( '<div style="height:200px;background: url(%s)"', esc_url($url) );
    foreach ( $attr as $name => $value ) {
        $html .= " $name=" . '"' . $value . '"';
    }
    $html .= '></div>';
    return $html;
}

function wpdocs_filter_gallery_img_atts( $atts, $attachment ) {
    return;
}

add_filter( 'admin_post_thumbnail_html', 'thumbnail_url_field' );

add_action( 'save_post', 'thumbnail_url_field_save', 10, 2 );

add_filter( 'post_thumbnail_html', 'thumbnail_external_replace', 10, PHP_INT_MAX );

add_filter( 'wp_get_attachment_image_attributes', 'wpdocs_filter_gallery_img_atts', 10, 2 );