<?php

namespace Drupal\epg\Model\Content;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\epg\Controller\epgController;
use Drupal\epg\Provider\TVDB\tvdb;
use Drupal\epg\Provider\TVMaze\tvMaze;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

class series
{
    var $nid = null;
    var $_title;
    var $_overview;
    var $_series_network;
    var $_first_aired;
    var $_status;
    var $_poster;
    var $_categories;
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
        if(empty($node)) {
            $this->nid = null;
            return;
        }
        $this->setTitle($node->get('title')->value);
        $this->setOverview($node->get('body')->value);
        $this->setSeriesNetwork($node->get('field_series_network')->value);
        $this->setFirstAired($node->get('field_series_first_aired')->value);
        $this->setStatus($node->get('field_series_status')->value);
        $this->setPoster($node->get('field_series_poster')->target_id);
        $this->setTvdbId($node->get('field_series_tvdb_id')->value);
        $this->setTvMazeId($node->get('field_series_tvmaze_id')->value);
        $categories = [];
        foreach($node->get('field_series_categories')->getValue() as $category) {
            foreach($category as $categoryId) {
                $categories[] = $categoryId;
            }
        }
        $this->setCategories($categories);
    }

    public function checkForExistingNode()
    {
        if(!$this->nid) {
            try {
                $nodes = \Drupal::entityTypeManager()
                    ->getStorage('node')
                    ->loadByProperties([
                        'type' => 'series',
                        'field_series_tvdb_id' => $this->getTvdbId()
                    ]);
                if ($node = reset($nodes)) {
                    $this->nid = $node->id();
                    $this->loadNodeData();
                }
            } catch (InvalidPluginDefinitionException $e) {
            }
        }
    }


    public function checkForExistingNodeTvMaze()
    {
        if(!$this->nid) {
            try {
                $nodes = \Drupal::entityTypeManager()
                    ->getStorage('node')
                    ->loadByProperties([
                        'type' => 'series',
                        'field_series_tvmaze_id' => $this->getTvMazeId()
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
            $node = Node::create(['type' => 'series']);
            $node->set('uid', 1);
            $node->status = 1;
            $node->enforceIsNew();
        }
        $node->set('title', $this->getTitle());
        $node->set('body', $this->getOverview());
        $node->set('field_series_network', $this->getSeriesNetwork());
        $node->set('field_series_first_aired', $this->getFirstAired());
        $node->set('field_series_status', $this->getStatus());
        $node->set('field_series_poster', $this->getPoster());
        $node->set('field_series_categories', $this->getCategories());
        $node->set('field_series_tvdb_id', $this->getTvdbId());
        $node->set('field_series_tvmaze_id', $this->getTvMazeId());
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
    public function getSeriesNetwork()
    {
        return $this->_series_network;
    }

    /**
     * @param mixed $series_network
     */
    public function setSeriesNetwork($series_network)
    {
        $this->_series_network = $series_network;
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
    public function getStatus()
    {
        return $this->_status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->_status = $status;
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

    public function updateEpisodeList()
    {
        if($this->getTvdbId()) {
            $tvdb = new tvdb();
            $episodes = $tvdb->getSeriesEpisodes($this->getTvdbId());
            foreach ($episodes as $tvdbEpisode) {
                $episode = new episode();
                $episode->setTvdbId($tvdbEpisode->id);
                $episode->checkForExistingNode();
                if (!$episode->nid) {
                    $episode->setSeason($tvdbEpisode->getSeason());
                    $episode->setEpisodeNumber($tvdbEpisode->getNumber());
                    $episode->setTitle($tvdbEpisode->getEpisodeName());
                    $episode->setLastUpdated($tvdbEpisode->getLastUpdated());
                    $episode->setOverview($tvdbEpisode->getOverview());
                    $episode->setFirstAired($tvdbEpisode->getFirstAired());
                    $episode->setSeries($this->nid);
                    $episode->update();
                }
            }
        }
    }

    public function attachImage($path)
    {
        if(substr($path, 0, 7) == 'file://') {
            $path = str_replace('file://C:\\', '/mnt/c/', $path);
            $path = str_replace('\\', '/', $path);
        }
        $imgData = file_get_contents($path);
        if(!$imgData) return;
        $fileName = $this->nid . '_' . substr($path, strrpos($path, '/') + 1);
        $file = File::create([
            'uid' => 1,
            'filename' => $fileName,
            'uri' => 'public://posters/' . $fileName,
            'status' => 1,
        ]);
        try {
            $file->save();
            $dir = dirname($file->getFileUri());
            if (!file_exists($dir)) {
                mkdir($dir, 0770, TRUE);
            }
            file_put_contents($file->getFileUri(), $imgData);
            $file->save();
            $file_usage = \Drupal::service('file.usage');
            $file_usage->add($file, 'epg', 'user', 1);
            $file->save();
            $this->setPoster($file->id());
            $this->update();
        } catch (EntityStorageException $e) {
        }
    }

    public function checkForUpdates()
    {
        if($this->getTvMazeId()) {
            $tvMaze = new tvMaze();
            $dataSeries = $tvMaze->getSeries($this->getTvMazeId());
            if($dataSeries !== false) {
                $this->setTitle($dataSeries->getSeriesName());
                $this->setOverview($dataSeries->getOverview());
                $this->setSeriesNetwork($dataSeries->getNetwork());
                $this->setStatus($dataSeries->getStatus());
                $this->setFirstAired($dataSeries->getFirstAired());
                if($category = $dataSeries->getCategory()) {
                    $categories = [0 => epgController::parseCategory($category)];
                    $this->setCategories($categories);
                }
                $this->update();
                if ($image = $dataSeries->getImage()) {
                    $this->attachImage($image);
                }
            }
        } elseif($this->getTvdbId()) {
            $tvdb = new tvdb();
            $dataSeries = $tvdb->getSeries($this->getTvdbId());
            $this->setTitle($dataSeries->getSeriesName());
            $this->setOverview($dataSeries->getOverview());
            $this->setSeriesNetwork($dataSeries->getNetwork());
            $this->setStatus($dataSeries->getStatus());
            $this->setFirstAired($dataSeries->getFirstAired());
            if($category = $dataSeries->getCategory()) {
                $categories = [];
                foreach($dataSeries->getCategory() as $category) {
                    $categories[] = epgController::parseCategory($category);
                }
                $this->setCategories($categories);
            }
            $this->update();
            $dataImage = $tvdb->getSeriesPoster($this->getTvdbId());
            if($dataImage !== false && $dataImage->getImage()) {
                $this->attachImage($dataImage->getImage());
            }
        }
    }

    /**
     * @return episode[]
     */
    public function getEpisodes()
    {
        try {
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties([
                    'type' => 'episodes',
                    'field_episode_series' => $this->nid
                ]);
            $episodes = [];
            foreach($nodes as $node) {
                $episodes[] = new episode($node->id());
            }
            return $episodes;
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
        }
        return [];
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

    /**
     * @return mixed
     */
    public function getPoster()
    {
        return $this->_poster;
    }

    /**
     * @param mixed $poster
     */
    public function setPoster($poster)
    {
        $this->_poster = $poster;
    }

    /**
     * @return mixed
     */
    public function getCategories()
    {
        return $this->_categories;
    }

    /**
     * @param mixed $categories
     */
    public function setCategories($categories)
    {
        $this->_categories = $categories;
    }
}