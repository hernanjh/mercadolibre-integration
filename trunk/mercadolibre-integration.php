<?php
/*
Plugin Name: MercadoLibre Integration
Plugin URI: http://wordpress.org/extend/plugins/mercadoLibre-integration/
Description: List your product catalog of MercadoLibre on your site WordPress.
Author: Hernan Javier Hegykozi
Version: 1.0
Author URI: http://www.skiba.com.ar
License: GPL2    

    Copyright 2013  Hernan Javier Hegykozi  (email : hernanjh@gmail.com)

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

add_action('init', 'MLIntplugin_init');

if (!get_option('options_mlInt')) {
	add_option('options_mlInt',array());
}				
$options_mlInt = get_option('options_mlInt');


function MLIntplugin_init() {
	
}

/* options page (required for saving prefs)*/
$options_page = get_option('siteurl') . '/wp-admin/admin.php?page=mercadolibre-integration/mercadolibre-integration_options.php';
function MLInt_options_page() {
	add_options_page('MercadoLibre Integration', 'MercadoLibre Integration', 10, 'mercadolibre-integration/mercadolibre-integration_options.php');
}
// add a settings link to the plugin in the plugin list
function MLInt_show_settings_link($links, $file) {
	$thisFile = basename(__FILE__);
	if (basename($file) == $thisFile) {
	   $settings_title = __('Settings for this Plugin', 'mercadolibre_integration');
	   $settings = __('Settings', 'mercadolibre_integration');
	   $settings_link = '<a href="options-general.php?page=mercadolibre-integration/mercadolibre-integration_options.php" title="' . $settings_title . '">' . $settings . '</a>';
	   array_unshift($links, $settings_link);
	}
	return $links;
}
//Main plugin function.
function do_mlintegration( $atts ){
global $options_mlInt;

	$options = array(
					'id' => '0',
					'user'=>'', 
					'columns'=>'0', 
					'featured'=>'',
					'imgfeatured'=>'',
					'bypage'=>'0'
				);
			
	extract( shortcode_atts( array(
		'name' => ''
		), $atts ));
		
	
	for ($i=0; $i < count($options_mlInt); $i++) {	
		if ( strtolower($options_mlInt[$i]['id']) ==  strtolower($atts['name'])){
			$options = $options_mlInt[$i];
			break;
		}
	}
	//Parameters-------
		$MLInt_user = urlencode($options['user']);
		$MLInt_articlesbypage = $options['bypage'];
		$MLInt_columns = $options['columns'];
		$MLInt_featured = explode(',', $options['featured']);
		$MLInt_featured_img = $options['imgfeatured'];
	//------------------

	$MLInt_featured_size = floor(150 / $MLInt_columns);

	$query = strtolower(stripslashes(strip_tags($MLInt_user))); if ($query == null) {$query = '';}
	$limit = $MLInt_articlesbypage; if ($limit == null) {$limit = 6;}
	

	//Want to know if we are displaying paged content.
	global $wp_query; // We are going to need $wp_query
	if( is_paged() ) {
		//It is indeed paged, want to know which page
		$page = $wp_query->query_vars['paged'];
		$page = stripslashes($page);
		$offset = ($page-1)*$limit;
	} else {
		//Not paged, set everything to null
		$offset = null; $page = null;
	}
	
	$api_call = 'https://api.mercadolibre.com/sites/MLA/search?nickname='.$query.'&limit='.$limit;
	if ( $offset ) { $api_call = $api_call.'&offset='.$offset; }
		
	$data = MLInt_api_call($api_call); 
	

	$output_result = '<table id="mlintegration" class="">';
	$articles = array();
	
	foreach ($data['results'] as $item) {
	
		$featured = false;
		$image = str_replace('s_MLA_v_I_', 's_MLA_v_O_', $item['thumbnail']);
		$title = $item['title'];
		$subtitle = $item['subtitle'];
		$permalink = $item['permalink'];
		$price = $item['price'];
		
		if (in_array(str_replace($data['site_id'],'',$item['id']), $MLInt_featured)){$featured = true;};
		
		$output_temp = "";		
		$output_temp.=  '<td class="MLInt-content" style="width:'.floor(95 / $MLInt_columns).'%;">';
		
		//Add image
		$output_temp.=  '<p class="MLInt-img">';				
		$output_temp.=  '<a rel="nofollow" href="http://pmstrk.mercadolibre.com.ar/jm/PmsTrk?tool=4272163137252252&go='.$permalink.'" title="'.$title.'" rel="nofollow" class="img-shadow" target="_blank"><img src="'.$image.'" alt="'.$title.': '.$subtitle.'"/></a>';
		
		//Add image of featured articles	
		if ($featured){ 
			$output_temp.=  '<div class="MLInt-offer" style="height:'.$MLInt_featured_size.'px;width:'.$MLInt_featured_size.'px;"><img src="'.$MLInt_featured_img.'" style="max-width:95%;max-height:95%;" /></div>';
		}		
		$output_temp.=  '</p>';
		
		//add title
		$output_temp.=  '<p class="MLInt-title"><a rel="nofollow" href="http://pmstrk.mercadolibre.com.ar/jm/PmsTrk?tool=4272163137252252&go='.$permalink.'" rel="nofollow" target="_blank">'.$title.'</a></p>';
		
		//Add subtitle
		if ($subtitle) { $output_temp.=  '<p>'.$subtitle.'</p>'; };
		
		//Add price with decimal
		$pricearr = explode('.', $price);
		$output_temp.= '<p class="MLInt-price">'.MLInt_symbol_currencie($item['currency_id']).' '.$pricearr[0].'<sup>';	
		if (sizeof($pricearr) == 2) { $output_temp.= $pricearr[1]; } else { $output_temp.= "00";};
		$output_temp.=  '</sup></p>';
		
		$output_temp.=  '</td>';
		
		//Insert first if featured
		if ($featured){ array_unshift($articles, $output_temp); } else { array_push($articles, $output_temp);};
		
	}
	
	$output = "";
	$output_temp = "";	
	$cant = 1;
	for ($i = 0, $size = count($articles); $i < $size; ++$i)
	{
		$output_temp .= $articles[$i];
		if ($cant == $MLInt_columns || $i == $size-1)
		{
			$output.= '<tr>'.$output_temp.'</tr>';
			$output_temp = "";
			$cant = 1;
		}
		else
		{
			$cant++;
		}	
	}

	$output_result.= $output;
	$output_result.= '</table>';
	
	//Build the pagination if needed
	$total_results = $data['paging']['total'];
	if ( $total_results > $limit ) { //If there are more results than our limit, then pagination is needed
		
		$pages = ceil($total_results/$limit); //Calculate how many pages we need
		
		// We are going to modify the $wp_query to make it think this is something paged, and use the default nav functions and plugins.
		$wp_query->query_vars['paged'] = $page; //Set paged
		$wp_query->max_num_pages = $pages; //Set pages
	}
	
	return $output_result;

}

function MLInt_symbol_currencie ($ids)
{
	//Gets the currency symbol
	$api_call = 'https://api.mercadolibre.com/currencies/'.$ids;
	$data = MLInt_api_call($api_call); 
	return $data['symbol'];
}

function MLInt_api_call ($url)
{
	//Make the call to API ML
	$api_call = $url;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $api_call);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$returned = curl_exec($ch);
	curl_close($ch);

	return json_decode($returned, true); 
}

//Create shortcode that returns do_mlintegration() function
add_shortcode( 'mercadolibre_integration', 'do_mlintegration' );

//Incluye el estilo
function MLInt_styles() {
	wp_enqueue_style( 'fancybox', plugin_dir_url(__FILE__) . 'mercadolibre-integration-style.css' ); }

add_action( 'wp_enqueue_scripts', 'MLInt_styles' );
add_action('admin_menu', 'MLInt_options_page');
add_filter("plugin_action_links", 'MLInt_show_settings_link', 10, 2);
?>