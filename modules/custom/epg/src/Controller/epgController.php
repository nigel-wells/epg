<?php

namespace Drupal\epg\Controller;

use DateTime;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\epg\Model\Content\channel;
use Drupal\epg\Model\Content\episode;
use Drupal\epg\Model\Content\movie;
use Drupal\epg\Model\Content\programme;
use Drupal\epg\Model\Content\series;
use Drupal\epg\Provider\OMDB\omDb;
use Drupal\epg\Provider\TVDB\tvdb;
use Drupal\epg\Provider\TVMaze\tvMaze;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use SimpleXMLElement;

class epgController extends ControllerBase
{
    private $showMessages = false;
    private $logMessages = false;
    function __construct()
    {
        if (\Drupal::currentUser()->id()) {
            $this->showMessages = true;
        } else {
            $this->logMessages = true;
        }
    }

    public function updateDataView()
    {
        return [
            '#type' => 'markup',
            '#markup' => $this->t('Hello, World!'),
        ];
    }

    private function logMessage($message) {
        if($this->logMessages) {
            \Drupal::logger('cron')->notice($message);
        }
        if($this->showMessages) {
            $messenger = \Drupal::messenger();
            $messenger->addMessage($message);
        }
    }

    public function getFeeds()
    {
        $path = 'public://epg/channels/';
        if (is_dir($path)) {
            $files = array_diff(scandir($path), array('..', '.'));
            foreach($files as $key => $fileName) {
                $files[$key] = $path . $fileName;
            }
            return $files;
        }
        return false;
    }

    public function importFeed()
    {
        if ($files = $this->getFeeds()) {
//            $this->importChannels($files);
            $this->importProgrammes($files);
        }
    }

    private function importChannels($files)
    {
        $this->logMessage('EPG - Importing Channels');
        foreach($files as $xmlFile) {
            $this->logMessage('EPG - Importing [' . $xmlFile . ']');
            $xmlChannels = simplexml_load_file($xmlFile);
            if (isset($xmlChannels->channel)) {
                foreach ($xmlChannels->channel as $xmlChannel) {
                    $channel = new Channel($xmlChannel['id']);
                    $channel->setChannelNumber($xmlChannel['id']);
                    if (!empty($xmlChannel->{'display-name'})) {
                        $channel->setTitle($xmlChannel->{'display-name'});
                    }
                    $channel->update();
                    // Attach icon if available
                    if (!empty($xmlChannel->{'icon'}['src'])) {
                        $channel->attachImage($xmlChannel->{'icon'}['src']);
                    }
                }
            }
        }
        $this->logMessage('EPG - Importing Channels Completed');
    }

