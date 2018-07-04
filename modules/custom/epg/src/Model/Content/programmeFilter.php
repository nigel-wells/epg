<?php

namespace Drupal\epg\Model\Content;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;

class programmeFilter extends baseModel
{
    var $_title;
    var $_series;
    private $_movie;
    private $_last_attempt;

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
        $this->setLastAttempt($node->get('field_filter_last_attempt')->value);
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
        $node->set('field_filter_last_attempt', $this->getLastAttempt());
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

    public function outputPosterDefaultImage()
    {
        // Establish image factors:
        $text = $this->getTitle();
        $debug = false;
        $font_size = 60; // Font size is in pixels.
        $font_file = drupal_get_path('module', 'epg') . '/src/Controller/assets/fonts/arialbd.ttf';
        $imageWidth = 778;
        $imageHeight = 1200;
        $padding = 50;
        $linePadding = 25;
        $imageWidthIncPadding = $imageWidth - ($padding * 2);
        $imageHeightIncPadding = $imageHeight - ($padding * 2);
        // Create image:
        $image = imagecreatetruecolor($imageWidth, $imageHeight);

        // Allocate text and background colors (RGB format):
        $text_color = imagecolorallocate($image, 255, 255, 255);
        $bg_color = imagecolorallocate($image, 0, 0, 0);
        $debug_color = imagecolorallocate($image, 255, 0, 0);

        // Fill image:
        imagefill($image, 0, 0, $bg_color);

        // Whitespace
        $typeSpace = imagettfbbox($font_size, 0, $font_file, ' ');
        $typeSpaceWidth = abs($typeSpace[4] - $typeSpace[0]);
        // Get the width of all the words in the title
        $words = explode(' ', $text);
        $wordDimensions = [];
        $totalWidth = ($typeSpaceWidth * (count($words) - 1));
        foreach($words as $word) {
            $type = imagettfbbox($font_size, 0, $font_file, $word);
            $typeWidth = abs($type[4] - $type[0]);
            $typeHeight = abs($type[5] - $type[1]);
            $wordDimensions[] = [
                'word' => $word,
                'width' => $typeWidth,
                'height' => $typeHeight
            ];
            $totalWidth += $typeWidth;
        }
        // Figure out how many lines of text we need to display the words
        $linesRequired = ceil($totalWidth / $imageWidthIncPadding);
        $lineCount = 0;
        $totalBlockHeight = 0;
        $totalBlockBaseLineOffset = 0;
        $firstLineHeight = 0;
        $maxWordsPerLine = ceil(count($words) / $linesRequired);
        $lines = [];
        while($lineCount < $linesRequired) {
            $line = '';
            $lineWidth = 0;
            $wordsUsed = 0;
            foreach($wordDimensions as $index => $dimensions) {
                if( $lineWidth + $typeSpaceWidth + $dimensions['width'] > $imageWidthIncPadding) {
                    $linesRequired++;
                    break;
                }
                if($line) {
                    $line .= ' ';
                    $lineWidth += $typeSpaceWidth;
                }
                $line .= $dimensions['word'];
                $lineWidth += $dimensions['width'];
                unset($wordDimensions[$index]);
                $wordsUsed++;
                if($wordsUsed == $maxWordsPerLine) {
                    break;
                }
            }
            $lines[] = $line;
            $lineCount++;
            // Get heights of the line to calculate placement on the image
            $type = imagettfbbox($font_size, 0, $font_file, $line);
            $typeHeight = abs($type[5] - $type[1]);
            $totalBlockHeight += $typeHeight;
            $totalBlockBaseLineOffset += ($typeHeight - $font_size);
            if(!$firstLineHeight) $firstLineHeight = $typeHeight;
        }
        $totalBlockHeight += $linePadding * (count($lines) - 1);
        $lineOffset = ($totalBlockHeight / 2) - $firstLineHeight;
        $heightExtra = 0;
        foreach($lines as $lineCount => $line) {
            // Get dimensions for the line
            $type = imagettfbbox($font_size, 0, $font_file, $line);
            $typeWidth = abs($type[4] - $type[0]);
            $typeHeight = abs($type[5] - $type[1]);

            // Fix starting x and y coordinates for the text:
            $x = (($imageWidthIncPadding - $typeWidth) / 2) + $padding;
            $y = (($imageHeightIncPadding / 2) + $padding) - $lineOffset;
            $lineOffset -= $typeHeight - $heightExtra + $linePadding;
            $heightExtra = ($typeHeight - $font_size);
            // Add TrueType text to image:
            if($debug) {
                imagefilledrectangle($image, 0, $y - $typeHeight, $imageWidth, $y, $debug_color);
            }
            imagettftext($image, $font_size, 0, $x, $y - $heightExtra, $text_color, $font_file, $line);
        }
        if($debug) {
            imagefilledrectangle($image, 0, ($imageHeight / 2), $imageWidth, ($imageHeight / 2), $text_color);
        }
        // Generate and send image to browser:
        header('Content-type: image/png');
        imagepng($image);

        // Destroy image in memory to free-up resources:
        imagedestroy($image);
        exit;
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
}