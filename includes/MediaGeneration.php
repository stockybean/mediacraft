<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
* Force: regenerate even if the files exist
* Bypass: ignore checks and generate
*/

/**

CORE PURPOSES
 - ON UPLOAD
 - ON CROP
 - ON USER ACTION
 	- CHECK
 	- REGENERATE
 - CRON

ON CROP
 - TEST SIZES
 - ADD ALL VIABLES SIZES TO MEDIA OBJECT
 - LOCATEORGENERATE
 - UPDATE META



ON USER ACTION

CHECK
 - LOOP ALL ATTACHMENTS
 - TEST AND ADD ALL VIABLE SIZES
 - CREATE REPORT .. MAYBE DO SOMETHING ELSE

REGENERATE
 - TEST AND ADD ALL VIABLE SIZES
 - LOCATEORGENERATE
 - UPDATE META

CRON
 - todo later

*/
class MediaGeneration {
	private $instance_manager;
    private $media_config;
    private $media_cropper;
    private $media_compressor;

    public $media_object;
    public $debug = false;

    private $is_manual_trigger = false;
    private $force = false;
    private $size_threshold = 1.5;

    public function __construct(InstanceManager $instance_manager) {
        $this->instance_manager = $instance_manager;
        $this->media_config 	= $this->instance_manager->MediaConfig;
        $this->media_cropper 	= $this->instance_manager->MediaCropping;
        $this->media_compressor	= $this->instance_manager->MediaCompression;

        $this->media_object = new MediaObject;

		add_filter('intermediate_image_sizes_advanced', array($this, 'handleWordpressGeneration'), 10, 2);
        add_filter('wp_generate_attachment_metadata', array($this, 'generate'), 10, 2);
        add_filter('delete_attachment', array($this, 'delete'));
    }

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// HOOKS /////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

    public function generate($metadata, $attachment_id) {

    	// in the event of regeneration, we need to first generate metadata
    	// we don't want to double trigger
    	if( !$this->force && $this->is_manual_trigger ) return $metadata;

    	try {
	    	$this->media_object->set($attachment_id, $metadata);
	    	$this->media_object->validate();
		} catch (\InvalidArgumentException $e) {
			$this->media_object->addMessage(
				"Trying to generate ({$attachment_id})",
				$e->getMessage(),
				'generating',
				'high'
			);
			return false;
		}

		// everything is good to go, let's loop
		$this->prepare();
		$this->generateSizes();
        $this->compressImages();
        $this->updateMediaMetadata();

		return $this->media_object->metadata->toArray();
    }

    public function analyze($metadata, $attachment_id) {

    	try {
	    	$this->media_object->set($attachment_id, $metadata);
	    	$this->media_object->validate();
		} catch (\InvalidArgumentException $e) {
			$this->media_object->addMessage("Trying to analyze ({$attachment_id})", $e->getMessage(), 'generating', 'low');
			return false;
		}

		// everything is good to go, let's loop
		$this->prepare();

		return $this->compareSizesToMetaData();
    }

    public function delete($attachment_id) {

    	$metadata = wp_get_attachment_metadata($attachment_id);

    	try {
	    	$this->media_object->set($attachment_id, $metadata);
		} catch (\InvalidArgumentException $e) {
			$this->media_object->addMessage("Trying to delete {$attachment_id})", $e->getMessage(), 'deleting', 'high');
			return false;
		}

		// @todo
		// write a method to verify sizes
		// such as add all possible sizes

		foreach($this->media_object->sizes as $name => $size_settings) {
			$file_path = getSizeFilePath($name, $size_settings);
			if( file_exists($file_path) ) {
		  		unlink($file_path);
			}			
		}
    }

    public function handleWordpressGeneration($sizes, $metadata) {

		$this->media_object->mock($metadata);

    	foreach($sizes as $name => $size) {

    		$size_settings = MediaConfig::getSettings($name);
	        if( $size_settings && $this->shouldSkipSize($size_settings) ) unset($sizes[$name]);
    	}

    	return $sizes;
    }

    public function testSizes($type, $media_object = null) {
    	$this->bypass = true;

    	if( $media_object ) $this->media_object = $media_object;

		if($type === 'check')	 	$this->check();
		if($type === 'generate') 	$this->generateSizes();
		if($type === 'regenerate') 	$this->regenerateSizes();
		if($type === 'update')	 	$this->loadUpMetaData();
		if($type === 'complete')	$this->doItAll();
    }

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// OPERATIONS ////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////