    private function importProgrammes($files)
    {
        $this->logMessage('EPG - Importing Programmes');
        $updated = 0;
        $created = 0;
        foreach($files as $xmlFile) {
            $this->logMessage('EPG - Importing [' . $xmlFile . ']');
            $xmlProgrammes = simplexml_load_file($xmlFile);
            if (isset($xmlProgrammes->programme)) {
                foreach ($xmlProgrammes->programme as $xmlProgramme) {
                    $programme = new Programme();
                    // Add basic data first to see if we can get a match for an existing node
                    if (!empty($xmlProgramme['channel'])) {
                        $channel = new channel($xmlProgramme['channel']);
                        if ($channel->nid) {
                            $programme->setChannelNumber($channel->nid);
                        }
                    }
                    if (!empty($xmlProgramme['start'])) {
                        $programme->setStartTime($this->parseDate($xmlProgramme['start']));
                    }
                    if (!empty($xmlProgramme['stop'])) {
                        $programme->setEndTime($this->parseDate($xmlProgramme['stop']));
                    }
                    $programme->checkForExistingNode();
                    // Update other information
                    if (!empty($xmlProgramme->{'title'})) {
                        $programme->setTitle($xmlProgramme->{'title'});
                    }
                    if (!empty($xmlProgramme->{'desc'})) {
                        $programme->setDescription($xmlProgramme->{'desc'});
                    }
                    if (!empty($xmlProgramme->{'date'})) {
                        $programme->setYear($xmlProgramme->{'date'});
                    }
                    if (!empty($xmlProgramme->{'video'}->{'quality'})) {
                        $programme->setVideoQuality($xmlProgramme->{'video'}->{'quality'});
                    }
                    if (!empty($xmlProgramme->{'rating'}->{'value'})) {
                        $programme->setRating($xmlProgramme->{'rating'}->{'value'});
                    }
                    if (!empty($xmlProgramme->{'episode-num'})) {
                        $episodeNum = array_map('trim', explode('.', $xmlProgramme->{'episode-num'}));
                        if (isset($episodeNum[0])) {
                            $programme->setSeason(intval($episodeNum[0]) + 1);
                        }
                        if (isset($episodeNum[1])) {
                            $programme->setEpisodeNumber(intval($episodeNum[1]) + 1);
                        }
                        if (isset($episodeNum[2])) {
//                        $programme->setEpisodePart($episodeNum[2]);
                        }
                    }
                    $programme->updateDuration();
                    if ($programme->nid) {
                        $updated++;
                    } else {
                        $created++;
                    }
                    $programme->update();
                    // Check for an existing series and update if required
                    $seriesId = $programme->getSeries();
                    if (!$seriesId) {
                        $seriesId = $programme->isMatchingSeriesAvailable();
                    }
                    if ($seriesId) {
                        if (!$programme->getSeries()) {
                            $programme->setSeries($seriesId);
                            $programme->update();
                        }
                        $series = new series($seriesId);
                        if (!empty($xmlProgramme->{'category'})) {
                            $categories = [];
                            foreach ($xmlProgramme->{'category'} as $category) {
                                $categoryId = epgController::parseCategory($category);
                                if ($categoryId && !in_array($categoryId, $categories)) {
                                    $categories[] = $categoryId;
                                }
                            }
                            if (count($categories)) {
                                $series->setCategories($categories);
                            }
                        }
                        $series->update();
                        if (!empty($xmlProgramme->{'icon'}['src'])) {
                            // Only upload if there isn't one already
                            if (!$series->getPoster()) {
                                $series->attachImage($xmlProgramme->{'icon'}['src']);
                            }
                        }
                        // Check for any other updates to the series
                        $series->checkForUpdates();
                    }
                }
            }
        }
        $this->logMessage('EPG - Added ' . $created . ' and updated ' . $updated . ' programmes');
        $this->logMessage('EPG - Importing Programmes Completed');
    }

    static function parseCategory($category)
    {
        try {
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('taxonomy_term')
                ->loadByProperties([
                    'vid' => 'series_categories',
                    'name' => $category
                ]);
            if ($node = reset($nodes)) {
                return $node->id();
            } else {
                return epgController::createCategory($category);
            }
        } catch (InvalidPluginDefinitionException $e) {
        }
        return 0;
    }

    static function createCategory($category)
    {
        $term = \Drupal\taxonomy\Entity\Term::create([
            'vid' => 'series_categories',
            'name' => $category,
        ]);
        try {
            $term->save();
            return $term->id();
        } catch (EntityStorageException $e) {
        }
        return 0;
    }

    private function parseDate($date)
    {
        $newDate = substr($date, 0, 4) . '-' .
            substr($date, 4, 2) . '-' .
            substr($date, 6, 2) . ' ' .
            substr($date, 8, 2) . ':' .
            substr($date, 10, 2) . ':' .
            substr($date, 12, 2);
        $parseDate = new DateTime($newDate, new \DateTimeZone('Pacific/Auckland'));
        $parseDate->setTimezone(new \DateTimeZone('UTC'));
        return $parseDate->format('Y-m-d\TH:i:s');
    }

    public function importProviderData()
    {
        $this->logMessage('EPG - Matching Programmes');
        $counter = 0;
        foreach($this->getProgrammesMissingData() as $programme) {
            $this->updateProgrammeData($programme);
            $counter++;
            if($counter == 100) break;
        }
        $this->logMessage('EPG - Matching Programmes Completed');
    }

