<?php

/*

Copyright (c) 2012, Kimo Rosenbaum and contributors
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the owner nor the names of its contributors
      may be used to endorse or promote products derived from this
      software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

/**
 * APIConsumerV2
 * @author Kimo Rosenbaum <kimor79@yahoo.com>
 * @version $Id$
 * @package APIConsumerV2
 */

class APIConsumerV2 {

	protected $curl_opts = array(
		CURLOPT_USERAGENT => 'api_consumer/2.0',
	);
	protected $headers = array();
	protected $info = array();
	protected $iheaders = array();
	protected $message = '';
	protected $options = array();
	protected $output = array();
	protected $raw_headers = array();
	protected $raw_output = '';
	protected $status = 500;

	public function __construct($options = array()) {
		while(list($option, $value) = each($options)) {
			if(substr($option, 0, 8) == 'CURLOPT_') {
				$c_opt = constant($option);
				$this->curl_opts[$c_opt] = $value;
			} else {
				$this->options[$option] = $value;
			}
		}

		if(array_key_exists('cookies', $this->options)) {
			$cookies = array();

			$keys = explode(',', $this->options['cookies']);
			foreach($keys as $key) {
				if(array_key_exists($key, $_COOKIE)) {
					$cookies[] = sprintf("%s=%s", $key,
						$_COOKIE[$key]);
				}
			}

			if(!empty($cookies)) {
				$this->curl_opts[CURLOPT_COOKIE] = implode(',',
					$cookies);
			}
		}

		if(!array_key_exists('base_url', $this->options)) {
			$this->options['base_url'] = $this->buildSelfURL();
		}

		$this->options['base_urn'] = $this->buildURN();
		$this->options['base_uri'] = $this->buildURI();
	}

	public function __deconstruct() {
	}

	/**
	 * Build a base URL pointing to one's self
	 * @return string
	 */
	protected function buildSelfURL() {
		$host = '';
		$scheme = 'http';
		$port = '';

		if(array_key_exists('HTTP_HOST', $_SERVER)) {
			$host = $_SERVER['HTTP_HOST'];
		} else {
			$host = $_SERVER['SERVER_NAME'];
		}

		if(array_key_exists('HTTPS', $_SERVER) &&
				$_SERVER['HTTPS'] === 'on') {
			$scheme .= 's';
		}

		if(array_key_exists('SERVER_PORT', $_SERVER) &&
				$_SERVER['SERVER_PORT'] != 80 &&
				$_SERVER['SERVER_PORT'] != 443) {
			$port = ':' . $_SERVER['SERVER_PORT'];
		}

		return sprintf("%s://%s%s/", $scheme, $host, $port);
	}

	protected function buildURI() {
		if(array_key_exists('base_uri', $this->options)) {
			return $this->options['base_uri'];
		}

		return sprintf("%s/%s/",
			rtrim($this->options['base_url'], '/'),
			trim($this->options['base_urn'], '/'));
	}

	protected function buildURN() {
		$base_urn = '/';

		if(array_key_exists('base_urn', $this->options)) {
			$base_urn = trim($this->options['base_urn'], '/');
		}

		if($base_urn === '') {
			$base_urn = '/';
		} else {
			$base_urn = sprintf("/%s/", $base_urn);
		}

		return $base_urn;
	}

	/**
	 * Wrapper around request() to return details field
	 * @return mixed array with details (which may be empty) or false
	 */
	public function getDetails() {
		$args = func_get_args();
		$output = call_user_func_array(array(
			$this, 'makeRequest'), $args);
		if(is_array($output)) {
			if(array_key_exists('details', $output)) {
				return $output['details'];
			}

			$this->message = 'API did not return a details field';
		}

		return false;
	}

	/**
	 * Get the given header from the response
	 * @param string $header
	 * @return mixed string or NULL
	 */
	public function getHeader($header) {
		if(array_key_exists($header, $this->headers)) {
			return $this->headers[$header];
		}

		$iheader = strtolower($header);
		if(array_key_exists($iheader, $this->iheaders)) {
			return $this->iheaders[$iheader];
		}

		return NULL;
	}

	/**
	 * Get the headers from the most recent API call
	 * @return string
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Get the curl info from the most recent API call
	 * @return string
	 */
	public function getInfo() {
		return $this->info;
	}