    public function check() {

    	// if( empty($this->media_object->sizes) ) throw new \Exception('No sizes to check');

    	// compare expected vs meta
    	// then compare vs actual files found on server
    	// then check if compressed
    }

    public function generateSizes() {
    	foreach($this->media_object->sizes as $size_name => $size_data) {

    		$expected_path = MediaHelper::fullPathFromFile($size_data['file'], $this->media_object);

    		if( !MediaHelper::checkFileExistence($expected_path) ) {
	            $this->generateSize($size_name, $size_data);
    		}

        	$size_data = $this->media_object->validateSize($size_data, $this->media_object);

            $this->media_object->sizes[$size_name] = $size_data;
    	}
    }

    public function regenerateSizes() {
    	foreach($this->media_object->sizes as $size_name => $size_data) {

            $image_info = $this->generateSize($size_name, $size_data);

            if( $image_info ) {

            	$this->media_object->addSizeProperty($size_name, [
            		'file' => $image_info['file'],
            		'filesize' => $image_info['filesize'],
            		'status' => 'regenerated'
        		]);

            } else {

            	$this->media_object->addSizeProperty($size_name, [
            		'status' => 'failed'
        		]);

        		throw new \ImageGenerationException('Could not generate, check messages.');
            }
    	}
    }

    public function compressImages() {
    	foreach($this->media_object->sizes as $size_name => $size_data) {

    		$file_path = $this->getSizeFilePath($size_name, $size_data);

    		try {
	            $compressed_file = $this->media_compressor->compressSize($file_path);
	            $image_info = $this->getImageInfo($compressed_file);
	            if( $image_info ) {
	            	$this->media_object->addSizeProperty($size_name, [
	            		'compressed_filesize' => $image_info['filesize'],
	            		'status' => 'compressed'
	        		]);
	            }
    		} catch (ImageCompressionException $e) {
    			$this->media_object->addMessage("Trying to compress $size_name", $e->getMessage(), 'compressing', 'medium');
    			return false;
    		}
    	}
    }

    public function doItAll() {
    	$file = get_attached_file($this->media_object->ID);
    	$metadata = $this->manuallyTriggeredProcessing($this->media_object->ID, $file);

    	$this->generateSizes();
    	$this->loadUpMetaData();
    	$this->updateMediaMetadata();
    }


	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// CORE FUNCTIONS ////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

    public function generateSize($name, $size_data) {

		$file_path = MediaHelper::fullPathFromFile($size_data['file'], $this->media_object);
		$temp_path = $this->getTempPathFromFile($size_data['file']);

	    try {

	    	if( $size_data['crop'] ) {

	    		try {

	    			$dest_file = $this->media_cropper->cropImage(
	    				$name,
	    				$temp_path, $file_path,
	    				$this->media_object
	    			);

	    			return true;
	    			
	    		} catch (\ImageCropException $e) {

	    			$this->media_object->addMessage("Trying to crop {$name}", $e->getMessage(), 'cropping', 'high');
	    			return false;

	    		} catch (\Exception $e) {

	    			$this->media_object->addMessage("Trying to crop {$name}", $e->getMessage(), 'cropping', 'high');
	    			return false;

	    		}
	    	}

	        $this->resizeImage(
	        	$this->media_object->file,
	        	$size_data['width'], $size_data['height'],
	        	$temp_path, $file_path
	        );

	        return true;

	    } catch (Exception $e) {
	    	$this->media_object->addMessage("Trying to resize $name", $e->getMessage(), 'generating', 'high');
	        return false;
	    }
    }

    public function resizeImage($file, $desired_width, $desired_height, $temp_file, $dest_file) {

        // $this->haltImageSizeLimits(); // shouldn't need this as all ill-fitting images are sent to crop

		$resized_file = wp_get_image_editor($file);

		if( is_wp_error($resized_file) ) throw new \Exception($resized_file->get_error_message());

	  	$resized_file->resize($desired_width, $desired_height, ['center', 'center']);

	  	$resized_file->save($dest_file);

	  	if( is_wp_error($resized_file) ) throw new \Exception($resized_file->get_error_message());

	    return MediaHelper::getImageInfo($dest_file);

	    // $this->enableImageSizeLimits();

	    throw new \Exception("Error getting image dimensions after resizing.");
    }

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// HELPERS ///////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

