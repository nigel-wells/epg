<?php
namespace Drupal\epg\Provider\TVDB;

define('TVDB_DEBUG', true);

class tvdb
{
    private $_api_key = '';
    private $_api_url = '';
    private $_api_version = '';
    private $_username = '';
    private $_user_key = '';
    private $_access_token = '';
    private $_refresh_token = '';
    private $_force_cache = false;
    private $_force_cache_refresh = false;

    function __construct() {
        // Set variables
        $this->setApiVersion('2.1.2');
        $this->setApiUrl('https://api.thetvdb.com/');
        $this->setApiKey('BEOQ5XJ2YCFHPORK');
        $this->setUsername('nigel-wellsuba');
        $this->setUserKey('ABH5REOK7RCVVKD2');
        // Currently using Postman to get this
        $this->setRefreshToken('eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE1MjcwNjYxMjAsImlkIjoiRVBHIFVwZGF0ZSIsIm9yaWdfaWF0IjoxNTI2OTc5NzIwLCJ1c2VyaWQiOjUwNjcxMSwidXNlcm5hbWUiOiJuaWdlbC13ZWxsc3ViYSJ9.P476crz-a5j9hnS4kCqyEVft9nj_wQbo4jXsxqWVRr8PsBJJ4H-5nFtCHZE4dI6eMXo2yDAYUnkOL4CiPaz_oG266AE9ayhOyKiLteKGmM6He7HoEhX5_GRieLloV-aEKsdUYycIjsaJLmy-kYeNrVLwPa1tBUEqEUxuUVP1himp193GqdE-04HFNUxjGhyeRbCOMC80Tmbu1LAOAxB0hxg954bKugINhs88TrhM_6r-P9fed6RIZVSQ586J-nbqgOeV_tSPRYyzCIjwAQ9PglucO6TyHo3J0i549YIzXtooQnKJVES1_l2BVw9cUWJzYmqFtyQsLFwxc2fTR2Qzyg');
        // Check token to use
        $this->checkToken();
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
     * @return string
     */
    public function getUsername()
    {
        return $this->_username;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->_username = $username;
    }

    /**
     * @return string
     */
    public function getUserKey()
    {
        return $this->_user_key;
    }

    /**
     * @param string $user_key
     */
    public function setUserKey($user_key)
    {
        $this->_user_key = $user_key;
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

    private function checkToken() {
        $oAuthPath = $this->getCacheDir() . 'oAuth.json';
        $validToken = false;
        if (file_exists($oAuthPath)) {
            $response = json_decode(file_get_contents($oAuthPath));
            if ($response) {
                $this->setAccessToken($response->token);
                $validToken = true;
            }
        }
        // Refresh the token if needed
        if (!$validToken) {
            $this->refreshToken();
        }
    }

    /**
     * @param string $refresh_token
     */
    private function setRefreshToken($refresh_token) {
        $this->_refresh_token = $refresh_token;
    }

    private function refreshToken() {
        $oAuthPath = $this->getCacheDir() . 'oAuth.json';
        $this->setAccessToken('');
        $request = new request('login', 'POST');
        $request->setData([
            'apikey' => $this->getApiKey(),
            'userkey' => $this->getUserKey(),
            'username' => $this->getUsername()
        ]);
        $response = $this->request($request);
        // Save the response and set the access token
        if (!$this->isResponseError($response)) {
            // Save this response
            if (file_exists($oAuthPath)) {
                unlink($oAuthPath);
            }
            file_put_contents($oAuthPath, json_encode($response));
            $this->setAccessToken($response->token);
        } else {
            trigger_error( 'TVDB failed to refresh token', E_USER_ERROR );
            die();
        }
    }

    /**
     * @param mixed $response
     * @return bool
     */
    public function isResponseError($response) {
        if (is_object($response) && get_class($response) == 'Guide\Error') {
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    private function getAccessToken() {
        return $this->_access_token;
    }

    /**
     * @param string $access_token
     */
    private function setAccessToken($access_token) {
        $this->_access_token = $access_token;
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
        if($this->getAccessToken()) {
            $headers[] = 'Authorization: Bearer ' . $this->getAccessToken();
        }
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
        } elseif($headerResponse == 401 && $call !== 'login') {
            $this->logrequest($request, "[" . $headerResponse . "]\n" . $response);
            $this->refreshToken();
        } else {
            // Log the response
            $this->logrequest($request, "[ERROR - " . $headerResponse . "]\n" . $response);
            $Error = new Error($response, $headerResponse, $request);
            // Don't need to worry about 404 errors as it just means nothing was found and isn't really an error
            if($headerResponse !== 404) {
                $Error->triggerError();
            }
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
            trigger_error('TVDB cache directory does not exist', E_USER_WARNING);
        } elseif (!is_writable($cacheDir)) {
            trigger_error('TVDB cache directory is not writable: <strong>' . $cacheDir . '</strong>', E_USER_WARNING);
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
        if (is_object($response) && get_class($response) == 'Drupal\epg\Provider\TVDB\error') {
            return false;
        }
        return true;
    }

    /**
     * @param $title string
     * @return Series[]
     */
    public function searchForSeries($title) {
        $request = new request('search/series');
        $request->setCacheExpiry();
        $request->setOptions(['name' => $title]);
        $response = $this->request($request);
        $series = [];
        if($this->isValidResponse($response)) {
            foreach($response->data as $TVDBSeries) {
                $series[] = new series($TVDBSeries);
            }
        }
        return $series;
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


    public function getSeries($seriesId)
    {
        $request = new request('series/' . $seriesId);
        $request->setCacheExpiry('1 month');
        $response = $this->request($request);
        if($this->isValidResponse($response)) {
            return new series($response->data);
        }
        return false;
    }

    public function getSeriesPoster($seriesId)
    {
        $request = new request('series/' . $seriesId . '/images/query');
        $request->setOptions(['keyType' => 'poster']);
        $request->setCacheExpiry('6 months');
        $response = $this->request($request);
        if($this->isValidResponse($response)) {
            return new image($response->data[0]);
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