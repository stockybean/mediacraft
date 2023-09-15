<?php

class MediaHelper {

    public static function checkFileExistence($file) {
    	if(file_exists($file)) {
	        return wp_basename($file);
	    }
	    return null;
    }

    public static function getImageInfo($path) {

    	if( $info = getimagesize($path) ) {
	        return [
	            'file' => wp_basename($path),
	            'path' => $path,
	            'width' => $info[0],
	            'height' => $info[1],
	            'filesize' => filesize($path)
	        ];
	    }
	    return false;
    }

    public static function shouldGenerate($settings, $media_object) {

	    $skip_on_upload		= $settings['skipOnUpload'] ?? false;
	    $fly_dynamic_image	= $settings['flyDynamicImage'] ?? false;

	    if( !$media_object->bypass_restraints && ($skip_on_upload || $fly_dynamic_image) ) {
	    	if( $skip_on_upload ) Messenger::setMessage('Skip on upload');
	    	if( $fly_dynamic_image ) Messenger::setMessage('Skip on upload');

    		return false;
	    }

    	// don't care if brute force, doesn't make sense to generate 8x larger images
    	if( !self::isWithinSizeThreshold($settings, $media_object) ) {
    		Messenger::setMessage('Failed threshold check');
    		return false;
    	}

	    return true;
	}

	private static function isWithinSizeThreshold($size_settings, $media_object) {
		$threshold = $size_settings['threshold'] ?? MediaConfig::globalProperty('threshold');

		// Calculate threshold values for width and height
		$threshold_width = $media_object->width * $threshold;
		$threshold_height = $media_object->height * $threshold;

		// Skip if size bounds are larger than the threshold
		if( $size_settings['width'] > $threshold_width || $size_settings['height'] > $threshold_height )
		    return false;

		return true;
    }

    public static function fullPathFromFile($file, $media_object) {
    	$dir = pathinfo($media_object->file, PATHINFO_DIRNAME);

	    return trailingslashit($dir) . "{$file}";
    }

    public static function getSuffix($width, $height, $retinaDpi = false) {
	    $retinaSuffix = $retinaDpi ? "@{$retinaDpi}x" : '';
	    return "{$width}x{$height}{$retinaSuffix}";
	}

	public static function fullPathFromName($name, $size_settings, $media_object) {
    	$retinaDpi = self::isSizeRetina($name);
    	$suffix = self::getSuffix($size_settings['width'], $size_settings['height'], $retinaDpi);

	    $dir = pathinfo($media_object->file, PATHINFO_DIRNAME);
	    $ext = pathinfo($media_object->file, PATHINFO_EXTENSION);

	    $filename = wp_basename($media_object->file, ".$ext");

	    return trailingslashit($dir) . "{$filename}-{$suffix}.{$ext}";
    }

    public static function fileToUrl($file, $media_object) {
		$baseurl = wp_get_upload_dir()['baseurl'] .DIRECTORY_SEPARATOR. str_replace(wp_get_upload_dir()['basedir'], '', str_replace(basename($media_object->file), '', $media_object->file));

    	return $baseurl . $file;
    }

    public static function urlToDBAttachedFile($url) {
	    // Get the uploads directory
	    $uploads_dir = wp_upload_dir();

	    // Remove the site URL to get the path relative to the uploads directory
	    $relative_path = str_replace($uploads_dir['baseurl'], '', $url);

	    // Remove query parameters and normalize the path
	    $relative_path = strtok($relative_path, '?');

	    // Ensure the path is relative
	    $relative_path = ltrim($relative_path, '/');

	    // Combine with the uploads directory
	    $attached_file = $relative_path;

	    return $attached_file;
	}

    public static function sizeRequiresCropping($size_settings, $media_object) {
    	if( $media_object->width >= $size_settings['width']
    		&& $media_object->height >= $size_settings['height'] ) {
    		return false;
	    }

	    $aspect_ratio 	= $media_object->width / $media_object->height;
	    $size_ratio		= $size_settings['width'] / $size_settings['height'];

	    if( $aspect_ratio === $size_ratio ) return false;

	    // this means width will need cropping
	    if( $aspect_ratio > $size_ratio ) {

	        $diff_x = $media_object->width - ($media_object->height * $size_ratio);
	        return [
		        'x'		=> $diff_x / 2,
		        'x2' 	=> $media_object->width - ($diff_x / 2),
		        'y' 	=> 0,
		        'y2'	=> $media_object->height
	        ];
	    }

	    $diff_y = $media_object->height - ($media_object->width / $size_ratio);
        return [
	        'x' 	=> 0,
	        'x2' 	=> $media_object->width,
	        'y' 	=> $diff_y / 2,
	        'y2'	=> $media_object->height - ($diff_y / 2)
        ];
    }

    public static function isSizeRetina($size) {
		// Define the combined pattern to remove both cases
	    $pattern = '/(?:retina_(\d+)x)/';

	    if (preg_match($pattern, $size, $matches)) {
	        return isset($matches[1]) ? $matches[1] : false;
	    }
	}
}