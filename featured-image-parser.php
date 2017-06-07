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

    if( is_single($id) && get_post_format($id) == 'link' ) {
        $url = 'http://thedevknight.com/';
        $html = get_data($url);

        preg_match_all( '|<img.*?src=[\'"](.*?)[\'"].*?>|i',$html, $matches );

        echo "heres the count: ".count($matches);

        for($i = 0;$i<count($matches[1]);$i++){
            $match = $matches[ 1 ][ $i ];
            echo "<img src=\"$url$match\"></img>";
        }
    }

}

add_action("wp_head", "get_post_id");
