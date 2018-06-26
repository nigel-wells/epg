<?php

namespace Drupal\epg\Model\Content;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;

class programmeFilter
{
    var $nid = null;
    var $_title;
    var $_series;
    private $_movie;

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
        if(empty($node)) {
            $this->nid = null;
            return;
        }
        $this->setTitle($node->get('title')->value);
        $this->setSeries($node->get('field_filter_series')->target_id);
        $this->setMovie($node->get('field_filter_movie')->target_id);
    }

    public function update()
    {
        if($this->nid) {
            $node = Node::load($this->nid);
        } else {
            $node = Node::create(['type' => 'programme_filter']);
            $node->set('uid', 1);
            $node->status = 1;
            $node->enforceIsNew();
        }
        $node->set('title', $this->getTitle());
        $node->set('field_filter_series', $this->getSeries());
        $node->set('field_filter_movie', $this->getMovie());
        try {
            $node->save();
            $this->nid = $node->id();
        } catch (EntityStorageException $e) {
        }
    }

    /**
     * @return string
     */
    public function getSearchTitle()
    {
        $title = $this->getTitle();
        $keywords = [
            'All New',
            'New:'
        ];
        foreach($keywords as $keyword) {
            if(substr($title, 0, strlen($keyword)) == $keyword) {
                $title = trim(substr($title, strlen($keyword)));
            }
        }
        return $title;
    }

    /**
     * @param string $title
     * @return bool|int|null|string
     */
    public function isMatchingSeriesAvailable($title = '')
    {
        if(!$title) $title = $this->getTitle();
        try {
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties([
                    'type' => 'series',
                    'title' => $title
                ]);
            if ($node = reset($nodes)) {
                return $node->id();
            }
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
        }
        return false;
    }

    public function updateAllProgrammes()
    {
        try {
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties([
                    'type' => 'programme',
                    'field_programme_filter' => $this->nid
                ]);
            foreach($nodes as $node) {
                $programme = new programme($node);
                $programme->setSeries($this->getSeries());
                $programme->setMovie($this->getMovie());
                $programme->update();
            }
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
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
}