<?php

/*
   * Plugin Name: WP Naver Map in Post
   * Description: Add a naver map to your post
   * Version: 1.2
   * Author: Alghost
   * Author URI: http://blog.alghost.co.kr
   */

/*  Copyright 2015  WP Naver Map in Post  (email : alghost.lee@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

add_action('admin_init', 'alghost_action_mce_button');
add_shortcode('navermap', 'alghost_add_naver_map');
add_action('wp_ajax_alghost_get_locations_from_keyword', 'alghost_get_locations_from_keyword');
add_action('wp_ajax_nopriv_alghost_get_locations_from_keyword', 'alghost_get_locations_from_keyword');
add_action('admin_menu', 'alghost_navermap_admin_setup');
function alghost_file_get_contents_curl($url) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);       

    $client_id= esc_attr(get_option('alghost-navermap-client-id'));
    $client_id= trim($client_id);
    $client_secret= esc_attr(get_option('alghost-navermap-client-secret'));
    $client_secret= trim($client_secret);

    $headers = array();
    $headers[] = "X-Naver-Client-Id: ".$client_id;
    $headers[] = "X-Naver-Client-Secret: ".$client_secret;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
}
function alghost_get_locations_from_keyword(){
    $keyword = $_POST['keyword'];
    if (!$keyword)
        $keyword = '';
    $keyword = sanitize_text_field($keyword);
    update_post_meta(0, 'keyword', $keyword);

    $url = "https://openapi.naver.com/v1/search/local.xml?query=".urlencode($keyword)."&start=1&display=10&sort=random";

    $response = alghost_file_get_contents_curl($url);
    $object = simplexml_load_string($response);
    $channel = $object->channel;
    $key=0;
    foreach($channel->item as $value){
        echo '<li><a href="#" id="maplink_'.$key.'">'.($key+1).'. '.$value->title.': '.$value->roadAddress.'('.$value->address.')</a><input type="hidden" id="map_'.$key.'" value="'.$value->mapx.'|'.$value->mapy.'|'.$value->title.'" /></li>';
        $key++;
    }

    die();
}

function alghost_action_mce_button() {
    // Check if user have permission
    if ( current_user_can( 'edit_posts' ) && current_user_can( 'edit_pages' ) ) {
        add_filter( 'mce_buttons', 'alghost_register_mce_button' );
        add_filter( 'mce_external_plugins', 'alghost_navermap_plugin' );
    }
}

// Function for new button
function alghost_navermap_plugin( $plugin_array ) {
    $plugin_array['alghost_navermap'] = plugin_dir_url(__FILE__).'/navermap.js';
    return $plugin_array;
}

// Register new button in the editor
function alghost_register_mce_button( $buttons ) {
    array_push( $buttons, 'navermap_button');
    return $buttons;
}

function alghost_add_naver_map($atts){
    $location = shortcode_atts(array(
                "mapx" => "0",
                "mapy" => "0",
                "title" => "None"
            ), $atts);

    $client_id= esc_attr(get_option('alghost-navermap-client-id'));
    $client_id= trim($client_id);
    $all_script = "<script type=\"text/javascript\" src=\"http://openapi.map.naver.com/openapi/v3/maps.js?clientId=".$client_id."\"></script>";
    $all_script .= "<script type=\"text/javascript\" src=\"".plugins_url()."/wp-naver-map-in-post/conv_loc/lib/geocent.js\"></script>";
    $all_script .= "<script type=\"text/javascript\" src=\"".plugins_url()."/wp-naver-map-in-post/conv_loc/lib/tmerc.js\"></script>";
    $all_script .= "<script type=\"text/javascript\" src=\"".plugins_url()."/wp-naver-map-in-post/conv_loc/lib/cscs.js\"></script>";
    $all_script .= "<SCRIPT type=\"text/javascript\" src=\"".plugins_url()."/wp-naver-map-in-post/conv_loc/lib/defs/GOOGLE_WGS84.js\"></SCRIPT>";
    $all_script .= "<SCRIPT type=\"text/javascript\" src=\"".plugins_url()."/wp-naver-map-in-post/conv_loc/lib/defs/TM128_katech_3param.js\"></SCRIPT>";
    $all_script .= "<p style=\"margin-left:15px; font-size:0.9em\"><b>".strip_tags($location['title'])."지도</b></p>";
    $all_script .= "<p id=\"alghostMap\" style=\"border:1px solid #000; max-width:400px; height:300px; margin:15px;\"></p>";
    $all_script .= "<p style=\"margin-left:15px; font-size:0.75em;\"><a id=\"alghostMobileLink\">네이버지도앱으로 연결</a></p>";
    $all_script .= "<script type=\"text/javascript\">";
    $all_script .= "var TM128 = new CS(csList.TM128_katech_3param);";
    $all_script .= "var WGS84 = new CS(csList.GOOGLE_WGS84);";
    $all_script .= "var convPtr = new PT(".$location['mapx'].",".$location['mapy'].");";
    $all_script .= "cs_transform(TM128, WGS84, convPtr);";
    $all_script .= "var HOME_PATH = window.HOME_PATH || '.';";
    $all_script .= "var objCenterPtr = new naver.maps.LatLng(convPtr.y, convPtr.x);";
    $all_script .= "var objMap = new naver.maps.Map(document.getElementById(\"alghostMap\"), {";
    $all_script .= "       center: objCenterPtr,";
    $all_script .= "       size: new naver.maps.Size(300, 300)";
    $all_script .= "   });";
    $all_script .= "var marker = new naver.maps.Marker({";
    $all_script .= "       position: objCenterPtr,";
    $all_script .= "       map: objMap,";
    $all_script .= "       icon: {url: HOME_PATH + '/icon/pin_spot2.png',";
    $all_script .= "           size: new naver.maps.Size(22,35),";
    $all_script .= "           origin: new naver.maps.Point(0,0),";
    $all_script .= "           anchor: new naver.maps.Point(11,35)}";
    $all_script .= "   });";
    $all_script .= "var contentString = [";
    $all_script .= "  '<div class=\"iw_inner\">',";
    $all_script .= "  '<b align=\"center\">".strip_tags($location['title'])."</b>',";
    $all_script .= "  '</div>'].join('');";
    $all_script .= "var infowindow = new naver.maps.InfoWindow({content: contentString, maxWidth: 200});";
    $all_script .= "naver.maps.Event.addListener(marker, \"click\", function(e) {";
    $all_script .= "   if (infowindow.getMap()){ infowindow.close(); }";
    $all_script .= "   else { infowindow.open(objMap, marker); }";
    $all_script .= "});";
    $all_script .= "infowindow.open(objMap, marker);";
    $all_script .= "   var mobile_url = \"navermaps://?menu=location&lat=\"+convPtr.y+\"&lng=\"+convPtr.x+\"&title=".strip_tags($location['title'])."&mLevel=11\";";
    $all_script .= "   document.getElementById(\"alghostMobileLink\").href = mobile_url;";
    $all_script .= "   </script>";

    return strval($all_script);
}

function alghost_navermap_admin_setup(){
    add_menu_page('네이버지도 설정 페이지', 'Naver map', 'manage_options', 'alghost-navermap-admin', 'alghost_navermap_admin_init');
    
    add_action('admin_init', 'alghost_navermap_admin_form_setup');
}
function alghost_navermap_admin_form_setup(){
    register_setting('alghost-navermap-keys', 'alghost-navermap-client-id');
    register_setting('alghost-navermap-keys', 'alghost-navermap-client-secret');
}

function alghost_navermap_admin_init(){
    echo '<div class="wrap">';
    echo '<h2>네이버지도 설정</h2>';
    echo '<p>네이버지도 플러그인 설정화면입니다. 검색 API와 지도 API를 설정하신 후 사용하시길 바랍니다.</p>';
    echo '<form method="post" action="options.php">';
    settings_fields('alghost-navermap-keys');
    do_settings_sections('alghost-navermap-keys');
    echo '<table class="form-table">';
    echo '  <tr valign="top">';
    echo '      <th scope="row">Client ID</th>';
    echo '      <td><input type="text" name="alghost-navermap-client-id" value="'.esc_attr(get_option('alghost-navermap-client-id')).'" style="max-width:70%;"/></td>';
    echo '  </tr>';
    echo '  <tr valign="top">';
    echo '      <th scope="row">Client Secret</th>';
    echo '      <td><input type="text" name="alghost-navermap-client-secret" value="'.esc_attr(get_option('alghost-navermap-client-secret')).'" style="max-width:70%;" /></td>';
    echo '  </tr>';
    echo '</table>';
    submit_button();
    echo '</form>';
    echo '</div>';
}
?>
