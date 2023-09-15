<?php

class MediaObject {

    use Size;

	private $ID;
	private $file;
	private $width;
	private $height;
    private $attachment;
    private $metadata; 		// mediacraft has the saved sizes from DB
    private $sizes 	= []; 	// expected sizes

    private $messages;

    private $bypass_restraints = false;

    public function __construct() {
    	$this->messages = new Messages;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// CREATING //////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////


    public function make($attachment_id, $metadata) {
    	$this->reset();

    	$attachment 		= get_post($attachment_id);
    	$this->ID 			= $attachment_id;
    	$this->width 		= $metadata['width'];
    	$this->height 		= $metadata['height'];
    	$this->file 		= get_attached_file($attachment_id);
        $this->attachment 	= $attachment;
    	$this->metadata 	= new MediaMetadata($metadata);

    	$this->verify();
    }

    public function convert($array) {
    	$this->reset();

    	$this->ID 			= $array['metadata']['file']['ID'] ?? null;
        $this->attachment 	= $array['attachment'] ?? null;
    	$this->file 		= $array['metadata']['file'] ?? null;
    	$this->width 		= $array['metadata']['width'];
    	$this->height 		= $array['metadata']['height'];
    	$this->metadata 	= new MediaMetadata($array['metadata']);
    	$this->sizes 		= $array['sizes'] ?? [];

    	$this->verify();
    }

    public function validate() {
	    if( !$this->ID )		throw new \InvalidArgumentException('Missing ID');
	    if( !$this->file )		throw new \InvalidArgumentException('Missing file');
	    if( !$this->width )		throw new \InvalidArgumentException('Missing width');
	    if( !$this->height )	throw new \InvalidArgumentException('Missing height');

	    // other validations?

	    return true;
    }

    // loads the expected sizes
    public function blueprint($bypass = false) {

        $saved_bypass = $this->bypass_restraints;
        if( $bypass ) $this->bypass_restraints = true;

        $this->addSizes();
        $this->addRetinas();

        $this->bypass_restraints = $saved_bypass;
    }

    public function mergeBlueprints() {

        foreach($this->sizes as $size_name => $size_data) {

            $path = MediaHelper::fullPathFromFile($size_data['file'], $this);
            $status = 'Missing';

            if( !MediaHelper::checkFileExistence($path, $this) ) {
                $this->sizes[$size_name] = $this->updateStatus($size_data, 'Missing');
                continue;
            }

            $size = $this->validateSize($size_data, $this);

            if( $size['status'] === 'Available' ) {
                $this->mergeSize($size_name, $size);
            }
        }
    }

    public function verify() {

        if( current_user_can('editor') || current_user_can('administrator') ) {
            $this->blueprint();
            $this->mergeBlueprints();
        }

        foreach($this->metadata->mediacraft as $size_name => $size_data) {
            $this->metadata->mediacraft[$size_name] = $this->validateSize($size_data, $this);
        }
    }

    private function reset() {
        $this->ID = null;
        $this->file = null;
        $this->width = null;
        $this->height = null;
        $this->attachment = null;
        $this->metadata = null;
        $this->sizes = [];
        $this->messages = new Messages;
    }

    public function save() {
    	try {

	    	$this->validate();
	    	

    		return update_post_meta($this->ID, '_wp_attachment_metadata', $this->metadata->toArray());
    	} catch (\InvalidArgumentException $e) {
			return false;    		
    	}

    	return;
    }


    //////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// TESTING ///////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

	public function mock($metadata) {
    	$this->reset();

    	$this->ID 			= null;
        $this->attachment 	= null;
    	$this->file 		= $metadata['file'] ?? null;
    	$this->width 		= $metadata['width'];
    	$this->height 		= $metadata['height'];
    	$this->metadata 	= new MediaMetadata($metadata);
    	$this->sizes 		= [];
    }

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// BLUEPRINTING //////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

    public function addSizes() {
        foreach( MediaConfig::getSettings() as $name => $settings ) {
        	if( $this->maybeAddSize($name) ) {
	            foreach(MediaConfig::getAddons($name) as $addon_name => $addon_settings) {
	            	$this->maybeAddSize($addon_name);
	            }
        	}
        }
    }

    public function addRetinas() {
        foreach( $this->sizes as $name => $size_data ) {
        	if( $this->maybeAddRetinas($name) ) {
	        	foreach(MediaConfig::getAddons($name) as $addon_name => $addon_settings) {
	        		$this->maybeAddRetinas($addon_name);
	        	}
        	}
        }
    }

    private function maybeAddSize($name) {

    	$size_settings         = MediaConfig::getSettings($name);
    	$expected_file_path    = MediaHelper::fullPathFromName($name, $size_settings, $this);

    	if( !MediaHelper::shouldGenerate($size_settings, $this) ) {
    		$this->addMessage("Trying to create {$name}", Messenger::getMessage(), 'generating', 'low');
    		return false;
    	}

        $size_properties = [
            'width'     => $size_settings['width'],
            'height'    => $size_settings['height'],
            'file'      => wp_basename($expected_file_path),
            'crop'      => MediaHelper::sizeRequiresCropping($size_settings, $this),
            'status'    => 'pending'
        ];

        $this->mergeSize($name, $this->createSize($size_properties));

		return true;
    }

    private function maybeAddRetinas($name) {

    	if( MediaHelper::isSizeRetina($name) ) return false;

    	$retinas_added = false;
		$size_settings = MediaConfig::getSettings($name);

    	foreach(MediaConfig::getDpis() as $dpi) {
	    	$retina_name = "retina_{$dpi}x_{$name}";
    		
			// clone settings
			$retina_size_settings = $size_settings;
			$retina_size_settings['width'] *= $dpi;
			$retina_size_settings['height'] *= $dpi;

	    	$expected_file_path = MediaHelper::fullPathFromName($retina_name, $size_settings, $this);

	    	if( !MediaHelper::shouldGenerate($retina_size_settings, $this) ) {
	    		$this->addMessage("Trying to create {$name} retina", Messenger::getMessage(), 'generating', 'low');
		        return false;
	    	}

			$size_properties = [
				'width' 	=> $retina_size_settings['width'],
				'height' 	=> $retina_size_settings['height'],
				'file' 		=> wp_basename($expected_file_path),
				'crop' 		=> MediaHelper::sizeRequiresCropping($retina_size_settings, $this),
				'retinaDpi'	=> $dpi,
				'status' 	=> 'pending'
			];

            $this->mergeSize($retina_name, $this->createSize($size_properties));

			$retinas_added = true;
    	}

    	return $retinas_added;
    }

    public function mergeSize($size_name, $size_data) {

        if( !isset($this->sizes[$size_name]) ) {
            $this->sizes[$size_name] = $size_data;
        } else {
            $this->sizes[$size_name] = $this->safelyMergeSize($this->sizes[$size_name], $size_data);
        }
    }

    public function mergeMeta($size_name, $size_data) {

        if( !isset($this->metadata->mediacraft[$size_name]) ) {
            $this->metadata->mediacraft[$size_name] = $size_data;
        } else {
            $this->safelyMergeSize($this->metadata->mediacraft[$size_name], $size_data);
        }
    }

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// RENDERING /////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

    public function getPictureMarkup($size, $attributes) {

    	if( !isset($this->metadata->mediacraft[$size]) ) {
    		// $this->addMessage("Trying to render $size", 'Settings not found', 'rendering', 'low');
    		throw new \ImageRenderException('Settings not found');
    	}

        if( $this->metadata->mediacraft[$size]['status'] !== 'Available' ) {
            throw new \ImageRenderException('Size is unavailable');
        }

    	$size_data 			= $this->metadata->mediacraft[$size];
    	$picture_attributes = $this->attributesToString($attributes['picture']);
    	$img_attributes 	= $this->attributesToString($attributes['img']);

    	$addons = $this->getMergedAddonSettings($size);
    	if( empty($addons) ) throw new \ImageRenderException('No addons found');

    	$sourceMarkup = '';

    	foreach($addons as $addon_name => $addon_settings) {
	    	$source_set		= $this->createSizeSources($addon_name, $addon_settings);
	    	$media_query = '';

	    	if( isset($addon_settings['displayConditions']) ) {
		    	$media_query 	= ' ' . $this->createSizeMediaQuery($addon_settings['displayConditions']);
	    	}

	        $sourceMarkup .= <<<HTML
			<source srcset="{$source_set}"{$media_query}>\n
HTML;
    	}

    	$url = MediaHelper::fileToUrl($size_data['file'], $this);

        $markup = <<<HTML
	    <picture{$picture_attributes}>
	        {$sourceMarkup}
	        <img{$img_attributes} src="{$url}" />
	    </picture>
HTML;

        pretty(htmlspecialchars($markup, ENT_QUOTES, 'UTF-8'));

    	return $markup;
    }

	public function getPictureCode($markup) {
	    $code = htmlspecialchars($markup, ENT_QUOTES, 'UTF-8');

	    return <<<EOD
		<p class="detail-title">OUTPUT</p>
		<pre>
			<code class="language-markup">
				{$code}
			</code>
		</pre>
EOD;
	}


	// not sure where this needs to be used
    public function addConfigToSize() {
		$props_to_merge = ['displayName', 'width', 'height', 'addons']; // non-destructive merge

    	// need to merge key sizes into the addon size

    	foreach($this->sizes as $size_name => $size_data) {
    		if( $this->isSizeRetina($size_name) ) continue; // skip retina

    		$config_settings = MediaConfig::getSettings($size_name);
    		foreach($props_to_merge as $prop) {
    			if( isset($config_settings[$prop]) ) {
		    		$this->sizes[$size_name][$prop] = $config_settings[$prop];
    			}
    		}
    	}
    }

    public function getMergedAddonSettings($size) {

    	$expected_addons = MediaConfig::getAddons($size);

    	$addons = [];
    	foreach($expected_addons as $addon_name => $addon_config) {

    		if( isset($this->metadata->mediacraft[$addon_name]) ) {
    		
	    		// collect the addons addons
	    		if( isset($this->metadata->mediacraft[$addon_name]['addons']) ) {
	    			foreach($this->metadata->mediacraft[$addon_name]['addons'] as $addon_addon_name => $addon_addon_config) {
	    				if( isset($this->metadata->mediacraft[$addon_addon_name]) ) {
		    				$addons[$addon_addon_name] = $addon_addon_config + $this->metadata->mediacraft[$addon_addon_name];
	    				}
	    			}
	    		}

	    		$addons[$addon_name] = $addon_config + $this->metadata->mediacraft[$addon_name];
    		}
    	}

    	$addons = $this->sortAddonsByWidth($addons);

    	return $addons;
    }

    public function sortAddonsByWidth(&$addons) {
		uasort($addons, function($a, $b) {
			$column_sorter = 'width';
			if( $a[$column_sorter] < $b[$column_sorter] ) {
				return 1;
			} elseif( $a[$column_sorter] > $b[$column_sorter] ) {
				return -1;
			}

			return 0;
		});

		return $addons;
    }

    private function createSizeSources($addon_name, $addon_settings) {

    	$sources[] = MediaHelper::fileToUrl($addon_settings['file'], $this);

    	// we'll locate retina to attach
    	$retinas = $this->getSizeRetinas($addon_name);
    	foreach($retinas as $dpi => $retina) {
    		$retina_url = MediaHelper::fileToUrl($this->metadata->mediacraft[$retina]['file'], $this);
    		$sources[] = "{$retina_url} {$dpi}x";
    	}

    	return implode(', ', $sources);
    }

	public function getSizeRetinas($size) {

		$retinas = [];
		foreach(MediaConfig::getDpis() as $dpi) {
			$search_size = "retina_{$dpi}x_{$size}";

			if( isset($this->metadata->mediacraft[$search_size]) ) {
				$retinas[$dpi] = $search_size;
			}
		}

		return $retinas;
	}

    public function createSizeMediaQuery($conditions) {

	    $queries = [];

	    if( isset($conditions['start']) ) {
	        $queries['start'] = "(min-width: {$conditions['start']}px)";
	    }

	    if( isset($conditions['end']) ) {
	    	$queries['end'] = "(max-width: {$conditions['end']}px)";
	    }

	    if( isset($conditions['between']) ) {
	    	$queries['between'] = "(min-width: {$conditions['between'][0]}px) and (max-width: {$conditions['between'][1]}px)";
	    }

	    if( isset($conditions['notBetween']) ) {
	    	$queries['notBetween'] = "(min-width: {$condition['notBetween'][0]}px) and (max-width: {$conditions['notBetween'][1]}px)";
	    }

        // Combine "start" and "end"
	    if( isset($queries['start']) && isset($queries['end']) ) {
	        $queries['startAndEnd'] = "{$queries['start']} and {$queries['end']}";
	        unset($queries['start'], $queries['end']);
	    }

	    // Insert "screen and " before each query
	    foreach($queries as $key => $query) {
	        $queries[$key] = "screen and $query";
	    }

	    // Make sure "startAndEnd" is inserted at the beginning of the array
	    if( isset($queries['startAndEnd']) ) {
	        $queries = ['startAndEnd' => $queries['startAndEnd']] + $queries;
	    }

	    // Implode with comma
	    return 'media="' . implode(', ', $queries) . '"';
    }

    public function attributesToString($attributes) {
	    $attribute_string = '';
	    foreach( $attributes as $key => $value ) {
	    	if( $key === 'src' ) continue;
	        $attribute_string .= " $key=\"$value\"";
	    }
	    return $attribute_string;
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////// INFORMATIONAL /////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////

	public function toArray() {
        return [
            'attachment' => $this->attachment,
            'metadata' => $this->metadata->toArray(),
            'sizes' => $this->sizes,
            'messages' => $this->getMessages()
        ];
    }

    public function __get($name) {
        
        // Check if the property exists
        if( property_exists($this, $name) ) {
            return $this->$name;
        }
        
        return null;
    }

    public function addMessage($context, $message, $type, $severity) {
        $this->messages->addMessage($context, $message, $type, $severity);
    }

    public function getMessages($type = null, $severity = null) {
        return $this->messages->getMessages($type, $severity);
    }
}
