<?php
namespace Drupal\epg\Provider\TVMaze;

class series extends apiObject
{
    var $id = 0;
    var $name = '';
    var $summary = '';
    var $network = '';
    var $status = '';
    var $premiered = '';
    var $image;
    var $type = '';

    /**
     * @return string
     */
    public function getNetwork()
    {
        return $this->network->name;
    }

    /**
     * @return string
     */
    public function getSeriesName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getOverview()
    {
        return $this->summary;
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
        return $this->premiered;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getImage()
    {
        return $this->image->original;
    }

    public function getCategory()
    {
        return $this->type;
    }
}