    public function updateProgrammeData(programme $programme)
    {
        $tvdb = new tvdb();
        $tvMaze = new tvMaze();
        if(!$programme->getMovie() && !$programme->getSeries() && $programme->getDuration() > 60) {
            // Check to see if its a movie
            $omDb = new omDb();
            $title = trim(str_replace([
                'Movie:',
                'Prime Flicks:'
            ], '', $programme->getTitle()));
            $this->logMessage('Searching OMDb for: ' . $title);
            if($dataMovie = $omDb->searchForMovie($title)) {
                $this->logMessage('found! - ' . $dataMovie->getTitle());
                $movie = new movie();
                $movie->setImdbId($dataMovie->getImdbID());
                $movie->checkForExistingNode();
                if(!$movie->nid) {
                    $movie->setTitle($dataMovie->getTitle());
                    $movie->setPlot($dataMovie->getPlot());
                    $movie->setYear($dataMovie->getYear());
                    $movie->update();
                    if($moviePoster = $dataMovie->getPoster()) {
                        $movie->attachImage($moviePoster);
                    }
                }
                $programme->setMovie($movie->nid);
                $programme->update();
            } else {
                $this->logMessage('Unable to find: ' . $title);
            }
        }
        if(!$programme->getSeries() && !$programme->getMovie() && !$programme->getYear()) {
            $seriesId = $programme->isMatchingSeriesAvailable();
            if ($seriesId === false) {

                $data = $tvdb->searchForSeries($programme->getSearchTitle());
                $resultsFound = count($data);
                $this->logMessage('Searching TVDB for: ' . $programme->getSearchTitle());
                foreach ($data as $dataSeries) {
                    if ($this->checkTextMatch($dataSeries->getSeriesName(), $programme->getSearchTitle(), $resultsFound)) {
                        $this->logMessage('found! - ' . $dataSeries->getSeriesName());
                        $series = new series();
                        $series->setTvdbId($dataSeries->getId());
                        $series->checkForExistingNode();
                        if(!$series->nid) {
                            $series->update();
                            $series->checkForUpdates();
                        }
                        $programme->setSeries($series->nid);
                        $programme->update();
                        break;
                    }
                }
                if(!$programme->getSeries()) {
                    $data = $tvMaze->searchForSeries($programme->getSearchTitle());
                    $resultsFound = count($data);
                    $this->logMessage('Searching TVMaze for: ' . $programme->getSearchTitle());
                    foreach ($data as $dataSeries) {
                        if ($this->checkTextMatch($dataSeries->getSeriesName(), $programme->getSearchTitle(), $resultsFound)) {
                            $this->logMessage('found! - ' . $dataSeries->getSeriesName());
                            $series = new series();
                            $series->setTvMazeId($dataSeries->getId());
                            $series->checkForExistingNodeTvMaze();
                            if (!$series->nid) {
                                $series->update();
                                $series->checkForUpdates();
                            }
                            $programme->setSeries($series->nid);
                            $programme->update();
                            break;
                        }
                    }
                }
                if(!$programme->getSeries()) {
                    // Check to see if this is a sports game and link to that sports series
                    if($sport = $this->checkIfSport($programme->getSearchTitle())) {
                        $seriesId = $programme->isMatchingSeriesAvailable($sport);
                        if(!$seriesId) {
                            $series = new series();
                            $series->setTitle($sport);
                            $series->setCategories([0 => epgController::parseCategory('Sport')]);
                            $series->update();
                            $this->logMessage('Created new sports series: ' . $sport);
                        } else {
                            $programme->setSeries($seriesId);
                            $programme->update();
                            $this->logMessage('Matched to existing sports series: ' . $sport);
                        }
                    } else {
                        $this->logMessage('Unable to find: ' . $programme->getSearchTitle());
                    }
                }
            } else {
                $programme->setSeries($seriesId);
                $programme->update();
                $this->logMessage('Series already exists: ' . $programme->getSearchTitle());
            }
        }
        if($programme->getSeries()) {
            $series = new series($programme->getSeries());
            // Update episode list if the series hasn't been updated in a while
            $series->updateEpisodeList();
            $episodes = $series->getEpisodes();
            // If we know the episode and season then we can look straight for that
            if($programme->getSeason() && $programme->getEpisodeNumber()) {
                foreach($episodes as $episode) {
                    if($episode->getEpisodeNumber() == $programme->getEpisodeNumber() &&
                        $episode->getSeason() == $programme->getSeason()) {
                        $programme->setEpisode($episode->nid);
                        $programme->setMatchScore(1000);
                        $programme->update();
                        break;
                    }
                }
            } else {
                // Do a string search based on the overview
                $matches = [];
                foreach($episodes as $episode) {
                    if($episode->getOverview() && $programme->getDescription() == $episode->getOverview()) {
                        $score = 999;
                    } else {
                        $score = $this->compareText($programme->getDescription(), $episode->getOverview());
                    }
                    $matches[$episode->nid] = $score;
                }
                // Find the best match
                $bestScore = 0;
                $bestMatch = 0;
                foreach($matches as $matchId => $score) {
                    if($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = $matchId;
                    }
                }
                foreach($episodes as $episode) if($episode->nid == $bestMatch) {
                    $this->logMessage($programme->getTitle() . '(' . $programme->nid . ') - Best Matched Score: ' . $bestScore . ' - S' . $episode->getSeason() . ' E' . $episode->getEpisodeNumber());
                    if ($bestScore > 80) {
                        $programme->setEpisode($episode->nid);
                    } else {
                        $programme->setPossibleEpisode($episode->nid);
                    }
                    $programme->setMatchScore($bestScore);
                    if ($bestScore >= 999) {
                        $programme->setEpisodeNumber($episode->getEpisodeNumber());
                        $programme->setSeason($episode->getSeason());
                    }
                    $programme->update();
                    break;
                }
            }
        } else {
            $programme->setLastAttempt(date('Y-m-d\TH:i:s'));
            $programme->update();
        }
    }

