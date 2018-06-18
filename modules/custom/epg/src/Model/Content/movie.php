<?php

namespace Drupal\epg\Model\Content;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\epg\Controller\epgController;
use Drupal\epg\Provider\TVDB\tvdb;
use Drupal\epg\Provider\TVMaze\tvMaze;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

class movie
{
    var $nid = null;
    var $_title;
    var $_plot;
    var $_year;
    var $_imdb_id;
    var $_poster;

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
        $this->setPlot($node->get('body')->value);
        $this->setYear($node->get('field_movie_year')->value);
        $this->setImdbId($node->get('field_movie_imdb_id')->value);
        $this->setPoster($node->get('field_movie_poster')->target_id);
    }

    public function checkForExistingNode()
    {
        if(!$this->nid) {
            try {
                $nodes = \Drupal::entityTypeManager()
                    ->getStorage('node')
                    ->loadByProperties([
                        'type' => 'movie',
                        'field_movie_imdb_id' => $this->getImdbId()
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
            $node = Node::create(['type' => 'movie']);
            $node->set('uid', 1);
            $node->status = 1;
            $node->enforceIsNew();
        }
        $node->set('title', $this->getTitle());
        $node->set('body', $this->getPlot());
        $node->set('field_movie_year', $this->getYear());
        $node->set('field_movie_imdb_id', $this->getImdbId());
        $node->set('field_movie_poster', $this->getPoster());
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
    public function getPlot()
    {
        return $this->_plot;
    }

    /**
     * @param mixed $plot
     */
    public function setPlot($plot)
    {
        $this->_plot = $plot;
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
        $this->_year = $year;
    }

    /**
     * @return mixed
     */
    public function getImdbId()
    {
        return $this->_imdb_id;
    }

    /**
     * @param mixed $imdb_id
     */
    public function setImdbId($imdb_id)
    {
        $this->_imdb_id = $imdb_id;
    }
}