<?php
function APF_google_map_api( $api ){
	$api['key'] = 'YOUR_API_KEY_HERE';
	return $api;
}
add_filter('acf/fields/google_map/api', 'APF_google_map_api');