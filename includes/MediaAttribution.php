<?php

class MediaAttribution {

  private $instance_manager;
  private $media_config;
  
  public $attribution_fields = [
    'attr_image_name' => [
        'label' => __('Image Name', 'mediacraft'),
        'input' => 'text',
        'helps' => __('The image name', 'mediacraft'),
        'application' => 'image',
        'exclusions' => ['audio', 'video', 'document']
    ],
    'attr_image_source_url' => [
        'label' => __('Image Source URL', 'mediacraft'),
        'input' => 'text',
        'helps' => __('URL where the image can be found, if available', 'mediacraft'),
        'application' => 'image',
        'exclusions' => ['audio', 'video', 'document']
    ],
    'attr_image_author_name' => [
        'label' => __('Image Author Name', 'mediacraft'),
        'input' => 'text',
        'helps' => __('Name of image author, if available', 'mediacraft'),
        'application' => 'image',
        'exclusions' => ['audio', 'video', 'document']
    ],
    'attr_image_author_url' => [
        'label' => __('Author Profile Source URL', 'mediacraft'),
        'input' => 'text',
        'helps' => __('URL for author website, social media etc..', 'mediacraft'),
        'application' => 'image',
        'exclusions' => ['audio', 'video', 'document']
    ],
    'attr_image_license' => [
        'label' => __('License', 'mediacraft'),
        'input' => 'select',
        'options' => [
            'none' => __('None', 'mediacraft'),
            'http://creativecommons.org/licenses/by/4.0/ | CC BY' => __('CC BY', 'mediacraft'),
            'https://creativecommons.org/licenses/by-sa/4.0/ | CC BY-SA' => __('CC BY-SA', 'mediacraft'),
            'https://creativecommons.org/licenses/by-nc/4.0/ | CC BY-NC' => __('CC BY-NC', 'mediacraft'),
            'https://creativecommons.org/licenses/by-nc-sa/4.0/ | CC BY-NC-SA' => __('CC BY-NC-SA', 'mediacraft'),
            'https://creativecommons.org/licenses/by-nd/4.0/ | CC BY-ND' => __('CC BY-ND', 'mediacraft'),
            'https://creativecommons.org/licenses/by-nc-nd/4.0/ | CC BY-NC-ND' => __('CC BY-NC-ND', 'mediacraft')
        ],
        'application' => 'image',
        'exclusions' => ['audio', 'video', 'document']
    ]
  ];

  public function __construct(InstanceManager $instance_manager) {
    $this->instance_manager = $instance_manager;
    $this->media_config = $this->instance_manager->MediaConfig;

    add_filter('attachment_fields_to_edit', [$this, 'applyFilter'], 11, 2);
    add_filter('attachment_fields_to_save', [$this, 'saveFields'], 11, 2);
    add_filter('media_attribution', array($this, 'insertAttribution'), 10, 1);
  }

  public function applyFilter($form_fields, $post = null) {
    foreach ($this->attribution_fields as $field => $values) {
        if (preg_match('/' . $values['application'] . '/', $post->post_mime_type) && !in_array($post->post_mime_type, $values['exclusions'])) {
            $meta = get_post_meta($post->ID, '_' . $field, true);

            switch ($values['input']) {
              default:
              case 'text':
                $values['input'] = 'text';
                break;

              case 'select':
                $values['input'] = 'html';
                $html = '<select name="attachments[' . $post->ID . '][' . $field . ']">';
                if (isset($values['options'])) {
                  foreach ($values['options'] as $k => $v) {
                    $selected = ($meta == $k) ? ' selected="selected"' : '';
                    $html .= '<option' . $selected . ' value="' . $k . '">' . $v . '</option>';
                  }
                }
                $html .= '</select>';
                $values['html'] = $html;
                break;
            }

            $values['value'] = $meta;
            $form_fields[$field] = $values;
        }
    }

    return $form_fields;
  }


  public function saveFields($post, $attachment) {
    if( !empty($this->attribution_fields) ) {
      foreach($this->attribution_fields as $field => $values) {
        if( isset($attachment[$field]) ) {
          update_post_meta($post['ID'], '_'.$field, $attachment[$field]);
        }
      }
    }

    return $post;
  }

  private function insertAttribution($image_id) {
    if (!get_post_meta($image_id, '_attr_image_name', true)) return;

    $attribution = $this->drawCitationLine($image_id);

    if ($attribution) return '<p>Photo Courtesy of '.$attribution.
    '</p>';
  }

  private function drawCitationLine($attachment_id) {
    $attributions = '';

    if (get_post_meta($attachment_id, '_attr_image_source_url', true)) {
        $attributions .= '<a href="'.get_post_meta($attachment_id, '_attr_image_source_url', true).
        '" target="_blank">'.get_post_meta($attachment_id, '_attr_image_name', true).
        '</a>';
    } else {
        $attributions .= get_post_meta($attachment_id, '_attr_image_name', true);
    }

    if (get_post_meta($attachment_id, '_attr_image_author_name', true)) {
        $attributions .= ' by ';
        if (get_post_meta($attachment_id, '_attr_image_author_url', true)) {
            $attributions .= '<a href="'.get_post_meta($attachment_id, '_attr_image_author_url', true).
            '" target="_blank">'.get_post_meta($attachment_id, '_attr_image_author_name', true).
            '</a>';
        } else {
            $attributions .= get_post_meta($attachment_id, '_attr_image_author_name', true);
        }
    }

    if (get_post_meta($attachment_id, '_attr_image_license', true) != 'none') {
        $attributions .= ' | Licensed under ';
        $pipe_pos = strpos(get_post_meta($attachment_id, '_attr_image_license', true), '|');

        if ($pipe_pos === false) {
            $license_name = get_post_meta($attachment_id, '_attr_image_license', true);
            $license_url = '';
        } else {
            $license_params = explode('|', get_post_meta($attachment_id, '_attr_image_license', true));
            $license_name = $license_params[1];
            $license_url = $license_params[0];
        }

        if ($license_url != '') {
            $attributions .= '<a href="'.$license_url.
            '" target="_blank">'.$license_name.
            '</a>';
        } else {
            $attributions .= $license_name;
        }
    }

    return $attributions;
  }

  private function imageCitation($content) {
    if (is_singular() && is_main_query()) {
      $attachments = get_children(['post_parent' => get_the_ID(), 'post_type' => 'attachment', 'post_mime_type' => 'image']);
      foreach($attachments as $attachment_id => $attachment) {
          if (get_post_meta($attachment_id, '_attr_image_name', true)) {
              $attributions .= mendo_draw_citation_line($attachment_id);
          }

          if ($attributions != "") {
              $attributions .= '<br />';
          }
      }

      if ($attributions != "") {
          $new_content = '<p><strong>Image Credits:</strong><br />'.$attributions.
          '</p>';

          if (get_post_thumbnail_id()) {
              $new_content .= '<p><strong>Featured Image Credit:</strong><br />'.mendo_draw_citation_line(get_post_thumbnail_id()).
              '</p>';
          }
      }

      $content .= $new_content;
    }

    return $content;
  }
}
