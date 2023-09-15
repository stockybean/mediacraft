<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 *
 * Converts <img> into <picture> with custom sources
 *
 */
class MediaRender {
	private $instance_manager;

    private $content;
    private $matches;
    private $current_image;

    private $errors = [];

    private $ignored_image_formats = ['gif', 'svg'];

    public function __construct(InstanceManager $instance_manager) {
    	$this->instance_manager = $instance_manager;

		add_filter('the_content', array($this, 'findAndReplaceImages'));
		add_filter('responsive_media_markup', array($this, 'findImages'), 10, 2);
	    // add_filter('query_vars', array($this, 'addSizeQueryVar'));
    }

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// HOOKS /////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////    

    public function findAndReplaceImages($content) {

    	// $settings = apply_filters('mediacraft_render_settings', $)
    	$this->findImages($content);
    	$this->prepareImages();
    	$this->replaceMatches();

    	return $this->content;
    }

	public function addSizeQueryVar($query_vars) {
	    $query_vars[] = 'size';
	    return $query_vars;
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// TESTING ///////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

	public function mockFindImages($data, $settings) {

		$this->content = $data['element'];

		// remove unsupported images
		if( $this->isIgnoredFormat($data['media_object']->file) ) {
            
            throw new \MediaRenderException('Ignored format');

        } else {

			$img_attributes = $this->getImageAttributes($data['element']);
			$attributes = $this->setAttributes($img_attributes, $settings['attributes']); // merge with settings

			$expected_data = [
				'info' => $data['info'] + ['attributes' => $attributes],
				'element' => $data['element'],
				'media_object' => $data['media_object']
			];

			$this->matches = [$expected_data];
        }
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// OPERATIONS ////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

	public function findImages($content, $settings = []) {

    	$this->content = $content;

    	// $settings = array_merge($this->media_config->getSettings(), $settings);

		preg_match_all('/<img[^>]*>/', $content, $matches);

		if( $matches ) {
			foreach($matches[0] as $match) {
		    	$attributes = $this->getImageAttributes($match);
				if( empty($attributes['src']) || $this->isImageExternal($attributes['src']) ) continue;

				$this->matches[] = [
					'info' => [
						'attributes' => $attributes
					],
					'element' => $match
				];
			}
		}
	}

	public function prepareImages() {
		foreach($this->matches as &$image) {
			$this->collectImageInfo($image);
			$this->maybeLoadMediaObject($image);
		}
	}

	// prior to loading media object
	public function collectImageInfo(&$image) {

		$src = $image['info']['attributes']['src'];

		// handle param'd URLs
		$parts = parse_url($src);
		parse_str($parts['query'], $query);
		if( isset($query['size']) ) {
			$size = $query['size'];
			if( in_array($size, MediaConfig::getKeySizes()) ) {

				$image['info']['width'] 	= MediaConfig::getSettings($size)['width'];
				$image['info']['height'] 	= MediaConfig::getSettings($size)['height'];
				$image['info']['size'] 		= $size;

				return;
			}
		}

	    $image_info = getimagesize($src);
	    if(!$image_info) {
	        $image_data = $this->maybeGetDimensionsFromUrl($src);
	        if($image_data) {
	            $image_info = [
	                0 => $image_data['dimensions'][0],
	                1 => $image_data['dimensions'][1]
	            ];
	        }
	    }

        // Can't find any dimensions
	    if(!$image_info) return false;

	    list($image['info']['width'], $image['info']['height']) = $image_info;
	}

	private function maybeLoadMediaObject(&$image) {

		$image_id = $this->findImageIdFromUrl($image['info']['attributes']['src']);

		if( $image_id ) {
			$metadata = wp_get_attachment_metadata($image_id);
			$media_object = new MediaObject;

			try {

				$media_object->make($image_id, $metadata);
				$media_object->validate();
				$media_object->verify();

				if( current_user_can('editor') || current_user_can('administrator') ) {

	                $this->instance_manager->MediaGeneration->media_object = $media_object;
	                $this->instance_manager->MediaGeneration->generateSizes(); 

			    }

				$image['element'] = $image;
				$image['media_object'] = $media_object;


				// pretty($media_object->metadata->mediacraft);
				pretty($media_object->sizes);
				pretty($media_object->messages);


				// if the param is in the URL skip
				if( isset($image['info']['size']) ) return;

				// now identify the image size
				if( $this->isFullSizeImage($image) ) {
			        $image['info']['size'] = 'full';
			    } else {
			        try {
			            $image['info']['size'] = $this->attemptToIdentifySize($image);
			        } catch (Exception $e) {
			            echo $e->getMessage();
			        }
			    }

			} catch (\InvalidArgumentException $e) {
				echo $e->getMessage();
			}
		}
	}

	public function replaceMatches() {
		foreach($this->matches as $key => $match) {

			try {
				$markup = $match['media_object']->getPictureMarkup(
					$match['info']['size'],
					$match['info']['attributes']
				);

				$this->content = str_replace($match['element'], $markup, $this->content);

				$this->matches[$key]['status'] = 'replaced';
				
			} catch (\ImageRenderException $e) {

				$this->matches[$key]['status'] = $e->getMessage();

			}
		}
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// HELPER ////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////	

	private function isImageExternal($url) {
		$domain = get_site_url();
      	$relative_url = str_replace($domain, '', $url);

      	if( $relative_url === $url ) return true;

      	return false;
	}

	private function attemptToIdentifySize($image) {

		foreach(MediaConfig::getSizes() as $name => $size) {
			if( $image['info']['width'] == $size['width'] &&
				$image['info']['height'] == $size['height'] ) {
				return $size['parent'] ?? $name;
			}
		}
		throw new Exception("size not found: {$image['info']['width']}x{$image['info']['height']}");
	}

	private function isFullSizeImage($image) {
		if( $image['info']['width'] == $image['media_object']->width &&
			$image['info']['height'] == $image['media_object']->height ) return true;

		return false;
	}

	public function findImageIdFromUrl($image_url) {

      	global $wpdb;
		$prefix = $wpdb->prefix;

		$search = MediaHelper::urlToDBAttachedFile($image_url);
		$attachment_id = $wpdb->get_col($wpdb->prepare('SELECT POST_ID FROM ' . $prefix . 'postmeta' . " WHERE meta_key = '_wp_attached_file' AND meta_value = '%s';", $search));

		if( !empty($attachment_id) ) return $attachment_id[0];

		return false;

		// old method to find ID via guid
     	// Thx to https://github.com/kylereicks/picturefill.js.wp/blob/master/inc/class-model-picturefill-wp.php
		$original_image_url = $image_url;
		$image_url = preg_replace('/^(.+?)(-\d+x\d+)?\.(jpg|jpeg|png|gif)((?:\?|#).+)?$/i', '$1.$3', $image_url);
		$prefix = $wpdb->prefix;

		// url should include /wp-content/ in the GUID, so let's start it there to rule out old GUID's
		$str_pos = strpos($image_url, '/wp-content');
		$image_url = substr($image_url, $str_pos);
		echo $image_url;

		// double % will escape and search for end-of-string match
		$attachment_id = $wpdb->get_col($wpdb->prepare('SELECT ID FROM ' . $prefix . 'posts' . " WHERE guid LIKE '%%%s';", $image_url));

		if (!empty($attachment_id)) {
			return $attachment_id[0];
		} else {
			return false;
		}
	}

    /**
    * Returns an array with all attributes from the original <img> element
    *
    * @param $image_node
    * @return array
    */
    public function getImageAttributes($image) {
        $image_node = mb_convert_encoding($image, 'HTML-ENTITIES', 'UTF-8');
        $dom = new DOMDocument();
        @$dom->loadHTML($image_node);
        $image = $dom->getElementsByTagName('img')->item(0);

        $attributes = [];
        foreach( $image->attributes as $attr ) {
            $attributes[$attr->nodeName] = $attr->nodeValue;
        }
        return $attributes;
    }

	private function maybeGetDimensionsFromUrl($url) {
	    $ext = pathinfo($url, PATHINFO_EXTENSION);
	    $filename = wp_basename($url, ".$ext");

	    $parts = explode('-', $filename);
	    $last_part = end($parts);

	    // Check if it's a retina variation
	    $retinaDpi = false;
	    if( preg_match('/(?:@(\d+)x)/', $last_part, $matches) ) {
	        $retinaDpi = isset($matches[1]) ? $matches[1] : false;
	    }

	    // Get dimensions if available
	    if(preg_match('/(\d+)x(\d+)/', $last_part, $matches)) {
		    return ['dimensions' => [$matches[1], $matches[2]], 'retinaDpi' => $retinaDpi];
	    }

	    return false;
	}

    /**
     * Check if the image format is valid or not
     * @param  string  $src
     * @param  array  $ignored_image_formats
     * @return boolean
     */
    public function isIgnoredFormat($src) {
        $image_info = pathinfo($src);
        return in_array($image_info['extension'], $this->ignored_image_formats);
    }

    protected function setAttributes($img_attributes, $settings_attributes) {

	    $merged = ['img' => $img_attributes];

	    if( isset($img_attributes['class']) && isset($settings_attributes['img']['class']) ) {
	        // Concatenate class values if both exist
	        $merged['img']['class'] = $img_attributes['class'] . ' ' . $settings_attributes['img']['class'];
	    } elseif( isset($settings_attributes['img']['class']) ) {
	        // Use the settings class if the original doesn't have it
	        $merged['img']['class'] = $settings_attributes['img']['class'];
	    }

	    return $merged += $settings_attributes;
    }

    /**
     * Removes images that is larger than the one inserted into the editor.
     * For example, if medium is inserted, ignore large and full.
     *
     * @param  array $images
     * @return array
     */
    protected function remove_images_larger_than_inserted($images, $largest_image_url) {
        $valid_images = [];
        foreach ($images as $image) {
            $valid_images[] = $image;
            if ($image['src'] == $largest_image_url) {
                break;
            }
        }
        return $valid_images;
    }

    public function getContent() {
    	return $this->content;
    }

    public function getMatches() {
    	return $this->matches;
    }
}