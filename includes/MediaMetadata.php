<?php

interface MediaMetadataInterface {

    public function toArray();
}

class MediaMetadata implements MediaMetadataInterface {

    // the standard properties
    private $width;
    private $height;
    private $file;
    private $filesize;
    private $sizes;
    private $image_meta;

    // mediacraft properties
    public $mediacraft;

    public function __construct($metadata) {

        // handle the source image
        $this->width        = $metadata['width'];
        $this->height       = $metadata['height'];
        $this->file         = $metadata['file'];
        $this->filesize     = $metadata['filesize'];
        $this->sizes        = $metadata['sizes'];
        $this->image_meta   = $metadata['image_meta'];

        $this->mediacraft   = $metadata['mediacraft'] ?? [];
    }

    public function toArray() {
        return [
            'width'         => $this->width,
            'height'        => $this->height,
            'file'          => $this->file,
            'filesize'      => $this->filesize,
            'sizes'         => $this->sizes,
            'image_meta'    => $this->image_meta,
            'mediacraft'    => $this->mediacraft
        ];
    }
}

