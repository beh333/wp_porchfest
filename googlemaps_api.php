<?php
function APF_google_map_api( $api ){
	$api['key'] = '';
	return $api;
}
add_filter('acf/fields/google_map/api', 'APF_google_map_api');