    private function getTempPathFromFile($file) {
    	$dir = pathinfo($this->media_object->file, PATHINFO_DIRNAME);
	    $ext = pathinfo($this->media_object->file, PATHINFO_EXTENSION);

	    $filename = wp_basename($file, ".$ext");

	    return trailingslashit(MEDIACRAFT_UPLOADS_DIR) . "temp_{$filename}.{$ext}";
    }

	private function haltImageSizeLimits() {
	    add_filter('image_resize_dimensions', array($this, 'resizeDimensionsFilter'), 10, 6);
	}

	private function enableImageSizeLimits() {
	    remove_filter('image_resize_dimensions', array($this, 'resizeDimensionsFilter'), 10);
	}

	public function resizeDimensionsFilter($output, $orig_w, $orig_h, $dest_w, $dest_h, $crop) {
        return array( 0, 0, 0, 0, $dest_w, $dest_h, $orig_w, $orig_h );
	}

    private function manuallyTriggeredProcessing($attachment_id, $file) {
        $this->is_manual_trigger = true;
        
        $metadata = wp_generate_attachment_metadata($attachment_id, $file);

        $this->is_manual_trigger = false;

        return $metadata;
    }

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// CONDITIONALS //////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

    private function shouldSkipSize($size_settings) {
	    $widthRequirementMet = $this->media_object->width >= $size_settings['width'];
	    $heightRequirementMet = $this->media_object->height >= $size_settings['height'];
	    
	    return !$widthRequirementMet || !$heightRequirementMet || MediaHelper::shouldGenerate($size_settings, $this->media_object);
	}


	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// AJAX METHODS //////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

    public function regenerate($attachment_id, $force = false) {
    	$this->force = $force;
    	$file = get_attached_file($attachment_id);
    	$metadata = $this->manuallyTriggeredProcessing($attachment_id, $file);
    	$this->generate($metadata, $attachment_id);
    	$this->force = false;

    	return $this->media_object->toArray();
    }

	public function analyzeMediaSizes($attachment_id) {
		$metadata = wp_get_attachment_metadata($attachment_id);

    	try {
	    	$this->media_object->set($attachment_id, $metadata);
	    	$this->media_object->validate();
		} catch (\InvalidArgumentException $e) {
			$this->media_object->addMessage("Trying to analyze ({$attachment_id})", $e->getMessage(), 'generating', 'low');
			return false;
		}

		$this->testSizes('prepare');
		// $this->testSizes('update');

		// return $this->media_object->toArray();
		return $this->compareSizesToMetaData();


		$analysis = $this->analyze($metadata, $attachment_id);

		return $analysis;
	}

	private function compareSizesToMetaData() {

		$all_possible_sizes = array_keys($this->media_config->getSettings());
		$expected_sizes = array_keys($this->media_object->sizes);
		$found_sizes = array_keys($this->media_object->metadata->mediacraft);

		$current_metadata = $this->media_object->metadata->mediacraft;
		$expected_metadata = $this->media_object->sizes;

		$excluded_properties = ['filesize', 'status'];
		$additional_properties = ['compressed_file'];

		$differences = [];

		foreach( $expected_metadata as $name => $size_data ) {
		    if( !isset($current_metadata[$name]) ) {
		        $differences[$name] = 'missing';
		        continue;
		    }

		    $current_size_data = $current_metadata[$name];

		    $diff = [];
		    foreach( $size_data as $property => $value) {

		    	if( in_array($property, $excluded_properties) ) continue;

		        if( !isset($current_size_data[$property]) ) {
		            $diff[$property] = 'missing';
		        }

		        $current_size_value = $current_size_data[$property];

		        if( $value !== $current_size_value ) {
		            $diff[$property] = [
		                'existing' => $current_size_value,
		                'expected' => $value
		            ];
		        }
		    }

            // Compare additional properties
	        foreach( $additional_properties as $property ) {
	            if( !isset($current_size_data[$property]) ) {
	                $diff[$property] = 'missing';
	            }
	        }

		    if( !empty($diff) ) {
		        $differences[$name] = $diff;
		    }
		}

		$analysis = [
			'optional_sizes' => array_diff($all_possible_sizes, $expected_sizes),
			'extra_sizes_found' => array_diff($expected_sizes, $found_sizes),
			'expected_sizes' => $differences
		];

		return $analysis;
	}
}
