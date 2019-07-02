<?php
namespace Drupal\epg\Provider\TVDB;

class image extends apiObject
{
    var $keyType = '';
    var $fileName = '';
    var $resolution = '';

    /**
     * @return string
     */
    public function getKeyType()
    {
        return $this->keyType;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->fileName;
    }

    /**
     * @return string
     */
    public function getResolution()
    {
        return $this->resolution;
    }

    /**
     * @return string
     */
    public function getImage()
    {
        if($image = $this->getFilename()) {
            return 'https://www.thetvdb.com/banners/' . $image;
        }
        return '';

    }
}