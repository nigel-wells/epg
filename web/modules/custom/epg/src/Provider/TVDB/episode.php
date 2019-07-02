<?php
namespace Drupal\epg\Provider\TVDB;

class episode extends apiObject
{
    var $airedEpisodeNumber = 0;
    var $airedSeason = 0;
    var $episodeName = '';
    var $overview = '';
    var $lastUpdated = '';
    var $firstAired = '';

    /**
     * @return int
     */
    public function getNumber() {
        return $this->airedEpisodeNumber;
    }

    /**
     * @return int
     */
    public function getSeason() {
        return $this->airedSeason;
    }

    /**
     * @return string
     */
    public function getEpisodeName()
    {
        return $this->episodeName;
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
    public function getLastUpdated()
    {
        return $this->lastUpdated;
    }

    /**
     * @return string
     */
    public function getFirstAired()
    {
        return $this->firstAired;
    }

}