    /**
     * @return programme[]
     */
    private function getProgrammesMissingData()
    {
        try {
            $result = \Drupal::entityQuery('node')
                ->condition('type', 'programme')
                ->condition('field_programme_start_time', date('Y-m-d\tH:i:s', strtotime('-1 day')), '>')
                ->condition('field_programme_channel', '50565', '!=')
                ->condition('field_programme_channel', '50564', '!=')
//                ->condition('field_programme_channel', '50296')
                ->notExists('field_programme_series')
                ->notExists('field_programme_movie')
                ->execute();
            $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($result);
            $programmes = [];
            foreach($nodes as $node) {
                $programme = new programme($node);
                if(!$programme->getSeries() &&
                    !$programme->getMovie()
                    && strtotime($programme->getLastAttempt()) < strtotime('-3 hours')
                ) {
                    $programmes[] = $programme;
                }
            }
            return $programmes;
        } catch (InvalidPluginDefinitionException $e) {
        }
        return [];
    }

    /**
     * @param $searchText
     * @param $programmeTitle
     * @param $resultsFound
     * @return bool
     */
    private function checkTextMatch($searchText, $programmeTitle, $resultsFound)
    {
        // Very simple check to start with
        if($programmeTitle == $searchText) return true;
        // Failing that we can strip away what isn't needed and try and find a match
        $filterMatched = false;
        $textOne = $this->parseTextMatch($searchText);
        $textTwo = $this->parseTextMatch($programmeTitle);
        if($textOne == $textTwo) $filterMatched = true;
        // Remove all spaces and see if there is a match that way
        if(!$filterMatched) {
            $tmpTextOne = str_replace(' ', '', $textOne);
            $tmpTextTwo = str_replace(' ', '', $textTwo);
            if ($tmpTextOne == $tmpTextTwo) $filterMatched = true;
        }
        // If there is only one result then we can see if it is pretty close
        if(!$filterMatched && $resultsFound == 1) {
            if(strpos($textOne, $textTwo) !== false) {
                $filterMatched = true;
            } elseif(strpos($textTwo, $textOne) !== false) {
                $filterMatched = true;
            }
        }
        // Strip out stop words
        if(!$filterMatched) {
            $tmpTextOne = $this->removeStopWords($textOne);
            $tmpTextTwo = $this->removeStopWords($textTwo);
            if ($tmpTextOne == $tmpTextTwo) $filterMatched = true;
        }
        // If a filter was matched then save it for later
        if($filterMatched) {
            $this->addProgrammeFilter($programmeTitle, $searchText);
            return true;
        }
        return false;
    }

