<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

class MediaConfig {

  private static $settings;

  public static function initialize() {
    self::loadGenerationConfig();

    add_action('after_setup_theme', array('MediaConfig', 'setupMedia'));
    add_filter('max_srcset_image_width', '__return_true');
    add_filter('big_image_size_threshold', '__return_false');
    add_filter('intermediate_image_sizes_advanced', array('MediaConfig', 'removeUnwantedSizes'), 9, 1);
    add_filter('upload_dir', array('MediaConfig', 'changeUploadDir'));
    add_filter('crop_thumbnails_image_sizes', array('MediaConfig', 'addFlyDynamicSizesToCroppingPlugin'), 10, 1);
    add_filter('image_size_names_choose', array('MediaConfig', 'makeCustomSizesAccessible'), 10, 1);
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////
  ////////////////////////////////// HOOKS /////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////////////////////////

  public static function setupMedia() {

    // add the sizes from the user JSON
    foreach(self::$settings['sizes'] as $name => $settings) {
      /**
       * Fly Dynamic Image sizes are great for outlier image uses
       * Sizes will only be generated upon being requested via Crop Featured Images editing modal
       */
      if( isset($settings['flyDynamicImage']) && $settings['flyDynamicImage'] ) {

        if( function_exists('fly_add_image_size') ) {
          fly_add_image_size($name, $settings['width'] ?? 0, $settings['height'] ?? 0, $settings['crop'] ?? ['center', 'center']);
        }

        continue;
      }

      add_image_size($name, $settings['width'] ?? 0, $settings['height'] ?? 0, $settings['crop'] ?? ['center', 'center']);
    }
  }

  public static function removeUnwantedSizes($sizes) {
    unset(
      $sizes['medium'],
      $sizes['medium_large'],
      $sizes['large'],
      $sizes['1536x1536'],
      $sizes['2048x2048']
    );

    return $sizes;
  }

  public static function changeUploadDir($uploads) {
    $year = date('Y');
    $uploads['path'] .= '/' . $year;
    $uploads['url'] .= '/' . $year;
    return $uploads;
  }

  public static function addFlyDynamicSizesToCroppingPlugin($sizes) {

    if( !function_exists('fly_get_all_image_sizes') ) return $sizes;

    $fly_sizes = fly_get_all_image_sizes();

    if( !empty($fly_sizes) ) {
      foreach( $fly_sizes as $key => $size ) {
        $fly_sizes[$key] = [
          'width' => $size['size'][0],
          'height' => $size['size'][1],
          'crop' => true,
          'name' => $key,
          'id' => $key
        ];
      }
      
      $sizes = array_merge($sizes, $fly_sizes);
    }

    return $sizes;
  }

  public static function makeCustomSizesAccessible($sizes) {
    $custom_size_names = [];

    foreach(self::$settings['sizes'] as $name => $settings) {
      $custom_size_names[$name] = $settings['displayName'] ?? $name;
    }

    return array_merge($sizes, $custom_size_names);
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////
  ////////////////////////////////// ACCESS ////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////////////////////////


  // NEED TO CREATE METHODS FOR RETRIEVAL MOST LIKELY?? IN THAT CASE, LETS MAKE RETURN GLOBALS AND SIZES TOGETHER
  private static function loadGenerationConfig() {
    $media_sizes      = json_decode(get_option('media_optimization_sizes'), true);
    $global_settings  = json_decode(get_option('mediacraft_settings'), true);

    self::$settings = [
      'sizes' => $media_sizes ?? [],
      'global' => $global_settings
    ];
  }


  // REWRITE THIS TO BE USEFULL
  public static function getSizes() {
    // convert sizes into a simpler array
    $props_we_need = ['width', 'height', 'breakpoint'];

    // simplified array
    $sizes = [];
    foreach($media_sizes as $name => $size) {

      foreach($props_we_need as $key) {
        if( isset($size[$key]) ) {
          $sizes[$name][$key] = $size[$key];
        }
      }

      if( !isset($size['addons']) || !is_array($size['addons']) ) continue;

      foreach($size['addons'] as $addon_name => $addon) {
        foreach($props_we_need as $key) {
          if( isset($addon[$key]) ) {
            $sizes[$addon_name][$key] = $addon[$key];
          }
        }

        // add the parent prop
        $sizes[$addon_name]['parent'] = $name;
      }
    }

    uasort($sizes, function($a, $b) {
      return $a['width'] - $b['width'];
    });

    return $sizes;
  }

  public static function getSettings($size = null) {

    if( $size ) {
      if( array_key_exists($size, self::$settings['sizes']) ) {
        return self::$settings['sizes'][$size];
      } else {
        $all_the_addons = array_column(self::$settings['sizes'], 'addons');

        foreach($all_the_addons as $addons) {
          if( array_key_exists($size, $addons) ) {
            return $addons[$size];
          }
        }
      }

      return false;
    }

    return self::$settings['sizes'];
  }

  public static function getAddons($size) {
    return self::getSettings($size)['addons'] ?? [];
  }

  public static function getKeySizes() {
    return array_keys(self::$settings['sizes']);
  }

  public static function getDpis() {
    return self::$settings['global']['highDensityScreens'] ?? [2];
  }

  public static function globalProperty($property) {
    if( isset(self::$settings['global'][$property]) ) return self::$settings['global'][$property];

    return false;
  }
}
