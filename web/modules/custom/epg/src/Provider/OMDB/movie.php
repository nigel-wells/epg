<?php
namespace Drupal\epg\Provider\OMDB;

class movie extends apiObject
{
    var $Title = '';
    var $Year = 0;
    var $Rated = '';
    var $Released = '';
    var $Runtime = '';
    var $Genre = '';
    var $Director = '';
    var $Writer = '';
    var $Actors = '';
    var $Plot = '';
    var $Language = '';
    var $Country = '';
    var $Awards = '';
    var $Poster = '';
    var $imdbID = '';
    var $Type = '';

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->Title;
    }

    /**
     * @return int
     */
    public function getYear()
    {
        return $this->Year;
    }

    /**
     * @return string
     */
    public function getPlot()
    {
        return $this->Plot;
    }

    /**
     * @return string
     */
    public function getPoster()
    {
        return $this->Poster;
    }

    /**
     * @return string
     */
    public function getImdbID()
    {
        return $this->imdbID;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->Type;
    }

    public function isMovie()
    {
        return (strtolower($this->getType()) == 'movie');
    }

}