    private function addProgrammeFilter($programmeName, $filterName)
    {
        $node = Node::create(['type' => 'programme_filter']);
        $node->set('uid', 1);
        $node->status = 1;
        $node->enforceIsNew();
        $node->set('title', $programmeName);
        $node->set('field_programme_filter_name', $filterName);
        try {
            $node->save();
        } catch (EntityStorageException $e) {
        }
    }

    /**
     * @return bool|string
     */
    public function checkIfSport($title)
    {
        $keywords = [
            'WRC',
            'AFL' ,
            'Formula E',
            'NBA',
            'RWC',
            'MLS'
        ];
        foreach($keywords as $keyword) {
            if(strpos(strtolower($title), strtolower($keyword) . ' ') !== false) {
                return $keyword;
            }
        }
        return false;
    }

    /**
     * @param $text
     * @return mixed|null|string|string[]
     */
    private function removeStopWords($text)
    {
        $stopWords = [
            'the',
            'and'
        ];
        $text = str_replace($stopWords, '', $text);
        // Remove multiple spaces
        $text = preg_replace('!\s+!', ' ', $text);
        return $text;
    }

    /**
     * @param $text
     * @return null|string|string[]
     */
    private function parseTextMatch($text)
    {
        // Basic prep
        $text = strtolower(trim($text));
        // Lowercase numbers and spaces only
        $text = preg_replace('/[^a-z0-9& ]/', ' ', $text);
        // Remove 'the' from the start
        if(substr($text, 0, 4) == 'the ') {
            $text = substr($text, 4);
        }
        // Remove 'new' from the start
        if(substr($text, 0, 4) == 'new ') {
            $text = substr($text, 4);
        }
        // Replace '&' with 'and'
        if(strpos($text, '&')) {
            $text = str_replace('&', ' and ');
        }
        // Remove multiple spaces
        $text = preg_replace('!\s+!', ' ', $text);
        // Remove trailing s in case its a plural vs singular
        $words = explode(' ', $text);
        foreach ($words as $index => $word) {
            if(substr($word, -1) == 's') {
                $word = substr($word, 0, strlen($word) - 1);
                $words[$index] = $word;
            }
        }
        $text = implode(' ', $words);
        return $text;
    }

    private function compareText($textOne, $textTwo)
    {
        return similar_text($textOne, $textTwo);
    }