	/**
	 * Get the message from the most recent API call
	 * @return string
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * Get the parsed json output from the most recent API call
	 * @return array
	 */
	public function getOutput() {
		return $this->output;
	}

	/**
	 * Get the raw headers from the most recent API call
	 * @return array
	 */
	public function getRawHeaders() {
		return $this->raw_headers;
	}

	/**
	 * Get the raw output from the most recent API call
	 * @return string
	 */
	public function getRawOutput() {
		return $this->raw_output;
	}

	/**
	 * Wrapper around request() to return records field
	 * @return mixed array of records (which may be empty) or false
	 */
	public function getRecords() {
		$args = func_get_args();
		$output = call_user_func_array(array(
			$this, 'makeRequest'), $args);
		if(is_array($output)) {
			if(array_key_exists('records', $output)) {
				return $output['records'];
			}

			$this->message = 'API did not return a records field';
		}

		return false;
	}

	/**
	 * Return the status from the most recent API call
	 * @return int
	 */
	public function getStatus() {
		return (int) $this->status;
	}

	/**
	 * Make a request
	 * @param string $uri path
	 * @param array $options post, get, json_post
	 * @return mixed array on sucess, false on failure
	 */
	public function makeRequest($path, $options = array()) {
		$post = '';
		$url = sprintf("%s/%s?outputFormat=json",
			rtrim($this->options['base_uri'], '/'),
			ltrim($path, '/'));

		$this->headers = array();
		$this->iheaders = array();
		$this->message = '';
		$this->output = array();
		$this->raw_headers = array();
		$this->raw_output = '';
		$this->status = 0;

		if(array_key_exists('get', $options) &&
				is_array($options['get'])) {
			$url .= '&' . implode('&',
				$this->recursiveRawURLEncode($options['get']));
		}

		if(array_key_exists('post', $options) &&
				is_array($options['post'])) {
			$post = implode('&', $this->recursiveRawURLEncode(
				$options['post']));
		}

		if(array_key_exists('json_post', $options)) {
			$post = json_encode($options['json_post']);
		}

		$ch = curl_init();
		curl_setopt_array($ch, $this->curl_opts);
		curl_setopt_array($ch, array(
			CURLOPT_HEADERFUNCTION => array(&$this, 'readHeader'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => $url,
		));

		if($post) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}

		$j_data = curl_exec($ch);
		$this->info = curl_getinfo($ch);

		if(curl_errno($ch)) {
			$this->message = curl_errno($ch) . ': ' .
				curl_error($ch);
			$this->status = 500;
			curl_close($ch);
			return false;
		}

		if($this->info['http_code'] != 200) {
			$this->message = sprintf("API returned HTTP code: %s",
				$this->info['http_code']);
			$this->status = 500;
			curl_close($ch);
			return false;
		}

		$this->raw_output = $j_data;
		$data = json_decode($j_data, true);
		curl_close($ch);

		if(!is_array($data)) {
			$this->message = 'API returned invalid JSON';
			$this->status = 500;
			return false;
		}

		$this->output = $data;

		if(!array_key_exists('status', $data)) {
			$this->message = 'API did not return a status field';
			$this->status = 500;
			return false;
		}

		$this->message = $data['message'];
		$this->status = $data['status'];

		if($data['status'] <= 299) {
			return $data;
		}

		return false;
	}

	/**
	 * Callback for curl's HEADERFUNCTION
	 * @param object $ch
	 * @param string $header
	 * @return int
	 */
	protected function readHeader($ch, $header) {
		$this->raw_headers[] = $header;

		if(strpos($header, ': ') != 0) {
			list($key, $value) = explode(': ', $header, 2);

			$value = trim($value);
			if($value !== '') {
				$this->headers[$key] = $value;

				$ikey = strtolower($key);
				$this->iheaders[$ikey] = $value;
			}
		}

		return strlen($header);
	}

	/**
	 * Recursive rawurlencode
	 * @param array $data
	 * @return array
	 */
	protected function recursiveRawURLEncode($data = array()) {
		$output = array();

		while(list($key, $value) = each($data)) {
			if(is_array($value)) {
				foreach($value as $t_value) {
					$output[] = sprintf("%s[]=%s",
						$key, rawurlencode($t_value));
				}
			} else {
				$output[] = sprintf("%s=%s", $key,
					rawurlencode($value));
			}
		}

		return $output;
	}
}

?>
