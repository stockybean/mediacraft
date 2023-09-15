<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

class MediaAdmin {
	private $instance_manager;
    private $media_config;

    public function __construct(InstanceManager $instance_manager) {
        $this->instance_manager = $instance_manager;
        $this->media_config = $this->instance_manager->MediaConfig;

		add_action('admin_menu', array($this, 'adminSetup'));
		add_action('admin_init', array($this, 'registerSettings'));
		add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScriptsStyles'));
		add_action('init', array($this, 'mediaGenerationInit'));
	}

	public function adminSetup() {
		add_submenu_page(
			'upload.php', // Parent slug
			__( 'MediaCraft Pro', 'mediacraft' ),
			__( 'MediaCraft Pro', 'mediacraft' ),
			'manage_options',
			'mediacraft',
			array($this, 'loadMediaCraftFrontend'),
			3 // position
		);
	}

	public function registerSettings() {
		register_setting( 'media-optimization-group', 'mediacraft_settings' );
		register_setting( 'media-optimization-group', 'media_optimization_sizes' );
		register_setting( 'media-optimization-group', 'media_optimization_genolve_apikey' );
	}

	public function enqueueAdminScriptsStyles() {
		$current_screen = get_current_screen();
	    if ($current_screen->base !== 'media_page_mediacraft') return;

        // Enqueue Ace editor
        wp_enqueue_style('ace-editor-style', 'https://cdn.jsdelivr.net/npm/ace-builds@1.23.1/css/ace.min.css');
        wp_enqueue_script('ace-editor-script', 'https://cdn.jsdelivr.net/npm/ace-builds@1.23.1/src-min-noconflict/ace.min.js', array('jquery'), null, true);

        wp_enqueue_style('prism-style', 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.6.0/themes/prism-okaidia.min.css');
        wp_enqueue_script('prism-script', 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.6.0/prism.min.js', array('jquery'), null, true);

        wp_enqueue_script('indent-script', MEDIACRAFT_PLUGIN_URL . '/assets/js/indent.js', array(), null, true);

        wp_enqueue_style('mediacraft-styles', MEDIACRAFT_PLUGIN_URL . 'assets/css/styles.css');
        wp_enqueue_script('mediacraft-scripts', MEDIACRAFT_PLUGIN_URL . '/assets/js/scripts.js', ['jquery', 'ace-editor-script'], '1.1111', true);
        wp_localize_script('mediacraft-scripts', 'mediacraft_ajax', array(
	        'ajax_url' 	=> admin_url('admin-ajax.php'),
	        'nonce' 	=> wp_create_nonce('mediacraft_nonce')
	    ));
    }

	public function loadMediaCraftFrontend() {
	    $default_tab = null;
	    $tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
	    $templates_dir = MEDIACRAFT_PLUGIN_DIR . 'templates/';
	    ?>

	    <div class="wrap">
	        <h1><?php esc_html_e('MediaCraft Pro', 'mediacraft') ?></h1>
	        <p style="margin-top: 0;"><?php esc_html_e('A lightweight suite of tools to fully manage your media', 'mediacraft') ?></p>

	        <!-- tabs -->
	        <nav class="nav-tab-wrapper">
			    <a href="?page=mediacraft" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>">General Settings</a>
			    <a href="?page=mediacraft&tab=attachments" class="nav-tab <?php if($tab==='attachments'):?>nav-tab-active<?php endif; ?>">Analyze Attachments</a>
			    <a href="?page=mediacraft&tab=attachmentsByPage" class="nav-tab <?php if($tab==='attachmentsByPage'):?>nav-tab-active<?php endif; ?>">Attachments By Page</a>
			    <a href="?page=mediacraft&tab=imageGeneration" class="nav-tab <?php if($tab==='imageGeneration'):?>nav-tab-active<?php endif; ?>">Generate Images</a>
			    <a href="?page=mediacraft&tab=testing" class="nav-tab <?php if($tab==='testing'):?>nav-tab-active<?php endif; ?>">Testing</a>
	        </nav>

	        <div class="tab-content">
	            <?php
	            switch ($tab) :
	                case 'attachments':
	                	$attachments = $this->loadAttachments();
	                    include $templates_dir . 'attachments.php';
	                    break;
	                case 'attachmentsByPage':
	                	$pages = $this->loadPages();
	                    include $templates_dir . 'attachmentsByPage.php';
	                    break;
	                case 'imageGeneration':
	                    include $templates_dir . 'imageGeneration.php';
	                    break;
	                case 'testing':
	                    include $templates_dir . 'test.php';
	                    break;
	                default:
	                    include $templates_dir . 'generalSettings.php';
	                    break;
	            endswitch;
	            ?>
	        </div>
	    </div>
	    <?php
	}

	public function mediaGenerationInit() {
		// $image_generation = new ImageGenerationInit();
		// $image_generation->add_ajax_hooks();
	}

	private function loadAttachments() {
		$unsupported_mimes  = array( 'image/gif', 'image/bmp', 'image/tiff', 'image/x-icon', 'applicaton/pdf', 'image/svg+xml' );
		$all_mimes          = get_allowed_mime_types();
		$accepted_mimes     = array_diff( $all_mimes, $unsupported_mimes );

		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
		$args = array(
			'post_type'       => 'attachment',
			'post_mime_type'  => 'image',
			'orderby'         => 'post_date',
			'order'           => 'desc',
			'posts_per_page'  => '50',
			'post_status'     => 'inherit',
			'post_mime_type'  => $accepted_mimes,
			'paged'           => $paged
		);

		$loop = new WP_Query( $args );

		wp_reset_query();

		return $loop;
	}

	private function loadPages() {
		$pages_args = array(
			'sort_order' => 'asc',
			'sort_column' => 'post_title',
			'hierarchical' => 1,
			'exclude' => '',
			'include' => '',
			'meta_key' => '',
			'meta_value' => '',
			'authors' => '',
			'child_of' => 0,
			'parent' => -1,
			'exclude_tree' => '',
			'number' => '',
			'offset' => 0,
			'post_type' => 'page',
			'post_status' => 'publish'
		);

		$pages = get_pages($pages_args);
	}

	private function analyzeImage() {

		// insert media queries
		$media_settings = get_media_settings();

		return $element->get_markup();

		// Find and replace all <img>
		$content = preg_match_all('/<img[^>]*>/', $content, $matches);
		$analysis = array();

		if( !empty($matches) ) {
		  foreach($matches[0] as $index => $match) {

		    // Do nothing with images that has the 'rwp-not-responsive' class.
		    if ( strpos($match, 'rwp-not-responsive') ) continue;

		    $settings = $settings ?? [
		      'attributes' => [
		        'img' => [
		          'class' => 'img-as-bg'
		        ],
		        'picture' => [
		          'class' => 'responsive-media-markup'
		        ]
		      ]
		    ];

		    // new picture instance
		    $element = new responsivePicture($match[0], $media_settings, $settings);

		    $debug_info = $element->get_debug();
		    $thumbnail = $element->get_thumbnail();

		    $output = '<div class="image-code">';
		    $output .= '<div style="display: flex;flex-direction: column;flex-grow: 0;">';
		    $output .= '<img src="' . $thumbnail . '" />';
		    if( $thumbnail ) $output .= '<a class="button column-button" href="' . get_site_url() . '/wp-admin/tools.php?page=regenerate-thumbnails#/regenerate/'. $debug_info['id'] .'" title="click to regenerate image sizes" target="_blank" data-no-swup>Regenerate Images</a>';

		    /** Add Crop Thumbnails Button */
		    if( $thumbnail ) {
		      $crop_button = '';
		      $crop_button .= '<a class="button column-button cropThumbnailsLink" href="#" data-cropthumbnail=\'{"image_id":'.$debug_info['id'].',"viewmode":"single"}\' title="'.esc_attr__('Crop Image','crop-thumbnails').'">';
		      $crop_button .= '<span class="wp-media-buttons-icon"></span> '.esc_html__('Crop Image','crop-thumbnails');
		      $crop_button .= '</a>';
		      $output .= $crop_button;
		    }

		    $output .= '</div>';

		    if( isset($debug_info['notice']) ) {
		      $output .= '<div class="image-details-grid has-notice">';
		    } else {
		      $output .= '<div class="image-details-grid">';
		    }

		    // add notice to the top
		    if( isset($debug_info['notice']) ) $output .= '<div style="grid-column: 1 / -1; border-color: transparent; background-color: #fff8dd!important;"><p class="detail-title" style="color: #b38b00;">NOTICE</p><p>' . $debug_info['notice'] . '</p></div>';

		    foreach($debug_info as $key => $debug) {
		      if( $key === 'output' ) continue;
		      if( $key === 'notice' ) continue;

		      $style = '';
		      if( $key === 'img' ) $style = 'grid-column: 1 / -1;';
		      if( $key === 'location' ) $style = 'grid-column: 1 / -1;';

		      if( $key === 'images_post_media_queries_and_grouping' ) {
		        $output .= '<div class="srcset">';
		        foreach($debug as $image) {
		            $output .= '<a style="display: block;" href="'.$image['src'].'" target="_blank">' . ucfirst($image['size']) . '</a>';
		        }
		        $output .= '</div>';
		      } else {
		        $output .= '<div style="'.$style.'"><p class="detail-title">' . strtoupper($key) . '</p><p>' . $debug . '</p></div>';
		      }
		    }
		    // $output .= check_retina( $debug_info['id'], true );
		    $output .= '</div>';
		    if( !empty($debug_info['output']) ) $output .= '<code><p class="detail-title">OUTPUT</p>' . $debug_info['output'] . '</code>';
		    $output .= '</div>';

		    $analysis[$index] = $output;
		  }
		}


		return $analysis;
	}

	private function renderCropButton($attachment) {
		$crop_button = '<a class="button button-primary button-small cropThumbnailsLink" href="#" data-cropthumbnail=\'{"image_id":'.$attachment->ID.',"viewmode":"single"}\' title="'.esc_attr__('Crop Sizes','mediacraft').'">';
        $crop_button .= '<span class="wp-media-buttons-icon"></span> '.esc_html__('Crop Sizes','mediacraft');
        $crop_button .= '</a>';
        echo $crop_button;
	}

	private function renderCheckSizesButton($attachment) {
        $sizes_button = '<a class="button button-primary button-small" data-action="checkSizes" data-imageID="'.$attachment->ID.'" title="'.esc_attr__('Check Sizes','mediacraft').'">';
        $sizes_button .= esc_html__('Check Sizes','mediacraft');
        $sizes_button .= '</a>';
        echo $sizes_button;
	}

	private function renderRegenerateButton($attachment) {
        $regenerate_button = '<a class="button button-primary button-small" data-action="regenerate" data-imageID="'.$attachment->ID.'" title="'.esc_attr__('Regenerate','mediacraft').'">';
        $regenerate_button .= esc_html__('Regenerate','mediacraft');
        $regenerate_button .= '</a>';
        echo $regenerate_button;
	}
}
