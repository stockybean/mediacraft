<?php

class MediaTest {
	private $instance_manager;
    private $media_generation;
    private $media_render;
    private $job_controller;

	private $test_images = [
	    // Landscape
	    'landscape_small' => '400x300',
	    'landscape_large' => '2400x1600',

	    // Wide
	    'wide_small' => '700x310',
	    'wide_large' => '2400x900',

	    // Square
	    'square_small' => '460x460',
	    'square_large' => '1600x1600',

	    // Portrait
	    'portrait_small' => '380x700',
	    'portrait_large' => '900x1800',
	];

	public $ready_images = [];
	public $images = [];

    public function __construct(InstanceManager $instance_manager) {
    	$this->instance_manager = $instance_manager;
        $this->media_generation	= $this->instance_manager->MediaGeneration;
        $this->media_render		= $this->instance_manager->MediaRender;
        $this->job_controller	= $this->instance_manager->JobController;
    }

	public function loadSourceImages($sizes_to_test = []) {

		foreach($this->test_images as $name => $dimensions) {

			if( !empty($sizes_to_test) ) {
				if( !in_array($name, $sizes_to_test) ) continue;
			}

			$external_image_url = "https://placehold.co/{$dimensions}/teal/FFFFFF/jpg?font=playfair-display&text=mediacraft\nmediacraft\nmediacraft\nmediacraft\nmediacraft\nmediacraft\nmediacraft";

		    // Create the full path for the temporary image file
		    $tmp_image_path = trailingslashit(MEDIACRAFT_UPLOADS_DIR) . 'mediacraft_' .$name . '.jpg';

			if( !MediaHelper::checkFileExistence($tmp_image_path) ) {
			    // Download the image from the URL
			    $downloaded_image = file_get_contents($external_image_url);

			    // Save the downloaded image to the temporary directory
			    file_put_contents($tmp_image_path, $downloaded_image);
			}

		    $this->ready_images[$name] = $tmp_image_path;
		}
	}

	public function prepareTest() {
		foreach( $this->ready_images as $name => $original_file_path ) {

		    $image_info = MediaHelper::getImageInfo($original_file_path);
		    $metadata = [
		        'width'        => $image_info['width'],
		        'height'       => $image_info['height'],
		        'file'         => $original_file_path,
		        'filesize'     => $image_info['filesize'],
		        'sizes'        => [],
		        'image_meta'   => []
		    ];

		    $media_object = new MediaObject;
		    $media_object->mock($metadata);
		    $media_object->blueprint($bypass = true);

		    $this->media_generation->testSizes('check', $media_object); // safe for test images

			$this->images[$name] = $media_object;
		}
	}

	public function createSizes() {
		foreach( $this->images as $name => $media_object ) {

		    $this->media_generation->testSizes('generate', $media_object);
		    $media_object->fakeDB();
		}
	}

	public function destroyTestImages() {

		$counter = 0;

		foreach($this->images as $name => $media_object) {

		    foreach( $media_object->sizes as $name =>  $size ) {

		    	$size_path = $this->media_generation->getPathFromFile($size['file']);
		    	if( file_exists($size_path) ) {
		    		unlink($size_path);
		    		$counter++;
		    	}
			}

			unlink($media_object->file);
			$counter++;

		}

		if( $counter ) {
			echo "Deleted $counter test files.";
		} else {
			echo 'Found no files to delete.';
		}
	}

	public function checkSizes() {
		if( empty($this->images) ) return;

		foreach($this->images as $name => $media_object) {
			$this->media_generation->testSizes('check', $media_object);
		}
	}

	public function filterSizes($status = '', $media_object) {

		if( $status ) {
			$filtered_sizes = array_filter($media_object->sizes, function($size, $name) use ($status) {
				if( MediaHelper::isSizeRetina($name) ) return false;
				return $size['status'] === $status;
			}, ARRAY_FILTER_USE_BOTH);

			return $filtered_sizes;
		}

		return $media_object->sizes;
	}

