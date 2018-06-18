<?php
namespace Drupal\epg\Provider\TVDB;

class series extends apiObject
{
    var $id = 0;
    var $seriesName = '';
    var $overview = '';
    var $network = '';
    var $status = '';
    var $firstAired = '';
    var $genre = [];

    /**
     * @return string
     */
    public function getNetwork()
    {
        return $this->network;
    }

    /**
     * @return string
     */
    public function getSeriesName()
    {
        return $this->seriesName;
    }

    /**
     * @return string
     */
    public function getOverview()
    {
        return $this->overview;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getFirstAired()
    {
        return $this->firstAired;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function getCategory()
    {
        return $this->genre;
    }


}