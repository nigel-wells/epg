<?php
namespace Drupal\epg\Provider\OMDB;

define('OMDB_DEBUG', true);

class omDb
{
    private $_api_key = '';
    private $_api_url = '';
    private $_api_version = '';
    private $_force_cache = false;
    private $_force_cache_refresh = false;

    function __construct() {
        // Set variables
        $this->setApiVersion('1.0');
        $this->setApiUrl('http://www.omdbapi.com/');
        $this->setApiKey('18cf7549');
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
     * @return string
     */
    private function getApiUrl() {
        return $this->_api_url;
    }

    /**
     * @param string $api_key
     */
    private function setApiKey($api_key) {
        $this->_api_key = $api_key;
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
     * @param mixed $response
     * @return bool
     */
    public function isResponseError($response) {
        if (is_object($response) && get_class($response) == 'Drupal\epg\Provider\OMDB\error') {
            return true;
        }
        return false;
    }

    /**
     * @param request $request
     * @return error|\stdClass
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

        // Always add the API key to the options
        $opts = array_merge(['apikey' => $this->getApiKey()], $opts);

        // Setup curl request
        $channel = curl_init();

        curl_setopt($channel, CURLOPT_URL,
            $this->getApiUrl() . $call . (count($opts) ? '?' . http_build_query($opts) : '')
        );
        curl_setopt($channel, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($channel, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($channel, CURLOPT_SSL_VERIFYPEER, false);
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
            if (OMDB_DEBUG) {
                $this->logrequest($request, "[" . $headerResponse . "]\n" . $response);
            } else {
                $this->logrequest($request);
            }
            // Cache the response if we can
            if ($request->getPage() == 1 && $request->isCachable()) {
                $this->cacheResponse($request, $response);
            }
            $response = json_decode($response);
            return $response;
        } else {
            // Log the response
            $this->logrequest($request, "[ERROR - " . $headerResponse . "]\n" . $response);
            $Error = new error($response, $headerResponse, $request);
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
        if(OMDB_DEBUG && !$fromCache && $response) {
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
            trigger_error('OMDB cache directory does not exist', E_USER_WARNING);
        } elseif (!is_writable($cacheDir)) {
            trigger_error('OMDB cache directory is not writable: <strong>' . $cacheDir . '</strong>', E_USER_WARNING);
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
        if (is_object($response) && get_class($response) == 'Drupal\epg\Provider\OMDB\error') {
            return false;
        }
        return true;
    }

    /**
     * @param $title string
     * @return bool|movie
     */
    public function searchForMovie($title) {
        $request = new request();
        $request->setCacheExpiry();
        $request->setOptions(['s' => $title]);
        $response = $this->request($request);
        if($this->isValidResponse($response)) {
            if(isset($response->Response) && $response->Response == 'True') {
                $movie = new movie($response);
                if($movie->isMovie()) {
                    return $movie;
                }
            }
        }
        return false;
    }

    /**
     * @param $id
     * @return bool|movie
     */
    public function getMovie($id)
    {
        $request = new request();
        $request->setCacheExpiry();
        $request->setOptions(['i' => $id]);
        $response = $this->request($request);
        if($this->isValidResponse($response)) {
            if(isset($response->Response) && $response->Response == 'True') {
                $movie = new movie($response);
                if($movie->isMovie()) {
                    return $movie;
                }
            }
        }
        return false;
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
     * @return string
     */
    public function getApiKey()
    {
        return $this->_api_key;
    }
}