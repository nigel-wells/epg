<?php
namespace Drupal\epg\Provider\TVMaze;

define('TVMAZE_DEBUG', true);

class tvMaze
{
    private $_api_url = '';
    private $_api_version = '';
    private $_force_cache = false;
    private $_force_cache_refresh = false;

    function __construct() {
        // Set variables
        $this->setApiVersion('1.0');
        $this->setApiUrl('http://api.tvmaze.com/');
    }

    /**
     * @param string $api_url
     */
    private function setApiUrl($api_url) {
        $this->_api_url = $api_url;
    }

    /**
     * @return string
     */
    public function getApiUrl()
    {
        return $this->_api_url;
    }

    /**
     * @return string
     */
    public function getApiVersion() {
        return $this->_api_version;
    }

    /**
     * @param string $api_version
     */
    private function setApiVersion($api_version) {
        $this->_api_version = $api_version;
    }

    /**
     * @return bool
     */
    public function isForceCache() {
        return $this->_force_cache;
    }

    /**
     * @param bool $force_cache
     */
    public function setForceCache( $force_cache ) {
        $this->_force_cache = $force_cache;
    }

    private function getCacheDir()
    {
        return 'public://provider/cache/';
    }

    /**
     * @return bool
     */
    public function isForceCacheRefresh() {
        return $this->_force_cache_refresh;
    }

    /**
     * @param bool $force_cache_refresh
     */
    public function setForceCacheRefresh( $force_cache_refresh ) {
        $this->_force_cache_refresh = $force_cache_refresh;
    }

    /**
     * @param mixed $response
     * @return bool
     */
    public function isResponseError($response) {
        if (is_object($response) && get_class($response) == 'Drupal\epg\Provider\TVMaze\error') {
            return true;
        }
        return false;
    }

