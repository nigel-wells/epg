<?php

namespace Drupal\epg\Model\Content;

use DateTime;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;

class programme extends baseModel
{
    var $nid = null;
    var $_title;
    var $_channel_number;
    var $_description;
    var $_start_time;
    var $_end_time;
    var $_episode;
    var $_possible_episode;
    var $_episode_number;
    var $_season;
    var $_series;
    var $_match_score;
    var $_video_quality;
    private $_year;
    private $_rating;
    private $_duration = 0;
    private $_last_attempt;
    private $_movie;
    private $_filter;
    private $_valid;

    function __construct($nodeId = null)
    {
        if (is_object($nodeId) && get_class($nodeId) == 'Drupal\node\Entity\Node') {
            $this->nid = $nodeId->id();
            $this->loadNodeData($nodeId);
        } elseif ($nodeId !== null) {
            $this->nid = $nodeId;
            $this->loadNodeData();
        }
    }

    private function loadNodeData($node = null)
    {
        if(!$this->nid) return;
        if(is_null($node)) $node = Node::load($this->nid);
        $this->setTitle($node->get('title')->value);
        $this->setDescription($node->get('body')->value);
        $this->setEpisode($node->get('field_programme_episode')->target_id);
        $this->setPossibleEpisode($node->get('field_programme_possible_episode')->target_id);
        $this->setEpisodeNumber($node->get('field_programme_episode_number')->value);
        $this->setSeason($node->get('field_programme_season')->value);
        $this->setSeries($node->get('field_programme_series')->target_id);
        $this->setChannelNumber($node->get('field_programme_channel')->target_id);
        $this->setStartTime($node->get('field_programme_start_time')->value);
        $this->setEndTime($node->get('field_programme_end_time')->value);
        $this->setMatchScore($node->get('field_match_score')->value);
        $this->setVideoQuality($node->get('field_programme_video_quality')->value);
        $this->setLastAttempt($node->get('field_programme_last_attempt')->value);
        $this->setDuration($node->get('field_programme_duration')->value);
        $this->setRating($node->get('field_programme_rating')->value);
        $this->setYear($node->get('field_programme_year')->value);
        $this->setMovie($node->get('field_programme_movie')->target_id);
        $this->setFilter($node->get('field_programme_filter')->target_id);
        $this->setValid($node->get('field_programme_valid')->value);
    }

    public function checkForExistingNode()
    {
        if(!$this->nid) {
            try {
                $nodes = \Drupal::entityTypeManager()
                    ->getStorage('node')
                    ->loadByProperties([
                        'field_programme_channel' => $this->getChannelNumber(),
                        'field_programme_start_time' => $this->getStartTime(),
                        'field_programme_end_time' => $this->getEndTime(),
                    ]);
                if ($node = reset($nodes)) {
                    $this->nid = $node->id();
                    $this->loadNodeData();
                }
            } catch (InvalidPluginDefinitionException $e) {
            } catch (PluginNotFoundException $e) {
            }
        }
    }

    public function update()
    {
        if($this->nid) {
            $node = Node::load($this->nid);
        } else {
            $node = Node::create(['type' => 'programme']);
            $node->set('uid', 1);
            $node->status = 1;
            $node->enforceIsNew();
        }
        $node->set('title', $this->getTitle());
        $node->set('body', $this->getDescription());
        $node->set('field_programme_channel', $this->getChannelNumber());
        $node->set('field_programme_start_time', $this->getStartTime());
        $node->set('field_programme_end_time', $this->getEndTime());
        $node->set('field_programme_episode', $this->getEpisode());
        $node->set('field_programme_possible_episode', $this->getPossibleEpisode());
        $node->set('field_programme_episode_number', $this->getEpisodeNumber());
        $node->set('field_programme_season', $this->getSeason());
        $node->set('field_programme_series', $this->getSeries());
        $node->set('field_match_score', $this->getMatchScore());
        $node->set('field_programme_video_quality', $this->getVideoQuality());
        $node->set('field_programme_last_attempt', $this->getLastAttempt());
        $node->set('field_programme_duration', $this->getDuration());
        $node->set('field_programme_rating', $this->getRating());
        $node->set('field_programme_year', $this->getYear());
        $node->set('field_programme_movie', $this->getMovie());
        $node->set('field_programme_filter', $this->getFilter());
        $node->set('field_programme_valid', $this->isValid());
        try {
            $node->save();
            $this->nid = $node->id();
        } catch (EntityStorageException $e) {
        }
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->_title = $title;
    }

    /**
     * @return mixed
     */
    public function getChannelNumber()
    {
        return $this->_channel_number;
    }

