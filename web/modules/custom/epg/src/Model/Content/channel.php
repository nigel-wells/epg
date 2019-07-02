<?php

namespace Drupal\epg\Model\Content;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\epg\Controller\epgController;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

class channel
{
    var $nid = null;
    var $_title;
    var $_channel_number;
    private $_enabled;
    private $_icon;

    function __construct($channelNumber = null, $nodeId = null)
    {
        if (is_object($nodeId) && get_class($nodeId) == 'Drupal\node\Entity\Node') {
            $this->nid = $nodeId->id();
            $this->loadNodeData();
        } elseif ($nodeId !== null) {
            $this->nid = $nodeId;
            $this->loadNodeData();
        } elseif($channelNumber !== null) {
            try {
                $nodes = \Drupal::entityTypeManager()
                    ->getStorage('node')
                    ->loadByProperties(['field_channel_number' => $channelNumber]);
                if ($node = reset($nodes)) {
                    $this->nid = $node->id();
                    $this->loadNodeData();
                }
            } catch (InvalidPluginDefinitionException $e) {
            } catch (PluginNotFoundException $e) {
            }
        }
    }

    private function loadNodeData($node = null)
    {
        if (!$this->nid) return;
        if (is_null($node)) $node = Node::load($this->nid);
        $this->setTitle($node->get('title')->value);
        $this->setChannelNumber($node->get('field_channel_number')->value);
        $this->setIcon($node->get('field_channel_icon')->target_id);
        $this->setEnabled($node->get('field_channel_enabled')->value);
    }

    public function update()
    {
        $messenger = \Drupal::messenger();
        if($this->nid) {
            $node = Node::load($this->nid);
            $messenger->addMessage('Update Channel ' . $this->getChannelNumber());
            \Drupal::logger('cron')->notice('Update Channel: ' . $this->getChannelNumber());

        } else {
            $node = Node::create(['type' => 'channel']);
            $node->set('uid', 1);
            $node->status = 1;
            $node->enforceIsNew();
            $messenger->addMessage('New Channel ' . $this->getChannelNumber());
            \Drupal::logger('cron')->notice('New Channel: ' . $this->getChannelNumber());
        }
        $node->set('title', $this->getTitle());
        $node->set('field_channel_number', $this->getChannelNumber());
        $node->set('field_channel_icon', $this->getIcon());
        $node->set('field_channel_enabled', $this->getEnabled());
        try {
            $node->save();
            $this->nid = $node->id();
        } catch (EntityStorageException $e) {
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
            $this->setIcon($file->id());
            $this->update();
        } catch (EntityStorageException $e) {
        }
    }

    /**
     * @param bool $upcomingOnly
     * @return programme[]
     */
    public function getProgrammes($upcomingOnly = true)
    {
        try {
            $result = \Drupal::entityQuery('node')
                ->condition('type', 'programme')
                ->condition('field_programme_channel', $this->nid)
                ->condition('field_programme_start_time', \Drupal::time()->getRequestTime(), '>')
                ->execute();
            $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($result);
            $programmes = [];
            foreach($nodes as $node) {
                $programmes[] = new programme($node);
            }
            return $programmes;
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
        }
        return [];
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
        $this->_channel_number = $channel_number;
    }

    /**
     * @return mixed
     */
    public function getIcon()
    {
        return $this->_icon;
    }

    /**
     * @param mixed $icon
     */
    public function setIcon($icon)
    {
        $this->_icon = $icon;
    }

    /**
     * @return mixed
     */
    public function getEnabled()
    {
        return $this->_enabled;
    }

    /**
     * @param mixed $enabled
     */
    public function setEnabled($enabled)
    {
        $this->_enabled = $enabled;
    }

    /**
     * @return bool
     */
    public function isEnabled() {
        return $this->getEnabled() === '1';
    }
}