    public function createXML()
    {
        $xml = new SimpleXMLElement('<tv/>');
        $xml->addAttribute('generator-info-name', 'Nigel Drupal Output');
        // Add Channels
        $channels = $this->getChannels();
        foreach($channels as $channel) {
            $xmlChannel = $xml->addChild('channel');
            $xmlChannel->addAttribute('id', $channel->getChannelNumber());
            $xmlChannel->addChild('display-name', $channel->getTitle());
            if($iconId = $channel->getIcon()) {
                $channelIcon = File::load($iconId);
                $channelIconPath = \Drupal::service('file_system')->realpath($channelIcon->getFileUri());
                $channelIconPath = str_replace('/mnt/c/', 'C:\\', $channelIconPath);
                $channelIconPath = str_replace('/', '\\', $channelIconPath);
                $xmlIcon = $xmlChannel->addChild('icon');
                $xmlIcon->addAttribute('src', 'file://' . $channelIconPath);
            }
        }
        // Add Programmes
        foreach($this->getProgrammes() as $index => $programme) if ($programme->getTitle()) {
            $xmlProgramme = $xml->addChild('programme');
            $xmlProgramme->addAttribute('start', date('YmdHis O', strtotime($programme->getStartTime(true))));
            $xmlProgramme->addAttribute('stop', date('YmdHis O', strtotime($programme->getEndTime(true))));
            $xmlProgramme->addAttribute('channel', $channels[$programme->getChannelNumber()]->getChannelNumber());
            if($programme->isAMovie()) {
                $movie = new movie($programme->getMovie());
                $xmlProgramme->addChild('title', $this->parseXmlOutputText($movie->getTitle()));
                $xmlProgramme->addChild('desc', $this->parseXmlOutputText($movie->getPlot()));
                $xmlProgramme->addChild('date', $movie->getYear());
                if ($posterId = $movie->getPoster()) {
                    $poster = File::load($posterId);
                    $posterPath = \Drupal::service('file_system')->realpath($poster->getFileUri());
                    $posterPath = str_replace('/mnt/c/', 'C:\\', $posterPath);
                    $posterPath = str_replace('/', '\\', $posterPath);
                    $xmlIcon = $xmlProgramme->addChild('icon');
                    $xmlIcon->addAttribute('src', 'file://' . $posterPath);
                }
            } else {
                if($episodeNumber = $programme->getEpisodeNumber()) {
                    $seasonNumber = $programme->getSeason();
                    $seasonNumber--;
                    $episodeNumber--;
                    $episodeFormat = $seasonNumber . ' . ' . $episodeNumber . ' . 0/1';
                    $episodeSystem = 'xmltv_ns';
                } else {
                    $episodeSystem = 'original-air-date';
                    $episodeFormat = date('Y-m-d H:i:s', strtotime($programme->getStartTime(true)));
                }
                $xmlEpisode = $xmlProgramme->addChild('episode-num', $episodeFormat);
                $xmlEpisode->addAttribute('system', $episodeSystem);
                if($programme->getRating()) {
                    $xmlRating = $xmlProgramme->addChild('rating');
                    $xmlRating->addChild('value', $programme->getRating());
                }
                if($programme->getSeries()) {
                    $series = new series($programme->getSeries());
                    $xmlProgramme->addChild('title', $this->parseXmlOutputText($programme->getTitle()));
                    $xmlProgramme->addChild('desc', $this->parseXmlOutputText($series->getOverview()));
                    $categories = $series->getCategories();
                    if (count($categories)) {
                        foreach ($categories as $categoryId) {
                            $term = Term::load($categoryId);
                            $xmlProgramme->addChild('category', $term->getName());
                        }
                    }
                    if ($posterId = $series->getPoster()) {
                        $poster = File::load($posterId);
                        $posterPath = \Drupal::service('file_system')->realpath($poster->getFileUri());
                        $posterPath = str_replace('/mnt/c/', 'C:\\', $posterPath);
                        $posterPath = str_replace('/', '\\', $posterPath);
                        $xmlIcon = $xmlProgramme->addChild('icon');
                        $xmlIcon->addAttribute('src', 'file://' . $posterPath);
                        if($programme->getEpisode()) {
                            $episode = new episode($programme->getEpisode());
                            if($episode->getTitle() != $series->getTitle()) {
                                $xmlProgramme->addChild('sub-title', $this->parseXmlOutputText($episode->getTitle()));
                            }
                        }
                    }
                } else {
                    $xmlProgramme->addChild('title', $this->parseXmlOutputText($programme->getTitle()));
                    $xmlProgramme->addChild('desc', $this->parseXmlOutputText($programme->getDescription()));
                }
            }
        }
        // Save the file
        $path = 'public://epg/epgOutput.xml';
        $xml->asXML($path);
        $this->logMessage('EPG - XML file created [epgOutput.xml]');
    }

    private function parseXmlOutputText($text)
    {
        return htmlspecialchars(strip_tags($text));
    }

    /**
     * @return channel[]
     */
    public function getChannels()
    {
        try {
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties([
                    'type' => 'channel'
                ]);
            $channels = [];
            foreach($nodes as $node) {
                $channels[$node->id()] = new channel(null, $node->id());
            }
            return $channels;
        } catch (InvalidPluginDefinitionException $e) {
        }
        return [];
    }

    /**
     * @return programme[]
     */
    public function getProgrammes()
    {
        try {
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties([
                    'type' => 'programme'
                ]);
            $programmes = [];
            foreach($nodes as $node) {
                $programme = new programme($node);
                if(strtotime($programme->getEndTime()) > strtotime('-1 day')) {
                    $programmes[] = $programme;
                }
            }
            return $programmes;
        } catch (InvalidPluginDefinitionException $e) {
        }
        return [];
    }
}