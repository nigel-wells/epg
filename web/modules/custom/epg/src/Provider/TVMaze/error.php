<?php
namespace Drupal\epg\Provider\TVMaze;

class error
{
    private $_response_code = 0;
    private $_response = '';
    public $Request = null;

    public function __construct($data, $responseCode, Request $Request) {
        $this->_response_code = $responseCode;
        $this->Request = $Request;
        $this->_response = $data;
    }

    /**
     * @return int
     */
    public function getResponseCode() {
        return $this->_response_code;
    }

    /**
     * Log the error and send an email notification
     */
    public function triggerError() {
        // Send notification so it can be looked at
        $body = '<h1>Connection issue request/response to TVMaze server (' . $this->getResponseCode() . ')</h1>
            <p>' . $this->getErrorMessage() . '</p>
            <p><strong>End Point:</strong> ' . $this->Request->getCall() . '</p>
            <p><strong>Method:</strong> ' . $this->Request->getMethod() . '</p>
            <p><strong>Options</strong><br />' . print_r($this->Request->getOptions(), true) . '</p>
            <p><strong>Data</strong><br />' . print_r($this->Request->getData(), true) . '</p>
            <p><strong>Response</strong><br />' . htmlentities($this->_response) . '</p>
            <p><strong>Server IP:</strong> ' . $_SERVER['SERVER_ADDR'] . '</p>
            <p><strong>Date/Time:</strong> ' . date('l, jS F Y') . ' @ ' . date('g:ia') . '</p>
        ';
        \Drupal::logger('epg tvMaze')->error($body);
        // Trigger an official error as well
        $messenger = \Drupal::messenger();
        $messenger->addError('TVMaze Error: ' . $this->getErrorMessage());
    }

    /**
     * @return string
     */
    public function getErrorMessage() {
        $response = json_decode($this->_response);
        if(isset($response->error)) {
            $errorMessage = $this->Request->getCall() . ' :: ' . $response->error;
        } else {
            $errorMessage = $this->Request->getCall() . ' (' . $this->getResponseCode() . ')';
        }
        return $errorMessage;
    }
}