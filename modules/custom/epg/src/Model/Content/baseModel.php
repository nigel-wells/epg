<?php
namespace Drupal\epg\Model\Content;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;

class baseModel {
    var $nid = 0;

    public function id()
    {
        return $this->nid;
    }

    public function unPublish()
    {
        if($this->nid) {
            $node = Node::load($this->nid);
            $node->setUnpublished();
            try {
                $node->save();
            } catch (EntityStorageException $e) {
            }
        }
    }
}