<?php

namespace Drupal\epg\Model\Content;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;

class episode
{
    var $nid = null;
    var $_title;
    var $_overview;
    var $_last_updated;
    var $_first_aired;
    var $_season;
    var $_episode_number;
    var $_series;
    var $_tvdb_id;
    var $_tv_maze_id;

    function __construct($nodeId = null)
    {
        if ($nodeId !== null) {
            $this->nid = $nodeId;
            $this->loadNodeData();

        }
    }

    private function loadNodeData()
    {
        if(!$this->nid) return;
        $node = Node::load($this->nid);
        $this->setTitle($node->get('title')->value);
        $this->setOverview($node->get('body')->value);
        $this->setLastUpdated($node->get('field_episode_last_updated')->value);
        $this->setFirstAired($node->get('field_episode_first_aired')->value);
        $this->setSeason($node->get('field_episode_season')->value);
        $this->setEpisodeNumber($node->get('field_episode')->value);
        $this->setSeries($node->get('field_episode_series')->value);
        $this->setTvdbId($node->get('field_episode_tvdb_id')->value);
        $this->setTvMazeId($node->get('field_programme_tvmaze_id')->value);
    }

    public function checkForExistingNode()
    {
        if(!$this->nid) {
            try {
                $nodes = \Drupal::entityTypeManager()
                    ->getStorage('node')
                    ->loadByProperties([
                        'type' => 'episodes',
                        'field_episode_tvdb_id' => $this->getTvdbId()
                    ]);
                if ($node = reset($nodes)) {
                    $this->nid = $node->id();
                    $this->loadNodeData();
                }
            } catch (InvalidPluginDefinitionException $e) {
            }
        }
    }

    public function update()
    {
        if($this->nid) {
            $node = Node::load($this->nid);
        } else {
            $node = Node::create(['type' => 'episodes']);
            $node->set('uid', 1);
            $node->status = 1;
            $node->enforceIsNew();
        }
        $node->set('title', $this->getTitle());
        $node->set('body', $this->getOverview());
        $node->set('field_episode_last_updated', $this->getLastUpdated());
        $node->set('field_episode_first_aired', $this->getFirstAired());
        $node->set('field_episode_season', $this->getSeason());
        $node->set('field_episode', $this->getEpisodeNumber());
        $node->set('field_episode_series', $this->getSeries());
        $node->set('field_episode_tvdb_id', $this->getTvdbId());
        $node->set('field_programme_tvmaze_id', $this->getTvMazeId());
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
    public function getOverview()
    {
        return $this->_overview;
    }

    /**
     * @param mixed $overview
     */
    public function setOverview($overview)
    {
        $this->_overview = $overview;
    }

    /**
     * @return mixed
     */
    public function getFirstAired()
    {
        return $this->_first_aired;
    }

    /**
     * @param mixed $first_aired
     */
    public function setFirstAired($first_aired)
    {
        $this->_first_aired = $first_aired;
    }

    /**
     * @return mixed
     */
    public function getTvdbId()
    {
        return $this->_tvdb_id;
    }

    /**
     * @param mixed $tvdb_id
     */
    public function setTvdbId($tvdb_id)
    {
        $this->_tvdb_id = $tvdb_id;
    }

    /**
     * @return mixed
     */
    public function getLastUpdated()
    {
        return $this->_last_updated;
    }

    /**
     * @param mixed $last_updated
     */
    public function setLastUpdated($last_updated)
    {
        $this->_last_updated = $last_updated;
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
        $this->_season = $season;
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
    public function getTvMazeId()
    {
        return $this->_tv_maze_id;
    }

    /**
     * @param mixed $tv_maze_id
     */
    public function setTvMazeId($tv_maze_id)
    {
        $this->_tv_maze_id = $tv_maze_id;
    }
}