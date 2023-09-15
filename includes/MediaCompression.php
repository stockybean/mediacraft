<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

class MediaCompression {

	private $tiny_settings;

	public function __construct() {
		if( !class_exists('Tiny_Settings') || !class_exists('Tiny_Image') ) return false;

		$this->tiny_settings = new Tiny_Settings();
		$this->tiny_settings->admin_init();
	}

    public static function compressImageByID($attachment_id, $metadata) {
        $tiny_image = new Tiny_Image($this->tiny_settings, $attachment_id, $metadata);
        $result = $tiny_image->compress();
        wp_update_attachment_metadata($attachment_id, $tiny_image->get_wp_metadata());

        return $result;
    }

    public function compressSize($image_path) {
    	if( !$this->tiny_settings ) throw new \ImageCompressionException('No compressor loaded');

        $compressor = $this->tiny_settings->get_compressor();

        try {
            return $compressor->compress_file($image_path);
        } catch (Tiny_Exception $e) {
            return false;
        }
    }
}