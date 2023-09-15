<?php

class JobController {
	private $instance_manager;
	private $media_generation;

	private $images;

	public function __construct(InstanceManager $instance_manager) {
		$this->instance_manager = $instance_manager;
		$this->media_generation = $this->instance_manager->MediaGeneration;

		add_action('rest_api_init', array($this, 'registerRestEndpoint'));
		add_action('mediacraft_startAsyncJob', array($this, 'dispatchJob'));
	}

    public function registerRestEndpoint() {
		register_rest_route('mediacraft/v1', '/mediacraft/status/(?P<job_id>\S+)', array(
		  'methods'   => 'GET',
		  'callback'  => array($this, 'getJobStatus')
		));
	}

    public function createJobtemp($ids) {

    	return;

	    // Save the job ID to a transient with an expiration time (e.g., 10 seconds)
	    set_transient($job_id, $job_status, 30);

	    // Schedule the WP Cron event to process the generation task
	    wp_schedule_single_event(time(), 'mediacraft_startAsyncJob', [$job_id]);

	    return $job_id;


    	foreach($this->media_object->sizes as $name => $size_data) {

    		$filepath = $this->getSizeFilePath($name, $size_settings);

	        if( !$this->shouldGenerate($size_data) ) {
	        	$this->media_object->addMessage(
	        		"Checking if {$name} should be generated",
	        		Messenger::getMessage(),
	        		'generation',
	        		'low'
	        	);
	        	continue;
	        }

            $path = $this->generateSize($name, $filepath, $size_data);
            if( $path )
            	$this->media_object->addSizeProperty($name, ['file' => $path]);
    	}

    }

    public function createJob($data) {

    	// verify a task was provided
    	if( !isset($data['task']) && !in_array($data['task'], explode(' ', 'prepare check generate regenerate')) ) {
    		throw new \InvalidArgumentException('Need to specify a task');
    	}

	    $data_type = null;
	    if( isset($data['objects']) && is_array($data['objects']) ) {
	        $job_data = $data['objects'];
	        $data_type = 'objects';
	    } elseif( isset($data['image_ids']) && is_array($data['image_ids']) ) {
	        $job_data = $data['image_ids'];
	        $data_type = 'image_ids';
	    }

	    if( !$data_type || empty($job_data) ) {
	        throw new \InvalidArgumentException('Invalid data format');
	    }

	    // Generate a unique identifier or job ID for the task
	    $job_id = uniqid('mediacraft_', true);


        $job_status = [
	        'status'     		=> 'accepted',
	        'data_type'  		=> $data_type,
	        'remaining_items'	=> $job_data,
	        'task' 				=> $data['task']
	    ];

	    // Save the job ID to a transient with an expiration time (e.g., 10 seconds)
	    set_transient($job_id, $job_status, 60);

	    // Schedule the WP Cron event to process the generation task
	    wp_schedule_single_event(time(), 'mediacraft_startAsyncJob', [$job_id]);

	    return $job_id;
    }