	public function renderSizes($media_object) {

		$html = '';

		foreach(MediaConfig::getKeySizes() as $key_size_name) {

			$display_name = MediaConfig::getSettings($key_size_name);

			if( !isset($media_object->sizes[$key_size_name]) ) continue;

			$size_url = MediaHelper::fileToUrl($media_object->sizes[$key_size_name]['file'], $media_object);

			$data = [
				'element' => '<img class="test-image" src="'.$size_url.'" />',
				'media_object' => $media_object,
				'info' => [
					'size' => $key_size_name
				]
			];

			$settings = [
				'attributes' => [
					'picture' => [
						'class' => 'test-picture'
					],
					'img' => [
						'class' => $key_size_name
					]
				],
			];


			try {

				$this->media_render->mockFindImages($data, $settings);
				
			} catch (ImageRenderException $e) {

				$media_object->addMessage("Trying to render $key_size_name", $e->getMessage(), 'render', 'medium');

			}

			$this->media_render->replaceMatches();

			// since we're only sending one image as our content, there would only be one match
			$matches = $this->media_render->getMatches();

			$content = $this->media_render->getContent(); // used in production

			if( $matches[0]['status'] !== 'replaced' ) {
				$media_object->addMessage(
					"Trying to render $key_size_name",
					$matches[0]['status'],
					'rendering',
					'low'
				);
				continue;
			}

			$html .= <<<END
			<div class="grid-col">
				<span>{$display_name['displayName']}</span>
				{$content}
				{$media_object->getPictureCode($content)}
			</div>
END;
		}

		return $html;
	}

	public function startNewAsyncJob($data) {
		try {

			$job_id = $this->job_controller->createJob($data);
			echo "The job has started. Job ID: $job_id\n";
			echo '<button class="btn btn-small" data-action="async-job-check" data-jobID="'.$job_id.'">Check job status</button>';

		} catch (\InvalidArgumentException $e) {
			echo 'The job couldn\'t be started. Error: ' . $e->getMessage();
		}
	}

	public function objectsToArray($objects) {
		$array = [];

		foreach($objects as $name => $object) {
			$array[$name] = [
				'data' => $object->toArray()
			];
		}

		return $array;
	}
}

// Landscape
//'landscape_small' => '400x300',
//'landscape_large' => '2400x1600',

// Wide
//'wide_small' => '700x310',
//'wide_large' => '2400x900',

// Square
//'square_small' => '460x460',
//'square_large' => '1600x1600',

// Portrait
//'portrait_small' => '380x700',
//'portrait_large' => '900x1800',

$generate_files = false;
$test = new MediaTest($this->instance_manager);

?>

