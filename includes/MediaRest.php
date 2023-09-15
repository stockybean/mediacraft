<?php

class MediaRest {
	private $instance_manager;
	private $media_config;
	private $media_generation;

	public function __construct(InstanceManager $instance_manager) {
        $this->instance_manager = $instance_manager;
        $this->media_config 	= $this->instance_manager->MediaConfig;
        $this->media_generation	= $this->instance_manager->MediaGeneration;

		add_action('rest_api_init', array($this, 'registerTestEndpoint'));
	}

    public function registerTestEndpoint() {
		register_rest_route('mediacraft/v1', '/mediacraft/object/(?P<image_id>\S+)', array(
		  'methods'   => 'GET',
		  'callback'  => array($this, 'testObject')
		));
	}

	public function testObject($request) {

		$image_id = $request->get_param('image_id');
		$media_object = new MediaObject();
		$metadata = wp_get_attachment_metadata($image_id);
		$media_object->make($image_id, $metadata);
		$media_object->validate();
		$media_object->blueprint(true);
		$media_object->mergeBlueprints();

		return $media_object->toArray();

		$this->media_generation->media_object = $media_object;

		foreach($media_object->metadata->mediacraft as $size => $size_data) {
			if( $size_data['status'] !== 'Available' ) {
				$regenerated_status = $this->media_generation->generateSize($size, $size_data);

				if( $regenerated_status ) {
					$media_object->metadata->mediacraft[$size] = $media_object->validateSize($size, $media_object);
				}
			}
		}


        // $media_object->verify();

		return $media_object->toArray();
	}
}