    /**
     * @param mixed $channel_number
     */
    public function setChannelNumber($channel_number)
    {
        $this->_channel_number = intval($channel_number);
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * @param bool $timezone
     * @return mixed
     */
    public function getStartTime($timezone = false)
    {
        if($timezone) {
            $parseDate = new DateTime($this->_start_time, new \DateTimeZone('UTC'));
            $parseDate->setTimezone(new \DateTimeZone('Pacific/Auckland'));
            return $parseDate->format('Y-m-d H:i:s');
        } else {
            return $this->_start_time;
        }
    }

    /**
     * @param mixed $start_time
     */
    public function setStartTime($start_time)
    {
        $this->_start_time = $start_time;
    }

    /**
     * @param bool $timezone
     * @return mixed
     */
    public function getEndTime($timezone = false)
    {
        if($timezone) {
            $parseDate = new DateTime($this->_end_time, new \DateTimeZone('UTC'));
            $parseDate->setTimezone(new \DateTimeZone('Pacific/Auckland'));
            return $parseDate->format('Y-m-d H:i:s');
        } else {
            return $this->_end_time;
        }
    }

    /**
     * @param mixed $end_time
     */
    public function setEndTime($end_time)
    {
        $this->_end_time = $end_time;
    }

    /**
     * @return mixed
     */
    public function getEpisode()
    {
        return $this->_episode;
    }

    /**
     * @param mixed $episode
     */
    public function setEpisode($episode)
    {
        $this->_episode = intval($episode);
    }

    /**
     * @return mixed
     */
    public function getSeason()
    {
        return $this->_season;
    }

    /**
     * @param mixed $season
     */
    public function setSeason($season)
    {
        $this->_season = intval($season);
    }

    /**
     * @return bool|int|null|string
     */
    public function isMatchingFilterAvailable()
    {
        try {
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties([
                    'type' => 'programme_filter',
                    'title' => $this->getTitle()
                ]);
            if ($node = reset($nodes)) {
                return $node->id();
            }
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
        }
        return false;
    }

    /**
     * @return mixed
     */
    public function getPossibleEpisode()
    {
        return $this->_possible_episode;
    }

    /**
     * @param mixed $possible_episode
     */
    public function setPossibleEpisode($possible_episode)
    {
        $this->_possible_episode = $possible_episode;
    }

    /**
     * @return mixed
     */
    public function getEpisodeNumber()
    {
        return $this->_episode_number;
    }

    /**
     * @param mixed $episode_number
     */
    public function setEpisodeNumber($episode_number)
    {
        $this->_episode_number = $episode_number;
    }

    /**
     * @return mixed
     */
    public function getSeries()
    {
        return $this->_series;
    }

    /**
     * @param mixed $series
     */
    public function setSeries($series)
    {
        $this->_series = $series;
    }

    /**
     * @return mixed
     */
    public function getMatchScore()
    {
        return $this->_match_score;
    }

    /**
     * @param mixed $match_score
     */
    public function setMatchScore($match_score)
    {
        $this->_match_score = $match_score;
    }

    /**
     * @return mixed
     */
    public function getVideoQuality()
    {
        return $this->_video_quality;
    }

    /**
     * @param mixed $video_quality
     */
    public function setVideoQuality($video_quality)
    {
        $this->_video_quality = $video_quality;
    }

    /**
     * @return mixed
     */
    public function getLastAttempt()
    {
        return $this->_last_attempt;
    }

    /**
     * @param mixed $last_attempt
     */
    public function setLastAttempt($last_attempt)
    {
        $this->_last_attempt = $last_attempt;
    }

    /**
     * @return int
     */
    public function getDuration()
    {
        return $this->_duration;
    }

    /**
     * @param int $duration
     */
    public function setDuration($duration)
    {
        $this->_duration = intval($duration);
    }

    /**
     * return void
     */
    public function updateDuration()
    {
        $startTime = strtotime($this->getStartTime());
        $endTime = strtotime($this->getEndTime());
        $duration = ($endTime - $startTime) / 60;
        $this->setDuration($duration);
    }

    /**
     * @return bool
     */
    public function isAMovie()
    {
        if($this->getMovie()) {
            return true;
        }
        return false;
    }

    /**
     * @return mixed
     */
    public function getRating()
    {
        return $this->_rating;
    }

    /**
     * @param mixed $rating
     */
    public function setRating($rating)
    {
        $this->_rating = $rating;
    }

    /**
     * @return mixed
     */
    public function getYear()
    {
        return $this->_year;
    }

    /**
     * @param mixed $year
     */
    public function setYear($year)
    {
        $this->_year = intval($year);
    }

    /**
     * @return mixed
     */
    public function getMovie()
    {
        return $this->_movie;
    }

    /**
     * @param mixed $movie
     */
    public function setMovie($movie)
    {
        $this->_movie = $movie;
    }

    /**
     * @return mixed
     */
    public function getFilter()
    {
        return $this->_filter;
    }

    /**
     * @param mixed $filter
     */
    public function setFilter($filter)
    {
        $this->_filter = $filter;
    }

    /**
     * @return mixed
     */
    public function isValid()
    {
        return $this->_valid;
    }

    /**
     * @param mixed $valid
     */
    public function setValid($valid)
    {
        $this->_valid = intval($valid);
    }
}