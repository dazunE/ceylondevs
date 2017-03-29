<?php 


//add_filter('acf/validate_value/name=april_target', 'target_data_validations', 10, 4);

function target_data_validations( $valid, $value, $field, $input ){

	global $post;
	
	// bail early if value is already invalid
	if( !$valid ) {
		
		return $valid;
		
	}

	$month = (int)date('m');

	$year  = date('Y');

	$acf = get_field_objects($post->ID);

	$user = wp_get_current_user();

    $finnacial_year = (int)$acf['finnacial_year']['value'];

    array_shift($acf);

 }




add_filter('acf/validate_value/name=validate_this_image', 'mya_acf_validate_value', 10, 4);

function mya_acf_validate_value( $valid, $value, $field, $input ){
	
	// bail early if value is already invalid
	if( !$valid ) {
		
		return $valid;
		
	}
	
	
	// load image data
	$data = wp_get_attachment_image_src( $value, 'full' );
	$width = $data[1];
	$height = $data[2];
	
	if( $width < 960 ) {
		
		$valid = 'Image must be at least 960px wide';
		
	}
	
	
	// return
	return $valid;
	
	
}