<div class="wrapper">
	<div class="btn-group">
		<a class="button button-primary button-small" href="?page=mediacraft&tab=testing">Test Upload</a>
		<a class="button button-primary button-small" href="?page=mediacraft&tab=testing&action=checkSizes">Check Sizes</a>
		<a class="button button-primary button-small" href="?page=mediacraft&tab=testing&action=regenerate">Test Regenerate</a>
		<a class="button button-primary button-small" href="?page=mediacraft&tab=testing&action=async">Test Async</a>
		<a class="button button-primary button-small" href="?page=mediacraft&tab=testing&action=refresh">Delete Test Data</a>
	</div>

    <div class="wrapper-grid">
        <?php
			$action = $_GET['action'] ?? '';

			$test->loadSourceImages([]);
			$test->prepareTest();

			switch ($action) {
				case 'refresh':
					$test->destroyTestImages();
					break;

				case 'async':

					if( isset($_GET['image_ids']) ) {
					    $string_of_ids = $_GET['image_ids'];
					    
					    // Split the comma-separated values into an array
					    $image_ids = explode(',', $string_of_ids);

					    foreach( $image_ids as $key => $image_id ) {
					    	$image_ids[$key] = trim($image_id);


					    }

					    $data = [
					    	'image_ids' => $image_ids,
					    	'task'		=> 'complete'
					    ];

					    $test->startNewAsyncJob($data);
				    }

				    if( isset($_GET['objects']) && $_GET['objects'] == 'testImages' ) {
	    				$array_from_objects = $test->objectsToArray($test->images);
						$data = ['objects' => $array_from_objects, 'task' => 'regenerate'];

				    	$test->startNewAsyncJob($data);
				    } ?>

					<!-- the image ID input -->
					<h5>Process Image IDs</h5>
					<form onsubmit="submitForm(event)">
				        <label for="image_ids">Enter Image IDs (comma-separated):</label>
				        <input type="text" name="image_ids">
				        <input type="submit" value="Submit">
				    </form>

				    <h5>Process the test images</h5>
				    <form onsubmit="submitForm(event)">
				        <input type="hidden" name="objects" value="testImages">
				        <input type="submit" value="Submit">
				    </form>

				    <div id="async-results"></div>
					
					<?php break;

				case 'checkSizes':
					$test->checkSizes();
					foreach( $test->images as $name => $media_object ) : ?>
			        	<h4 style="margin: 0;"><?php echo $name; ?></h4>
		        		<p style="margin: 0.25rem 0;"><?php echo "Original: {$media_object->width}x{$media_object->height}"; ?></p>
				    	<div class="sizes-list-wrapper">
					    	<div>
					    		<h5 style="margin: 0;">Expected Sizes</h5>
						    	<?php foreach($test->filterSizes('', $media_object) as $size_name => $size) : ?>
						    		<p><?php echo $size_name; ?></p>
								<?php endforeach; ?>

					    		<h5 style="margin: 0;">Missing Sizes</h5>
					    		<?php foreach($test->filterSizes('missing', $media_object) as $size_name => $size) : ?>
						    		<p><?php echo $size_name; ?></p>
								<?php endforeach; ?>
					    	</div>
					    </div>
			    		<?php echo $media_object->getMessages('generating'); ?>
		        	<?php endforeach;
					break;
				
				case 'render':
					if( isset($_GET['image_ids']) ) {
					    $string_of_ids = $_GET['image_ids'];
					    
					    // Split the comma-separated values into an array
					    $image_ids = explode(',', $string_of_ids);

					    foreach( $image_ids as $key => $image_id ) :
					    	$image_id = trim($image_id);
						    $metadata = wp_get_attachment_metadata($image_id);

						    $media_object = new MediaObject;
						    $media_object->set($image_id, $metadata);

			        		$rendered_sizes = $test->renderSizes($media_object); ?>

				        	<h4 style="margin: 0;"><?php echo $image_id; ?></h4>
			        		<p style="margin: 0.25rem 0;"><?php echo "Original: {$media_object->width}x{$media_object->height}"; ?></p>
				    		<?php echo $media_object->getMessages('rendering'); ?>
					    	<h5 style="margin: 0;">As a rendered element</h5>
				            <div class="row">
				                <?php echo $rendered_sizes; ?>
				            </div>
					    <?php endforeach;

				    } else {
				    	echo 'No images to render';
				    }
				    break;

				default:
					$test->createSizes();

					foreach( $test->images as $name => $media_object ) :
		        		$rendered_sizes = $test->renderSizes($media_object); ?>

			        	<h4 style="margin: 0;"><?php echo $name; ?></h4>
		        		<p style="margin: 0.25rem 0;"><?php echo "Original: {$media_object->width}x{$media_object->height}"; ?></p>
				    	<div class="sizes-list-wrapper">
				    		<?php if( $generate_files ) echo $media_object->getMessages('generating'); ?>
				    		<h5 style="margin: 0;">Sizes that got generated</h5>
					    	<div class="sizes">
					    		<?php foreach($test->filterSizes('generated', $media_object) as $size_name => $size_data) : ?>
					    		<div class="grid-col">
					    			<?php echo $size_name; ?>
									<figure style="margin: 0;">
										<img style="max-width: 100%;height: auto;" src="<?php echo MediaHelper::fileToUrl($size_data['file'], $media_object); ?>" />
									</figure>
								</div>
								<?php endforeach; ?>
					    	</div>
					    </div>
			    		<?php echo $media_object->getMessages('rendering'); ?>
				    	<h5 style="margin: 0;">As a rendered element</h5>
			            <div class="row">
			                <?php echo $rendered_sizes; ?>
			            </div>

		        	<?php endforeach;
					break;
			}
		?>
    </div>
</div>

