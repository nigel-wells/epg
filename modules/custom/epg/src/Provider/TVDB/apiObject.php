<?php
namespace Drupal\epg\Provider\TVDB;

class apiObject
{
    public $id;

    public function __construct($object) {
        $this->setObject($object);
    }

    /**
     * @param $data
     */
    public function setObject($data) {
        // Create an array so we can loop through the top level variables
        $array = array($data);
        foreach ($array[0] AS $key => $value) {
            $this->{$key} = $data->{$key};
        }
    }
}