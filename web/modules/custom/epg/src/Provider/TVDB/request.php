<?php
namespace Drupal\epg\Provider\TVDB;

class request
{
    private $_call = '';
    private $_method = 'GET';
    private $_options = [];
    private $_data = [];
    private $_cachable = false;
    private $_cache_expiry = 0;
    private $_page = 1;
    private $_time = 0;

    function __construct($call, $method = '') {
        $this->setCall($call);
        $this->setMethod($method);
    }

    /**
     * @return string
     */
    public function getMethod() {
        return $this->_method;
    }

    /**
     * @param string $method
     */
    public function setMethod($method) {
        $method = strtoupper($method);
        $validMethods = [
            'GET',
            'POST',
            'PATCH',
        ];
        if(in_array($method, $validMethods)) {
            $this->_method = $method;
        }
    }

    /**
     * @return string
     */
    public function getCall() {
        return $this->_call;
    }

    /**
     * @param string $call
     */
    private function setCall($call) {
        $this->_call = $call;
    }

    /**
     * @return bool
     */
    public function isCachable() {
        return $this->_cachable;
    }

    /**
     * @param bool $cachable
     */
    private function setCachable($cachable) {
        $this->_cachable = $cachable;
    }

    /**
     * @return array
     */
    public function getOptions() {
        $options = $this->_options;
        // Add in the page parameter if going past page one
        if($this->getPage() > 1) {
            $options['page'] = $this->getPage();
        }
        return $options;
    }

    /**
     * @param array $options
     */
    public function setOptions($options) {
        $this->_options = $options;
    }

    /**
     * @return array
     */
    public function getData() {
        $data = '';
        if(is_array($this->_data)) {
            $data = json_encode($this->_data);
        }
        return $data;
    }

    /**
     * @param array|string $data
     */
    public function setData($data) {
        $this->_data = $data;
    }

    /**
     * @return int
     */
    public function getCacheExpiry() {
        return $this->_cache_expiry;
    }

    /**
     * @param string $cache_expiry
     */
    public function setCacheExpiry($cache_expiry = '1 day') {
        $expiry = strtotime('+' . $cache_expiry) - time();
        $this->_cache_expiry = $expiry;
        $this->setCachable(true);
    }

    /**
     * @return string
     */
    public function getCacheFilename() {
        $tvdb = new tvdb();
        $cacheFilename = strtolower(str_replace('/', '.', $this->getCall()));
        // Add any options
        foreach($this->getOptions() as $key => $val) if($key != 'page') {
            $cacheFilename .= '-' . str_replace( '-', '_', $this->sanitizeKey($key)) . '.' . str_replace( '-', '_', $this->sanitizeKey($val));
        }
        $cacheFilename .= '-' . strtolower($this->getMethod()) . '-v' . $tvdb->getApiVersion() . '.json';
        return $cacheFilename;
    }

    public function nextPage() {
        $this->setPage($this->getPage() + 1);
    }

    /**
     * @return int
     */
    public function getPage() {
        return $this->_page;
    }

    /**
     * @param int $page
     */
    private function setPage($page) {
        $this->_page = $page;
    }

    /**
     * @param bool $format
     * @return int
     */
    public function getTime($format = false) {
        $time = $this->_time;
        if($format) {
            $time = date('i:s', $time);
        }
        return $time;
    }

    /**
     * @param int $time
     */
    public function setTime($time) {
        $this->_time = $time;
    }

    private function sanitizeKey($slug, $minCharacters = null) {
        // Trim it up
        $slug = trim($slug);
        // Turn spaces into dashes
        $slug = str_replace(' ', '-', $slug);
        $slug = str_replace('_', '-', $slug);
        // Make sure there is no trailing dash
        if(substr($slug, -1) == '-') {
            $slug = substr($slug, 0, -1);
        }
        // Strip out funny characters
        $slug = preg_replace('/[^\-a-zA-Z0-9]/', '', $slug);
        // Remove any multiple dashes
        while(strpos($slug, '--') !== false) {
            $slug = str_replace('--', '-', $slug);
        }
        // Make it lowercase
        $slug = strtolower($slug);
        // After its been formatted correctly it really just needs to be at least 8 characters long
        if($minCharacters !== null && strlen($slug) < $minCharacters) {
            $slug = false;
        }
        // Return the updated version
        return $slug;
    }
}