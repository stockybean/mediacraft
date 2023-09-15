<?php
/**
 * Plugin Name: MediaCraft Pro
 * Description: An all-inclusive media management and optimization plugin for WordPress.
 * Version: 1.0
 * Author: Joel Duke
 */

if (!defined('MEDIACRAFT_PLUGIN_DIR')) {
    define('MEDIACRAFT_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('MEDIACRAFT_PLUGIN_URL')) {
    define('MEDIACRAFT_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if( !defined('MEDIACRAFT_UPLOADS_DIR')) {
	$mediacraft_uploads = trailingslashit(wp_upload_dir()['basedir']) . 'mediacraft';
	if( !is_dir($mediacraft_uploads) ) {
	    mkdir($mediacraft_uploads, 0700);
	}
	define('MEDIACRAFT_UPLOADS_DIR', $mediacraft_uploads);
}

// some helpful exceptions
class ImageGenerationException	extends \Exception {}
class ImageCompressionException extends \Exception {}
class ImageCropException 		extends \Exception {}
class ImageRenderException		extends \Exception {}
class ImageMetadataException	extends \Exception {}


// Load Size trait
require_once(plugin_dir_path(__FILE__) . 'includes/MediaSize.php');

// Load MediaObject class
require_once(plugin_dir_path(__FILE__) . 'includes/MediaObject.php');

// Load Config static class
require_once(plugin_dir_path(__FILE__) . 'includes/MediaConfig.php');

// Load Helper static class
require_once(plugin_dir_path(__FILE__) . 'includes/MediaHelper.php');

// Load MediaMetadata class
require_once(plugin_dir_path(__FILE__) . 'includes/MediaMetadata.php');

// Load MediaGeneration class
require_once(plugin_dir_path(__FILE__) . 'includes/MediaGeneration.php');

// Load MediaCropping class
require_once(plugin_dir_path(__FILE__) . 'includes/MediaCropping.php');

// Load MediaCompression class
require_once(plugin_dir_path(__FILE__) . 'includes/MediaCompression.php');

// Load ResponsivePicture class
require_once(plugin_dir_path(__FILE__) . 'includes/MediaRender.php');

// Load MediaAdmin class
require_once(plugin_dir_path(__FILE__) . 'includes/MediaAdmin.php');

// Load MediaAttribution class
// require_once(plugin_dir_path(__FILE__) . 'includes/MediaAttribution.php');

// AJAX Handling
require_once(plugin_dir_path(__FILE__) . 'includes/MediaAjax.php');

// aync job controller
require_once(plugin_dir_path(__FILE__) . 'includes/JobController.php');

// Message Handling
require_once(plugin_dir_path(__FILE__) . 'includes/Messages.php');

// Rest
require_once(plugin_dir_path(__FILE__) . 'includes/MediaRest.php');

class Messenger {
	protected static $message;

	public static function setMessage($message) {
		self::$message = $message;
	}

	public static function getMessage() {
		$message = self::$message;
		self::clearMessage();

		return $message;
	}

	public static function clearMessage() {
		self::$message = null;
	}
}

class InstanceManager {
    private $instances = [];

    public function __get($class) {
        if( !isset($this->instances[$class]) ) {
            $this->instances[$class] = new $class($this);
        }

        return $this->instances[$class];
    }
}

// Create the InstanceManager
$instance_manager = new InstanceManager();
MediaConfig::initialize();

// Instantiate classes
$media_ajax 			= $instance_manager->MediaAjax;
// $media_config 			= $instance_manager->MediaConfig; // converting to static
$media_generation 		= $instance_manager->MediaGeneration;
$media_cropping	 		= $instance_manager->MediaCropping;
$media_compression		= $instance_manager->MediaCompression;
$media_render		 	= $instance_manager->MediaRender;
$media_admin 			= $instance_manager->MediaAdmin;
$job_controller			= $instance_manager->JobController;
$media_rest 			= $instance_manager->MediaRest;
// $media_attribution		= $instance_manager->MediaAttribution;