    /**
     * @param request $request
     * @return Error|\stdClass
     */
    private function request(request $request) {
        // Check if we can use cache
        if ($request->getPage() == 1 && $request->isCachable() && !$this->isForceCacheRefresh()) {
            $response = $this->getCacheResponse($request);
            // Return it if we found something
            if ($response !== false) {
                $this->logrequest($request, '', true);
                return $response;
            }
        }
        // Prepare data for submission
        $call = $request->getCall();
        $method = $request->getMethod();
        $data = $request->getData();
        $opts = $request->getOptions();

        // Setup curl request
        $channel = curl_init();

        curl_setopt($channel, CURLOPT_URL,
            $this->getApiUrl() . $call . (count($opts) ? '?' . http_build_query($opts) : '')
        );
        curl_setopt($channel, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($channel, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($channel, CURLOPT_SSL_VERIFYPEER, false);
        if ($method == 'POST') {
            curl_setopt($channel, CURLOPT_POST, true);
            curl_setopt($channel, CURLOPT_POSTFIELDS, $data);
        } elseif ($method == 'PATCH') {
            curl_setopt($channel, CURLOPT_CUSTOMREQUEST, $method);
        }
        $headers = [
            0 => 'Content-type: application/json'
        ];
        curl_setopt($channel, CURLOPT_HTTPHEADER, $headers);
        $timerBegin = microtime(true);
        $response = curl_exec($channel);
        $header = curl_getinfo($channel);
        curl_close($channel);
        $timerEnd = microtime(true);
        $request->setTime($timerEnd - $timerBegin);
        // Decode the response before returning it
        $headerResponse = intval($header['http_code']);
        if ($headerResponse >= 200 && $headerResponse < 300) {
            // Log the response
            if (TVDB_DEBUG) {
                $this->logrequest($request, "[" . $headerResponse . "]\n" . $response);
            } else {
                $this->logrequest($request);
            }
            // Cache the response if we can
            if ($request->getPage() == 1 && $request->isCachable()) {
                $this->cacheResponse($request, $response);
            }
            $response = json_decode($response);
            // Check if there are more pages and fetch them if there are
            if (isset($response->links)) {
                if (isset($response->links->next) && intval($response->links->next) > 0) {
                    $request->nextPage();
                    $responseNextPage = $this->request($request);
                    // Combine the original response and the next page response
                    $jsonNextPage = json_decode(json_encode($responseNextPage), true);
                    $jsonOriginal = json_decode(json_encode($response), true);
                    $data = array_merge($jsonOriginal['data'], $jsonNextPage['data']);
                    $jsonOriginal['data'] = $data;
                    $response = json_decode(json_encode($jsonOriginal));
                    // Cache the response again with the newly added page data
                    if ($request->isCachable()) {
                        $this->cacheResponse($request, json_encode($response));
                    }
                }
            }
            return $response;
        } elseif($headerResponse == 429) {
            $this->logrequest($request, "[" . $headerResponse . "]\n" . $response);
            sleep(10);
            $this->request($request);
        } else {
            // Log the response
            $this->logrequest($request, "[ERROR - " . $headerResponse . "]\n" . $response);
            $Error = new error($response, $headerResponse, $request);
            $Error->triggerError();
            return $Error;
        }
    }

    /**
     * @param request $request
     * @param string $response
     * @param bool $fromCache
     */
    private function logrequest(request $request, $response = '', $fromCache = false) {
        $message = date('c') . ' - ' . $request->getCall() . ' (' . $request->getMethod() . ')';
        if ($fromCache) {
            $message .= ' [Cached: ' . $request->getCacheFilename() . ']';
        } else {
            $message .= ' [' . $request->getTime(true) . ']';
        }
        $message .= "\n";
        if(TVDB_DEBUG && !$fromCache && $response) {
            $debugLogFile = $this->getCacheDir() . 'requests.debug.log';
            $data = print_r($request->getData(), true);
            if(!empty($data) && $data != '[]') {
                $message .= "[DATA]\n" . print_r($data, true) . "\n";
            }
        } else {
            $debugLogFile = $this->getCacheDir() . 'requests.' . date('Ym') . '.log';
        }
        if($response) $message .= "[RESPONSE <" . $this->_api_url . ">]\n" . $response . "\n";
        error_log($message, 3, $debugLogFile);
    }

    /**
     * @param request $request
     * @param $response
     */
    private function cacheResponse(request $request, $response) {
        $cacheDir = $this->getCacheDir();
        if (!is_dir($cacheDir)) {
            trigger_error('TVMaze cache directory does not exist', E_USER_WARNING);
        } elseif (!is_writable($cacheDir)) {
            trigger_error('TVMaze cache directory is not writable: <strong>' . $cacheDir . '</strong>', E_USER_WARNING);
        } else {
            $cacheFilePath = $cacheDir . $request->getCacheFilename();
            if (file_exists($cacheFilePath)) {
                unlink($cacheFilePath);
            }
            file_put_contents($cacheFilePath, $response);
        }
    }

    /**
     * @param request $request
     * @return bool|\stdClass
     */
    private function getCacheResponse(request $request) {
        $cacheFilePath = $this->getCacheDir() . $request->getCacheFilename();
        if (file_exists($cacheFilePath)) {
            // Use the request expiry value if caching is enabled otherwise set to 1 hour to cover the cron task
            $expiryTTL = $request->getCacheExpiry();
            // Check to see if it is recent enough
            if (filemtime($cacheFilePath) + $expiryTTL > time()) {
                $json = json_decode(file_get_contents($cacheFilePath));
                if ($json) {
                    return $json;
                }
            }
        }
        return false;
    }

    /**
     * @param mixed $response
     * @return bool
     */
    public function isValidResponse($response) {
        if (is_object($response) && get_class($response) == 'Drupal\epg\Provider\TVMaze\error') {
            return false;
        }
        return true;
    }

    /**
     * @param $title string
     * @return Series[]
     */
    public function searchForSeries($title) {
        $request = new request('search/shows');
        $request->setCacheExpiry();
        $request->setOptions(['q' => $title]);
        $response = $this->request($request);
        $series = [];
        if($this->isValidResponse($response)) {
            foreach($response as $TVMazeSeries) {
                $series[] = new series($TVMazeSeries->show);
            }
        }
        return $series;
    }

    public function getSeries($seriesId)
    {
        $request = new request('shows/' . $seriesId);
        $request->setCacheExpiry('1 month');
        $response = $this->request($request);
        if($this->isValidResponse($response)) {
            return new series($response);
        }
        return false;
    }

    /**
     * @param $seriesId
     * @return Episode[]
     */
    public function getSeriesEpisodes($seriesId) {
        $request = new request('series/' . $seriesId . '/episodes');
        $request->setCacheExpiry('1 week');
        $response = $this->request($request);
        $episodes = [];
        if($this->isValidResponse($response)) {
            foreach($response->data as $TVDBEpisode) {
                $episodes[] = new episode($TVDBEpisode);
            }
        }
        return $episodes;
    }

}