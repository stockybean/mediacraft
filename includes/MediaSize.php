<?php

trait Size {

    private $default_properties = [
        "width" 				=> "",
        "height" 				=> "",
        "file" 					=> "",
        "filesize" 				=> "",
        "crop" 					=> "",
        "compressed_size" 		=> "",
        "display_conditions" 	=> [],
        "status" 				=> ""
    ];

    public function createSize($properties) {
    	if( !$properties['width'] )		throw new \InvalidArgumentException('Size must have a width');
    	if( !$properties['height'] )	throw new \InvalidArgumentException('Size must have a height');
    	if( !$properties['file'] )		throw new \InvalidArgumentException('Size must have a file');

        return array_merge($this->default_properties, $properties);
    }

    public function validateSize($size, $media_object) {

    	$path 		= MediaHelper::fullPathFromFile($size['file'], $media_object);
    	if( !MediaHelper::checkFileExistence($path) ) return $this->updateStatus($size, 'Missing');

    	$image_info = MediaHelper::getImageInfo($path);

    	$invalid_reason = '';

    	if( $size['width'] !== $image_info['width'] ) 	$invalid_reason = 'Bad width';
    	if( $size['height'] !== $image_info['height'] ) $invalid_reason = 'Bad height';
    	if( $image_info['filesize'] <= 0 ) 				$invalid_reason = 'Filesize is null';

    	if( $invalid_reason ) {
    		$size = $this->updateStatus($size, 'Failed');
    		return $this->addFailedReason($size, $invalid_reason);
    	}

    	return $this->updateStatus($size, 'Available');
    }

    public function updateStatus($size, $status) {

    	if( $status === '' ) return $size;
    	$size['status'] = $status;

    	return $size;
    }

    public function addFailedReason($size, $reason) {

    	if( $reason === '' ) return $size;
    	$size['failed_reason'] = $reason;

    	return $size;
    }

	/**
	* addons need to inherit crop data from key_size
	*/
    public function updateCrop($size, $crop_data) {
    	if( $crop_data['x'] 	=== null || empty($crop_data['x']) ) 	return;
    	if( $crop_data['x2'] 	=== null || empty($crop_data['x2']) ) 	return;
    	if( $crop_data['y'] 	=== null || empty($crop_data['y']) ) 	return;
    	if( $crop_data['y2'] 	=== null || empty($crop_data['y2']) ) 	return;

    	if( empty($size['crop']) ) {
    		if( !empty($size['crop']['x']	)) 	return;
    		if( !empty($size['crop']['x2']	))	return;
    		if( !empty($size['crop']['y']	)) 	return;
    		if( !empty($size['crop']['y2']	)) 	return;
    	}

    	$size['crop'] = $crop_data; // no merging, need to completely replace

    	return $size;
    }

    public function updateCompression($filesize) {
    	$size['compressed_size'] = $filesize;

    	return $size;
    }

    public function safelyMergeSize($existing, $new) {
        $merged = $existing;

        foreach( $new as $key => $value ) {

            // don't overwrite crop data
            if( $key === 'crop' && isset($merged[$key]) && $merged[$key] ) continue;

            if( !empty($value) ) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}

// on generate, update status and verify the height/width
// on compress, update compressed_size, status and verify height/width
// on crop, update crop, status and verify height/width
// on verify, update status and verify height/width

// on init of MediaObject
// the metadata will be looped and verified and added
// then the sizes will be blueprinted
// then the blueprint sizes will be safelyMerged (which means metadata takes priority)
// the verification for metadata and blueprints are the same, therefore no need to merge any of the actual blueprint props into a metadata-loaded size