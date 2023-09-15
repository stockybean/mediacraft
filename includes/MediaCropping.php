<?php

class MediaCropping {
    private $instance_manager;
    private $media_config;

    public function __construct(InstanceManager $instance_manager) {
        $this->instance_manager = $instance_manager;
        $this->media_config = $this->instance_manager->MediaConfig;

        if( !function_exists( 'wp_crop_image' ) ) {
		    include( ABSPATH . 'wp-admin/includes/image.php' );
		}

        add_action('crop_thumbnails_before_crop', array($this, 'craftAllCroppedMediaSizes'), 10, 4);
    }

    public function craftAllCroppedMediaSizes($input, $cropped_size, $temporary_file, $current_file_path) {

    	$metadata = wp_get_attachment_metadata($input->sourceImageId);
    	$media_object = $this->loadMediaObject($input->sourceImageId, $metadata);
		$active_image_names = array_column($input->activeImageSizes, 'name'); // possible more than one is cropped at once

    	foreach($active_image_names as $name) {

			$size_properties = [
				'width' 	=> $cropped_size['width'],
				'height' 	=> $cropped_size['height'],
				'file' 		=> wp_basename($current_file_path),
				'crop' 		=> $input->selection,
				'status' 	=> 'cropping'
			];

			$media_object->addSize($name, $size_properties);
    	}

    	/**
    	* SEND CROP DATA OVERRIDE SINCE IT'S FROM THE HOOK
    	*/

    	$this->instance_manager->MediaGeneration->addRetinaSizesToMediaObject();

    	$this->instance_manager->MediaGeneration->generateSizes();
        $this->instance_manager->MediaGeneration->compressImages();
        $this->instance_manager->MediaGeneration->updateMediaMetadata();
    }

	public function cropImage($size, $temp_path, $dest_path, $media_object) {

	    $crop_data = $media_object->sizes[$size]['crop'];
	    if( is_array($crop_data) ) $crop_data = json_decode(json_encode($crop_data));

		if( !$this->isValidCropData($crop_data) ) throw new \ImageCropException('No MediaObject or missing crop data.');

	    $cropped = wp_crop_image(
	        $media_object->file,
	        $crop_data->x,
	        $crop_data->y,
	        $crop_data->x2 - $crop_data->x,
	        $crop_data->y2 - $crop_data->y,
	        $media_object->sizes[$size]['width'],
	        $media_object->sizes[$size]['height'],
	        false,
	        $temp_path
	    );

	    if( is_wp_error($cropped) ) throw new \ImageCropException($cropped->get_error_message());

        $copy_success = @copy($cropped, $dest_path);
        $unlink_success = @unlink($cropped);

        if( !$copy_success || !$unlink_success ) return false;

	    return $dest_path;
	}

	public function loadMediaObject($image_id, $metadata) {
		$media_generation = $this->instance_manager->MediaGeneration;
        $media_generation->media_object->set($image_id, $metadata);

        return $media_generation->media_object;
	}

	private function isValidCropData($crop_data) {

		if (
		    !isset($crop_data->x) || !isset($crop_data->y) || 
		    !isset($crop_data->x2) || !isset($crop_data->y2) ||
		    !is_numeric($crop_data->x) || !is_numeric($crop_data->y) ||
		    !is_numeric($crop_data->x2) || !is_numeric($crop_data->y2)
		) {
		    throw new \InvalidArgumentException('Invalid values in crop_data');
		}

		return true;
	}
}


// handle the retina
// $suffix = $media_generation->getSuffix($cropped_size['width'], $cropped_size['height'], true);

// $temp_file = str_replace("{$cropped_size['width']}x{$cropped_size['height']}", "{$cropped_size['width']}x{$cropped_size['height']}@2x", $temporary_file);

// $retina_file = str_replace("{$cropped_size['width']}x{$cropped_size['height']}", "{$cropped_size['width']}x{$cropped_size['height']}@2x", $current_file_path);