<style>
	.sizes-list-wrapper {
		margin-bottom: 2rem;
	}

	.sizes-list-wrapper .sizes {
		display: grid;
		grid-template-columns: repeat(6, minmax(150px, 300px));
		grid-auto-rows: auto;
		grid-auto-flow: row;
		gap: 5px;
		max-width: 90%;
	}

	.btn-group {
		margin-bottom: 2rem;
	}

	.wrapper-grid {

	}

	.wrapper-grid .row {
		display: grid;
		grid-template-columns: repeat(3, minmax(150px, 400px));
		grid-auto-rows: auto;
		grid-auto-flow: row;
		gap: 5px;
		margin-bottom: 2rem;
		max-width: 90%;
	}

	.test-image {
		max-width: 100%;
		height: auto;
	}

	code {
	    border-radius: 0.3rem;
	    padding: 4px 5px 5px;
	    white-space: pre;
	}

	.message-container {
		margin-bottom: 1rem;
		margin-top: 1rem;
		padding: 5px;
		border: 1px solid #ccc;
		background-color: #f9f9f9;
		font-size: 12px;
		width: auto;
		display: inline-block;
		min-width: 40%;
	}

	.message-container h6 {
		font-size: 1.2em;
		margin-top: 0;
		margin-bottom: 5px;
		color: #555;
		text-transform: capitalize;
		margin-left: 5px;
	}

	.message-list {
		list-style: none;
		padding: 0;
		margin: 0;
	}

	.message {
		padding: 2.5px 8px;
		margin: 2.5px 0;
		border-radius: 2px;
		color: #0d4f49;
		background-color: #f1f1f1;
		display: grid;
		grid-template-columns: minmax(120px, 260px) auto;
	}

	.message:first-of-type {
	  margin-top: 0;
	}

	.message:last-of-type {
	  margin-bottom: 0;
	}

	.message > span {
		font-weight: bold;
		color: #838282;
	}

	.message.high {
		background-color: #ffcccc;
		color: #d9534f;
	}

	.message.medium {
		background-color: #fff3cd;
		color: #8a6d3b;
	}

	.message.low {

	}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
	let codeBlocks = document.querySelectorAll('pre');
	console.log(codeBlocks);
	if( codeBlocks ) {
		codeBlocks.forEach(pre => {
			let code = pre.querySelector('code');
			let indented = indent.html(code.textContent.replace(/^\s*\n/gm, ''), {tabString: '	'});
			code.textContent = indented.replace(/^\s*\n/gm, '');
			pre.innerHTML = code.outerHTML;
		});
	}
})

function submitForm(event) {
    event.preventDefault();

    const formElement = event.target;
    const formData = new FormData(formElement);

    // Construct the URL with existing query parameters
    const existingParams = "page=mediacraft&tab=testing&action=async";
    const url = `upload.php?${existingParams}&${new URLSearchParams(formData).toString()}`;

    // Redirect to the constructed URL
    window.location.href = url;
}

let asyncJobCheck = document.querySelector('button[data-action="async-job-check"]');
if( asyncJobCheck ) {
	asyncJobCheck.addEventListener('click', function(e) {
		const jobID = asyncJobCheck.getAttribute('data-jobID');
		const successCallback = function(results) {
			const target = document.getElementById('async-results');

			for( const[name, image] of Object.entries(results.completed) ) {

				let sizes = '';
				Object.keys(image.sizes).forEach(function(key, index) {
				  sizes += `<li>${key}: ${image.sizes[key].status}</li>`;
				});

		        let template = `
		            <div>
		                <h5>${name}</h5>
		                <ul>
		                    ${sizes}
		                </ul>
		            </div>`;
		        target.innerHTML += template;
		    };

			for( const[name, reason] of Object.entries(results.failed) ) {
		        let template = `
		            <div>
		                <h5>${name} (Failed)</h5>
		                <p>Reason: ${reason}</p>
		            </div>`;
		        target.innerHTML += template;
		    };
		}

		// Call checkGenerationStatus every 5s
		let statusCheckInterval = setInterval(function() {
			checkGenerationStatus(jobID, successCallback, statusCheckInterval);
		}, 5000);
	});
}

function checkGenerationStatus(job_id, callback, interval) {
    let req = new XMLHttpRequest();

    req.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            let response = JSON.parse(this.responseText);

            if (response.status === 'completed') {
                // Generation task is completed
                clearInterval(interval);
                callback(response.job_results);
                console.log('Job completed successfully.');

            } else if (response.status === 'expired') {

            	clearInterval(interval);
            	console.log('Job expired :(');

            } else {
                // Generation task is still in progress
                // Update the UI to show a loading spinner or progress bar
                console.log(response.status);
            }
        }
    };

    let rest_url = "<?php echo rest_url('mediacraft/v1/mediacraft/status/'); ?>";

    req.open("GET", rest_url + job_id, true);
    req.send();
}
</script>