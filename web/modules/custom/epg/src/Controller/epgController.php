<?php

namespace Drupal\epg\Controller;

use DateTime;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\epg\Model\Content\channel;
use Drupal\epg\Model\Content\episode;
use Drupal\epg\Model\Content\movie;
use Drupal\epg\Model\Content\programme;
use Drupal\epg\Model\Content\programmeFilter;
use Drupal\epg\Model\Content\series;
use Drupal\epg\Provider\OMDB\omDb;
use Drupal\epg\Provider\TVDB\tvdb;
use Drupal\epg\Provider\TVMaze\tvMaze;
use Drupal\file\Entity\File;
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

    private function logMessage($message)
    {
        if ($this->logMessages) {
            \Drupal::logger('cron')->notice($message);
        }
        if ($this->showMessages) {
            $messenger = \Drupal::messenger();
            $messenger->addMessage($message);
        }
    }

    public function getFeeds()
    {
        $feeds = [];
        $feedHost = 'https://www.freeviewnz.tv';
        $xml = $xml = $this->getXMLFeed($feedHost . '/localservices/opg/schedule/');
        if(!empty($xml->Index)) {
            foreach($xml->Index->UrlDate as $feedIndex) {
                $feeds[] = $feedHost . $feedIndex->{'Url'};
            }
        }
        return $feeds;
    }

    public function importFeed()
    {
        if ($files = $this->getFeeds()) {
//            $this->importChannels($files);
            $this->importProgrammes($files);
        }
    }

    private function getXMLFeed($URL)
    {
        $ch = curl_init();
        $timeout = 30;
        curl_setopt($ch, CURLOPT_URL, $URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $xml = curl_exec($ch);
        curl_close($ch);
        return simplexml_load_string($xml);
    }

    private function importChannels($files)
    {
        $this->logMessage('EPG - Importing Channels');
        foreach ($files as $xmlFile) {
            $this->logMessage('EPG - Importing [' . $xmlFile . ']');
            $xml = $this->getXMLFeed($xmlFile);
            if (isset($xml->Channels)) {
                foreach ($xml->Channels->OpgChannelItem as $xmlChannelItem) {
                    $xmlChannel = $xmlChannelItem->Channel;
                    $channel = new Channel($xmlChannel['FreeviewChannelNumber']);
                    $channel->setChannelNumber($xmlChannel['FreeviewChannelNumber']);
                    if (!empty($xmlChannel['Name'])) {
                        $channel->setTitle($xmlChannel['Name']);
                    }
                    $channel->update();
                    // Mark all programmes as ready to be un-published unless they get a fresh import
                    // This allows for changes in the guide as well as dealing with midnight on the
                    // Final day of the EPG data
                    foreach ($channel->getProgrammes() as $programme) {
                        $programme->setValid(false);
                        $programme->update();
                    }
                }
                break;
            }
        }
        $this->logMessage('EPG - Importing Channels Completed');
    }

    private function importProgrammes($files)
    {
        $this->logMessage('EPG - Importing Programmes');
        $updated = 0;
        $created = 0;
        $unPublished = 0;
        foreach ($files as $xmlFile) {
            $this->logMessage('EPG - Importing [' . $xmlFile . ']');
            $xml = $this->getXMLFeed($xmlFile);
            if (isset($xml->Channels)) {
                foreach ($xml->Channels->OpgChannelItem as $xmlChannelItem) {
                    $xmlChannel = $xmlChannelItem->Channel;
                    if (!empty($xmlChannel['FreeviewChannelNumber'])) {
                        $channel = new Channel($xmlChannel['FreeviewChannelNumber']);
                        if ($channel->isEnabled()) {
                            foreach ($xmlChannelItem->Programs->Programs->ProgramEntity as $xmlProgramme) {
                                $programme = new Programme();
                                // Add basic data first to see if we can get a match for an existing node
                                if (!empty($xmlChannel['FreeviewChannelNumber'])) {
                                    $channel = new Channel($xmlChannel['FreeviewChannelNumber']);
                                    if ($channel->nid) {
                                        $programme->setChannelNumber($channel->nid);
                                    }
                                }
                                if (!empty($xmlProgramme->{'StartTime'})) {
                                    $programme->setStartTime($this->parseDate($xmlProgramme->{'StartTime'}));
                                }
                                if (!empty($xmlProgramme->{'EndTime'})) {
                                    $programme->setEndTime($this->parseDate($xmlProgramme->{'EndTime'}));
                                }
                                $programme->checkForExistingNode();
                                // Update other information
                                if (!empty($xmlProgramme->{'Title'})) {
                                    $programme->setTitle((string)$xmlProgramme->{'Title'});
                                }
                                if (!empty($xmlProgramme->{'Synopsis'})) {
                                    $programme->setDescription((string)$xmlProgramme->{'Synopsis'});
                                }
                                if (!empty($xmlProgramme->{'programme_year'})) {
                                    $programme->setYear($xmlProgramme->{'programme_year'});
                                }
                                if (!empty($xmlProgramme->{'IsHD'})) {
                                    $programme->setVideoQuality((string)$xmlProgramme->{'IsHD'} == 'true' ? 'HD' : 'SD');
                                }
                                if (!empty($xmlProgramme->{'Classification'})) {
                                    $programme->setRating((string)$xmlProgramme->{'Classification'});
                                }
//                        if (!empty($xmlProgramme->{'episode-num'})) {
//                            $episodeNum = array_map('trim', explode('.', $xmlProgramme->{'episode-num'}));
//                            if (isset($episodeNum[0])) {
//                                $programme->setSeason(intval($episodeNum[0]) + 1);
//                            }
//                            if (isset($episodeNum[1])) {
//                                $programme->setEpisodeNumber(intval($episodeNum[1]) + 1);
//                            }
//                            if (isset($episodeNum[2])) {
////                        $programme->setEpisodePart($episodeNum[2]);
//                            }
//                        }
                                $programme->updateDuration();
                                if ($programme->nid) {
                                    $updated++;
                                } else {
                                    $created++;
                                }
                                $programme->setValid(true);
                                $programme->update();
                                // Check for an existing filter and update if required
                                $filterId = $programme->getFilter();
                                if (!$filterId) {
                                    $filterId = $programme->isMatchingFilterAvailable();
                                    if (!$filterId) {
                                        $programmeFilter = new programmeFilter();
                                        $programmeFilter->setTitle($programme->getTitle());
                                        $programmeFilter->update();
                                        $filterId = $programmeFilter->nid;
                                    }
                                    $programme->setFilter($filterId);
                                    $programme->update();
                                }
                                // Update series and movie information if already available
                                $programmeFilter = new programmeFilter($filterId);
                                $programme->setMovie($programmeFilter->getMovie());
                                $programme->setSeries($programmeFilter->getSeries());
                                $programme->update();
                                // Check for an existing series and update if required
                                $seriesId = $programmeFilter->getSeries();
                                if ($seriesId) {
                                    $series = new series($seriesId);
                                    $categories = [];
                                    $genres = [];
                                    if (!empty($xmlProgramme->{'Genre'})) {
                                        $genres[] = (string)$xmlProgramme->{'Genre'};
                                    }
                                    if (!empty($xmlProgramme->{'SubGenre'})) {
                                        $genres[] = (string)$xmlProgramme->{'SubGenre'};
                                    }
                                    foreach ($genres as $genre) {
                                        $categoryId = epgController::parseCategory($genre);
                                        if ($categoryId && !in_array($categoryId, $categories)) {
                                            $categories[] = $categoryId;
                                        }
                                    }
                                    if (count($categories)) {
                                        $series->setCategories($categories);
                                    }
                                    $series->update();
//                            if (!empty($xmlProgramme->{'icon'}['src'])) {
//                                // Only upload if there isn't one already
//                                if (!$series->getPoster()) {
//                                    $series->attachImage($xmlProgramme->{'icon'}['src']);
//                                }
//                            }
                                    // Check for any other updates to the series
//                        $series->checkForUpdates();
                                }
                            }
                        }
                    }
                }
            }
        }
        foreach ($this->getInvalidProgrammes() as $programme) {
            $programme->unPublish();
            $unPublished++;
        }
        $this->logMessage('EPG - Added ' . $created . ', updated ' . $updated . ', and ' . $unPublished . ' un-published programmes');
        $this->logMessage('EPG - Importing Programmes Completed');
    }

    /**
     * @return programme[]
     */
    private function getInvalidProgrammes()
    {
        try {
            $result = \Drupal::entityQuery('node')
                ->condition('type', 'programme')
                ->condition('field_programme_valid', false)
                ->condition('status', '1')
                ->execute();
            $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($result);
            $programmes = [];
            foreach ($nodes as $node) {
                $programmes[] = new programme($node);
            }
            return $programmes;
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
        }
        return [];
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
        } catch (PluginNotFoundException $e) {
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
        $parseDate = new DateTime($date, new \DateTimeZone('Pacific/Auckland'));
        $parseDate->setTimezone(new \DateTimeZone('UTC'));
        return $parseDate->format('Y-m-d\TH:i:s');
    }

    public function importProviderData()
    {
        $this->logMessage('EPG - Matching Programme Movies');
        $this->checkProgrammeIsMovie();
        $this->logMessage('EPG - Matching Programme Complete');
        $this->logMessage('EPG - Matching Programme Filters');
        $counter = 0;
        foreach ($this->getProgrammeFiltersMissingData() as $programmeFilter) {
            $this->updateProgrammeFilterData($programmeFilter);
            $counter++;
            if ($counter == 100) break;
        }
        $this->logMessage('EPG - Matching Programme Filters Completed');
    }

    public function updateProgrammeFilterData(programmeFilter $programmeFilter)
    {
        $tvdb = new tvdb();
        $tvMaze = new tvMaze();
        $data = $tvdb->searchForSeries($programmeFilter->getSearchTitle());
        $resultsFound = count($data);
        $this->logMessage('Searching TVDB for: ' . $programmeFilter->getSearchTitle());
        foreach ($data as $dataSeries) {
            if ($this->checkTextMatch($dataSeries->getSeriesName(), $programmeFilter->getSearchTitle(), $resultsFound)) {
                $this->logMessage('found! - ' . $dataSeries->getSeriesName());
                $series = new series();
                $series->setTvdbId($dataSeries->getId());
                $series->checkForExistingNode();
                if (!$series->nid) {
                    $series->update();
                    $series->checkForUpdates();
                }
                $programmeFilter->setSeries($series->nid);
                $programmeFilter->update();
                break;
            }
        }
        if (!$programmeFilter->getSeries()) {
            $data = $tvMaze->searchForSeries($programmeFilter->getSearchTitle());
            $resultsFound = count($data);
            $this->logMessage('Searching TVMaze for: ' . $programmeFilter->getSearchTitle());
            foreach ($data as $dataSeries) {
                if ($this->checkTextMatch($dataSeries->getSeriesName(), $programmeFilter->getSearchTitle(), $resultsFound)) {
                    $this->logMessage('found! - ' . $dataSeries->getSeriesName());
                    $series = new series();
                    $series->setTvMazeId($dataSeries->getId());
                    $series->checkForExistingNodeTvMaze();
                    if (!$series->nid) {
                        $series->update();
                        $series->checkForUpdates();
                    }
                    $programmeFilter->setSeries($series->nid);
                    $programmeFilter->update();
                    break;
                }
            }
        }
        if (!$programmeFilter->getSeries()) {
            // Check to see if this is a sports game and link to that sports series
            if ($sport = $this->checkIfSport($programmeFilter->getSearchTitle())) {
                $seriesId = $programmeFilter->isMatchingSeriesAvailable($sport);
                if (!$seriesId) {
                    $series = new series();
                    $series->setTitle($sport);
                    $series->setCategories([0 => epgController::parseCategory('Sport')]);
                    $series->update();
                    $this->logMessage('Created new sports series: ' . $sport);
                } else {
                    $programmeFilter->setSeries($seriesId);
                    $programmeFilter->update();
                    $this->logMessage('Matched to existing sports series: ' . $sport);
                }
            } else {
                $this->logMessage('Unable to find: ' . $programmeFilter->getSearchTitle());
            }
        }
        // If a series was found then update all programmes relating to this filter
        if ($programmeFilter->getSeries()) {
            $programmeFilter->updateAllProgrammes();
        } else {
            $programmeFilter->setLastAttempt(date('Y-m-d\TH:i:s'));
            $programmeFilter->update();
        }
//        if($programmeFilter->getSeries()) {
//            $series = new series($programmeFilter->getSeries());
//            // Update episode list if the series hasn't been updated in a while
//            $series->updateEpisodeList();
//            $episodes = $series->getEpisodes();
//            // If we know the episode and season then we can look straight for that
//            if($programmeFilter->getSeason() && $programmeFilter->getEpisodeNumber()) {
//                foreach($episodes as $episode) {
//                    if($episode->getEpisodeNumber() == $programmeFilter->getEpisodeNumber() &&
//                        $episode->getSeason() == $programmeFilter->getSeason()) {
//                        $programmeFilter->setEpisode($episode->nid);
//                        $programmeFilter->setMatchScore(1000);
//                        $programmeFilter->update();
//                        break;
//                    }
//                }
//            } else {
//                // Do a string search based on the overview
//                $matches = [];
//                foreach($episodes as $episode) {
//                    if($episode->getOverview() && $programmeFilter->getDescription() == $episode->getOverview()) {
//                        $score = 999;
//                    } else {
//                        $score = $this->compareText($programmeFilter->getDescription(), $episode->getOverview());
//                    }
//                    $matches[$episode->nid] = $score;
//                }
//                // Find the best match
//                $bestScore = 0;
//                $bestMatch = 0;
//                foreach($matches as $matchId => $score) {
//                    if($score > $bestScore) {
//                        $bestScore = $score;
//                        $bestMatch = $matchId;
//                    }
//                }
//                foreach($episodes as $episode) if($episode->nid == $bestMatch) {
//                    $this->logMessage($programmeFilter->getTitle() . '(' . $programmeFilter->nid . ') - Best Matched Score: ' . $bestScore . ' - S' . $episode->getSeason() . ' E' . $episode->getEpisodeNumber());
//                    if ($bestScore > 80) {
//                        $programmeFilter->setEpisode($episode->nid);
//                    } else {
//                        $programmeFilter->setPossibleEpisode($episode->nid);
//                    }
//                    $programmeFilter->setMatchScore($bestScore);
//                    if ($bestScore >= 999) {
//                        $programmeFilter->setEpisodeNumber($episode->getEpisodeNumber());
//                        $programmeFilter->setSeason($episode->getSeason());
//                    }
//                    $programmeFilter->update();
//                    break;
//                }
//            }
//        }
    }

    private function checkProgrammeIsMovie()
    {
        $programmes = $this->getProgrammePossibleMovies();
        $counter = 0;
        foreach ($programmes as $programme) {
            $this->updateProgrammeData($programme);
            $counter++;
            if ($counter == 50) break;
        }
    }

    public function updateProgrammeData(programmeFilter $programmeFilter)
    {
        // Check to see if its a movie
        $omDb = new omDb();
        $title = trim(str_replace([
            'Movie:',
            'Prime Flicks:'
        ], '', $programmeFilter->getTitle()));
        $this->logMessage('Searching OMDb for: ' . $title);
        if ($dataMovie = $omDb->searchForMovie($title)) {
            $this->logMessage('found! - ' . $dataMovie->getTitle());
            $movie = new movie();
            $movie->setImdbId($dataMovie->getImdbID());
            $movie->checkForExistingNode();
            if (!$movie->nid) {
                $movie->setTitle($dataMovie->getTitle());
                $movie->setPlot($dataMovie->getPlot());
                $movie->setYear($dataMovie->getYear());
                $movie->update();
                if ($moviePoster = $dataMovie->getPoster()) {
                    $movie->attachImage($moviePoster);
                }
            }
            $programmeFilter->setMovie($movie->nid);
            $programmeFilter->update();
            return true;
        } else {
            $programmeFilter->setLastAttempt(date('Y-m-d\TH:i:s'));
            $programmeFilter->update();
            $this->logMessage('Unable to find: ' . $title);
        }
        return false;
    }

    /**
     * @return programme[]
     */
    private function getProgrammePossibleMovies()
    {
        try {
            $result = \Drupal::entityQuery('node')
                ->condition('type', 'programme')
                ->notExists('field_programme_series')
                ->notExists('field_programme_movie')
                ->condition('field_programme_duration', '60', '>')
                ->condition('field_programme_start_time', date('Y-m-d\tH:i:s', strtotime('-1 day')), '>')
                ->condition('status', '1')
                ->execute();
            $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($result);
            $programmes = [];
            foreach ($nodes as $node) {
                $programme = new programme($node);
                if (strtotime($programme->getLastAttempt()) < strtotime('-3 hours')) {
                    $programmes[] = $programme;
                }
            }
            return $programmes;
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
        }
        return [];
    }

    /**
     * @return programmeFilter[]
     */
    private function getProgrammeFiltersMissingData()
    {
        try {
            $result = \Drupal::entityQuery('node')
                ->condition('type', 'programme_filter')
                ->notExists('field_filter_series')
                ->notExists('field_filter_movie')
                ->execute();
            $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($result);
            $programmeFilters = [];
            foreach ($nodes as $node) {
                $programmeFilter = new programmeFilter($node);
                if (strtotime($programmeFilter->getLastAttempt()) < strtotime('-3 hours')) {
                    $programmeFilters[] = $programmeFilter;
                }
            }
            return $programmeFilters;
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
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
        if ($programmeTitle == $searchText) return true;
        // Failing that we can strip away what isn't needed and try and find a match
        $filterMatched = false;
        $textOne = $this->parseTextMatch($searchText);
        $textTwo = $this->parseTextMatch($programmeTitle);
        if ($textOne == $textTwo) $filterMatched = true;
        // Remove all spaces and see if there is a match that way
        if (!$filterMatched) {
            $tmpTextOne = str_replace(' ', '', $textOne);
            $tmpTextTwo = str_replace(' ', '', $textTwo);
            if ($tmpTextOne == $tmpTextTwo) $filterMatched = true;
        }
        // If there is only one result then we can see if it is pretty close
        if (!$filterMatched && $resultsFound == 1) {
            if (strpos($textOne, $textTwo) !== false) {
                $filterMatched = true;
            } elseif (strpos($textTwo, $textOne) !== false) {
                $filterMatched = true;
            }
        }
        // Strip out stop words
        if (!$filterMatched) {
            $tmpTextOne = $this->removeStopWords($textOne);
            $tmpTextTwo = $this->removeStopWords($textTwo);
            if ($tmpTextOne == $tmpTextTwo) $filterMatched = true;
        }
        return $filterMatched;
    }

    /**
     * @return bool|string
     */
    public function checkIfSport($title)
    {
        foreach ($this->getSportKeywords() as $keyword) {
            if (strpos(strtolower($title), strtolower($keyword) . ' ') !== false) {
                return $keyword;
            }
        }
        return false;
    }

    /**
     * @return array
     */
    private function getSportKeywords()
    {
        try {
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('taxonomy_term')
                ->loadByProperties([
                    'vid' => 'sport_keywords',
                ]);
            $keywords = [];
            foreach ($nodes as $node) {
                $term = Term::load($node->id());
                $keywords[] = $term->getName();
            }
            return $keywords;
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
        }
        return [];
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
        if (substr($text, 0, 4) == 'the ') {
            $text = substr($text, 4);
        }
        // Remove 'new' from the start
        if (substr($text, 0, 4) == 'new ') {
            $text = substr($text, 4);
        }
        // Replace '&' with 'and'
        if (strpos($text, '&')) {
            $text = str_replace('&', ' and ');
        }
        // Remove multiple spaces
        $text = preg_replace('!\s+!', ' ', $text);
        // Remove trailing s in case its a plural vs singular
        $words = explode(' ', $text);
        foreach ($words as $index => $word) {
            if (substr($word, -1) == 's') {
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
        foreach ($channels as $channel) {
            $xmlChannel = $xml->addChild('channel');
            $xmlChannel->addAttribute('id', $channel->getChannelNumber());
            $xmlChannel->addChild('display-name', $channel->getTitle());
            if ($iconId = $channel->getIcon()) {
                $channelIcon = File::load($iconId);
                $channelIconPath = '';
//                $channelIconPath = \Drupal::service('file_system')->realpath($channelIcon->getFileUri());
//                $channelIconPath = str_replace('/mnt/c/', 'C:\\', $channelIconPath);
//                $channelIconPath = str_replace('/', '\\', $channelIconPath);
                $channelIconPath = $this->parseFileAttachment($channelIcon->getFileUri());
                if ($channelIconPath) {
                    $xmlIcon = $xmlChannel->addChild('icon');
                    $xmlIcon->addAttribute('src', $channelIconPath);
                }
            }
        }
        // Add Programmes
        foreach ($this->getProgrammes() as $index => $programme) if ($programme->getTitle()) {
            $posterAdded = false;
            $xmlProgramme = $xml->addChild('programme');
            $xmlProgramme->addAttribute('start', date('YmdHis O', strtotime($programme->getStartTime(true))));
            $xmlProgramme->addAttribute('stop', date('YmdHis O', strtotime($programme->getEndTime(true))));
            $xmlProgramme->addAttribute('channel', $channels[$programme->getChannelNumber()]->getChannelNumber());
            if ($programme->isAMovie()) {
                $movie = new movie($programme->getMovie());
                $xmlProgramme->addChild('title', $this->parseXmlOutputText($movie->getTitle()));
                $xmlProgramme->addChild('desc', $this->parseXmlOutputText($movie->getPlot()));
                $xmlProgramme->addChild('date', $movie->getYear());
                if ($posterId = $movie->getPoster()) {
                    $poster = File::load($posterId);
                    $posterPath = '';
//                    $posterPath = \Drupal::service('file_system')->realpath($poster->getFileUri());
//                    $posterPath = str_replace('/mnt/c/', 'C:\\', $posterPath);
//                    $posterPath = str_replace('/', '\\', $posterPath);
//                    $posterPath = 'file://' . $posterPath;
                    $posterPath = $this->parseFileAttachment($poster->getFileUri());
                    if ($posterPath) {
                        $xmlIcon = $xmlProgramme->addChild('icon');
                        $xmlIcon->addAttribute('src', $posterPath);
                        $posterAdded = true;
                    }
                }
            } else {
                if ($episodeNumber = $programme->getEpisodeNumber()) {
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
                if ($programme->getRating()) {
                    $xmlRating = $xmlProgramme->addChild('rating');
                    $xmlRating->addChild('value', $programme->getRating());
                }
                $xmlProgramme->addChild('previously-shown');
                if ($programme->getSeries()) {
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
                        $posterPath = '';
//                        $posterPath = \Drupal::service('file_system')->realpath($poster->getFileUri());
//                        $posterPath = str_replace('/mnt/c/', 'C:\\', $posterPath);
//                        $posterPath = str_replace('/', '\\', $posterPath);
//                        $posterPath = 'file://' . $posterPath;
                        $posterPath = $this->parseFileAttachment($poster->getFileUri());
                        if ($posterPath) {
                            $xmlIcon = $xmlProgramme->addChild('icon');
                            $xmlIcon->addAttribute('src', $posterPath);
                            $posterAdded = true;
                        }
                        if ($programme->getEpisode()) {
                            $episode = new episode($programme->getEpisode());
                            if ($episode->getTitle() != $series->getTitle()) {
                                $xmlProgramme->addChild('sub-title', $this->parseXmlOutputText($episode->getTitle()));
                            }
                        }
                    }
                } else {
                    $xmlProgramme->addChild('title', $this->parseXmlOutputText($programme->getTitle()));
                    $xmlProgramme->addChild('desc', $this->parseXmlOutputText($programme->getDescription()));
                }
            }
            if (!$posterAdded) {
                $xmlIcon = $xmlProgramme->addChild('icon');
                $xmlIcon->addAttribute('src', 'http://epg.kiwi.nz/programme/image/' . $programme->getFilter());
            }
        }
        // Save the file
        $path = 'public://epg/xmltv.xml';
        $xml->asXML($path);
        $this->logMessage('EPG - XML file created [xmltv.xml]');
    }

    private function parseXmlOutputText($text)
    {
        return htmlspecialchars(strip_tags($text));
    }

    private function parseFileAttachment($uri)
    {
        global $base_url;
        return $base_url . file_url_transform_relative(file_create_url($uri));
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
            foreach ($nodes as $node) {
                $channels[$node->id()] = new channel(null, $node->id());
            }
            return $channels;
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
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
                    'type' => 'programme',
                    'status' => '1'
                ]);
            $programmes = [];
            foreach ($nodes as $node) {
                $programme = new programme($node);
                if (strtotime($programme->getEndTime()) > strtotime('-1 day')) {
                    $programmes[] = $programme;
                }
            }
            return $programmes;
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
        }
        return [];
    }

    public function outputProgrammerPoster()
    {
        $node = intval(\Drupal::routeMatch()->getParameter('node'));
        // You can get nid and anything else you need from the node object.
        $programmeFilter = new programmeFilter($node);
        if ($programmeFilter->id()) {
            $programmeFilter->outputPosterDefaultImage();
        }
    }
}