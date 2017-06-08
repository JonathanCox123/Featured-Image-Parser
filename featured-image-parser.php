<?php
/*
Plugin Name: Featured Image Parser
Plugin URI:
Description: a plugin that parses website content for featured images, stores the links of those images into a database table for use for post types => links
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

function get_post_id () {

    global $post;
    $id = $post->ID;

    $page_object = get_post( $id );
    $content = $page_object->post_content;

    @$content = preg_match_all( '/href\s*=\s*[\"\']([^\"\']+)/', $content, $links );
    $content = $links[1][0];

    if( !empty($content) && is_single($id) && get_post_format($id) == 'link' ) {
        $url = test_url( strval($content) );

        $dom = new DOMDocument;
        @$dom->loadHTML(get_data($url));
        $html = $dom->saveHTML();
        $xpath = new DOMXPath($dom);
        $nlist = $xpath->query("//meta/@content");
        foreach($nlist as $n){
            echo $n->nodeValue;

        }
    }

}

add_action("wp_head", "get_post_id");
