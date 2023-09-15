<?php

class MediaAjax {
	private $instance_manager;
	private $media_config;
	private $job_controller;

	private $async_results;

  	public function __construct(InstanceManager $instance_manager) {
	    $this->instance_manager = $instance_manager;
	    $this->media_config = $this->instance_manager->MediaConfig;
	    $this->media_generation = $this->instance_manager->MediaGeneration;
	    $this->job_controller = $this->instance_manager->JobController;

        add_action('wp_ajax_mediacraft_tools', array($this, 'mediacraftToolsHandler'));
        add_action('wp_ajax_nopriv_mediacraft_tools', array($this, 'mediacraftToolsHandler'));
    }

    public function mediacraftToolsHandler() {
		if( !wp_verify_nonce($_POST['nonce'], 'mediacraft_nonce') ) exit('No naughty business!');

		$options  = json_decode(stripslashes($_POST['options']));
		$action   = $_POST['tool-action'];

		switch ($action) {
			case 'asyncJob':
				if( !$options->IDs ?? null ) break;

				try {

					$job_id = $this->job_controller->createJob($options->IDs);
					echo json_encode($job_id);
					
				} catch (Exception $e) {
			        $errorResponse = array('error' => 'An error occurred: ' . $e->getMessage());
			        echo json_encode($errorResponse);					
				}

				break;

			case 'regenerate':
			  	if( !$options->imageID ?? null ) break;


			    try {
					$result = $this->media_generation->regenerate($options->imageID, $force = true);
					echo json_encode($result);
			    } catch (\Exception $e) {
			        $errorResponse = array('error' => 'An error occurred: ' . $e->getMessage());
			        echo json_encode($errorResponse);
			    }

			  	break;

			case 'checkSizes':
				if( !$options->imageID ?? null ) break;

			    try {
					$analysis = $this->media_generation->analyzeMediaSizes($options->imageID);
					echo json_encode($analysis);
			    } catch (\Exception $e) {
			        $errorResponse = array('error' => 'An error occurred: ' . $e->getMessage());
			        echo json_encode($errorResponse);
			    }

			  	break;

			default:
				echo 'You have to select an action.';
				break;
		}

		wp_die();
    }
}