	public function dispatchJob($job_id) {

		// Retrieve the options from the transient using the job ID
		$job_status = get_transient($job_id);
		$data_type 	= $job_status['data_type'];
		$task 		= $job_status['task'];

	    // Function to process a batch of items
	    $processBatch = function(&$items) use (&$job_status, $task, $data_type) {
	        $batch_size = 5;
	        $max_retries = 3;

	        // Use splice to chop off the next batch, and remove them from remaining_items
	        $batch = array_splice($items, 0, $batch_size);

	        while($batch) {
	            if($data_type === 'objects') {
	                foreach($batch as $name => $item) {

	                    try {
	                        $media_object = new MediaObject;
	                        $media_object->convert($item['data']);

	                        $this->media_generation->testSizes($task, $media_object);
	                        $job_status['completed'][$name] = $this->media_generation->media_object->toArray();

	                        // clear the image from the batch
	                        unset($batch[$name]);

	                    } catch (\ImageGenerationException $e) {

	                    	if( !isset($job_status['retry'][$name]) ) {

	                    		$messages = $media_object->getMessages();

	                            $job_status['retry'][$name] = [
	                                'retry_attempts' => 0,
	                                'reason' => $e->getMessage(),
	                                'data' => $item['data'],
	                                'messages' => $messages
	                            ];

	                        } else {

	                            $job_status['retry'][$name]['retry_attempts']++;

	                        }

                            // move the job into failed
	                        if( $job_status['retry'][$name]['retry_attempts'] >= $max_retries ) {
	                            $job_status['failed'][$name] = $job_status['retry'][$name]['reason'];
	                            unset($job_status['retry'][$name]);
	                        }

	                        unset($batch[$name]);
	                    }
	                }

	            } elseif($data_type === 'image_ids') {

	                foreach($batch as $key => $ID) {

	                    try {
	                        $metadata = wp_get_attachment_metadata($ID);
	                        $this->media_generation->media_object->set($ID, $metadata);
					    	$this->media_generation->media_object->validate();

					    	// first run prepare
	                        $this->media_generation->testSizes('prepare', $this->media_generation->media_object);

	                        // $this->media_generation->testSizes($task, $this->media_generation->media_object);
	                        $job_status['completed'][$ID] = $this->media_generation->media_object->toArray();

	                        // clear the image from the batch
	                        unset($batch[$key]);

	                    } catch (\InvalidArgumentException $e) {

	                    	$messages = $this->media_generation->media_object->getMessages();

	                    	// move the job into failed
                            $job_status['failed'][$ID] = [
                            	'reason' => $e->getMessage(),
                            	'messages' => $messages
                        	];

	                        unset($batch[$key]);

	                    } catch (\ImageGenerationException $e) {

	                    	if( !isset($job_status['retry'][$ID]) ) {

	                            $job_status['retry'][$ID] = [
	                                'retry_attempts' => 0,
	                                'reason' => $e->getMessage(),
	                                'data' => $ID
	                            ];

	                        } else {

	                            $job_status['retry'][$ID]['retry_attempts']++;

	                        }

                            // move the job into failed
	                        if( $job_status['retry'][$ID]['retry_attempts'] >= $max_retries ) {

	                        	$messages = $this->media_generation->media_object->getMessages();

	                            $job_status['failed'][$ID] = [
	                            	'reason' => $job_status['retry'][$ID]['reason'],
	                            	'messages' => $messages
                            	];

	                            unset($job_status['retry'][$ID]);
	                        }

	                        unset($batch[$key]);
	                    }
	                }
	            }
	        }
	    };

		if( $job_status && $job_status['status'] === 'waitingForNextBatch') {

			// Check if it's time for a retry
			$batch_time = $job_status['batch_time'] ?? null;

			if( $batch_time && time() >= $batch_time ) {

				$job_status['status'] = 'running';
				set_transient($job_id, $job_status, 60 * 10); // extend transient

				// Process the batch
	            $processBatch($job_status['remaining_items']);

	            // Process the retry pile
	            $processBatch($job_status['retry']);

	            // If all images are processed, set status to 'completed'
	            if( empty($job_status['remaining_items']) && empty($job_status['retry']) ) {

	                $job_status['status'] = 'completed';
				    set_transient($job_id, $job_status, 3600 * 24); // 24 hours
				    return;

	            }

                // next batch or new retries
                $job_status['status'] = 'waitingForNextBatch';
                $job_status['batch_time'] = time() + 30;
                set_transient($job_id, $job_status, 60 * 10); // 10 minutes
                return;
			}

		} else {

		    // update the job status, so when we call getJobStatus we actually get some info
		    $job_status = [
		    	'status'			=> 'running',
		        'data_type'      	=> $data_type,
		        'task'				=> $task,
		        'remaining_items'	=> $job_status['remaining_items'],
		        'completed'			=> [],
		        'retry'				=> [],
		        'failed' 			=> []
		    ];
			set_transient($job_id, $job_status, 60 * 10); // 10 minutes

			// Process the batch
            $processBatch($job_status['remaining_items']);

            // Process the retry pile
            $processBatch($job_status['retry']);


            // If all images are processed, set status to 'completed'
            if( empty($job_status['remaining_items']) && empty($job_status['retry']) ) {

                $job_status['status'] = 'completed';
			    set_transient($job_id, $job_status, 3600 * 24); // 24 hours
			    return;

            }

            // next batch or new retries
            $job_status['status'] = 'waitingForNextBatch';
            $job_status['batch_time'] = time() + 30;
            set_transient($job_id, $job_status, 60 * 10); // 10 minutes
            return;
	    }
	}

	public function getJobStatus($request) {

		$job_id = $request->get_param('job_id');
		$job_status = get_transient($job_id);

		if( $job_status === false ) return ['status' => 'expired'];

		if( $job_status['status'] === 'completed' ) {
			return [
				'status' => $job_status['status'],
				'job_results' => [
					'completed' => $job_status['completed'],
					'failed' => $job_status['failed']
				]
			];
		}

		if( $job_status['status'] === 'waitingForNextBatch' ) {
			$this->dispatchJob($job_id);

			// Calculate the time difference in seconds
		    $timeDifference = $job_status['batch_time'] - time();
		    
		    return ['status' => "Waiting for next batch to start. Starts in {$timeDifference} seconds"];
		}

		return ['status' => $job_status['status']];
	}

	private function calculateExponentialDelay($initial_delay, $attempts) {
		// Calculate the exponential delay based on the number of attempts
		// For example, you can use a formula like: delay = pow(2, $attempts) * 60;
		// This will double the delay with each retry attempt, starting from 60 seconds
		return pow(2, $attempts) * 60;
	}	
}