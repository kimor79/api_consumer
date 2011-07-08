<?php

/**

Copyright (c) 2011, Kimo Rosenbaum and contributors
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

**/

/**
 * ApiConsumer
 * @author Kimo Rosenbaum <kimor79@yahoo.com>
 * @version $Id$
 * @package ApiConsumer
 */

class ApiConsumer {

	protected $curl_opts = array(
		CURLOPT_USERAGENT => 'api_consumer/1.0',
	);
	protected $message = '';
	protected $options = array();
	protected $status = '';

	public function __construct($options = array()) {
		while(list($option, $value) = each($options)) {
			if(substr($option, 0, 8) == 'CURLOPT_') {
				$c_opt = constant($option);
				$this->curl_opts[$c_opt] = $value;
			} else {
				$this->options[$option] = $value;
			}
		}

		if(!$this->options['base_uri']) {
			$this->options['base_uri'] = 'http';

			if($_SERVER['HTTPS']) {
				$this->options['base_uri'] .= 's';
			}

			$this->options['base_uri'] .= sprintf("://%s",
				$_SERVER['SERVER_NAME']);
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
	}

	public function __deconstruct() {
	}

	/**
	 * Wrapper around request() to return details field
	 * @return mixed array with details (which may be empty) or false
	 */
	public function details() {
		$args = func_get_args();
		$output = call_user_func_array(array($this, 'request'), $args);
		if(is_array($output)) {
			if(array_key_exists('details', $output)) {
				return $output['details'];
			}

			$this->message = 'API did not return a details field';
		}

		return false;
	}

	public function message() {
		return $this->message;
	}

	/**
	 * Wrapper around request() to return records field
	 * @return mixed array of records (which may be empty) or false
	 */
	public function records() {
		$args = func_get_args();
		$output = call_user_func_array(array($this, 'request'), $args);
		if(is_array($output)) {
			if(array_key_exists('records', $output)) {
				return $output['records'];
			}

			$this->message = 'API did not return a records field';
		}

		return false;
	}

	/**
	 * Make a request
	 * @param string $uri path
	 * @param array $options post, get, options
	 * @return mixed array on sucess, false on failure
	 */
	public function request($path, $options = array()) {
		$url = sprintf("%s/%s?outputFormat=json",
			rtrim($this->options['base_uri'], '/'),
			ltrim($path, '/'));

		if(is_array($options['get'])) {
			$url .= '&' . implode('&',
				$this->_recursiveRawURLEncode($options['get']));
		}

		if(is_array($options['post'])) {
			if($options['json_post']) {
				$post = json_encode($options['post']);
			} else {
				$post = $this->_recursiveRawURLEncode(
					$options['post']);
			}
		}

		$ch = curl_init();
		curl_setopt_array($ch, $this->curl_opts);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => $url,
		));

		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

		$j_data = curl_exec($ch);

		if(curl_errno($ch)) {
			$this->message = curl_error($ch);
			$this->status = 500;
			curl_close($ch);
			return false;
		}

		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($http_code != '200') {
			$this->message = sprintf("API returned HTTP code: %s",
				$http_code);
			$this->status = 500;
			curl_close($ch);
			return false;
		}

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

		if($data['status'] != '200') {
			return false;
		}

		return $data;
	}

	public function status() {
		return $this->status;
	}

	/**
	 * Recursive rawurlencode
	 * @param array $data
	 * @return array
	 */
	protected function _recursiveRawURLEncode($data = array()) {
		$output = array();

		while(list($key, $value) = each($data)) {
			if(is_array($value)) {
				foreach($value as $t_value) {
					$output[] = sprintf("%s[]=%s",
						$key, rawurlencode($t_value));
				}
			} else {
				$data[] = sprintf("%s=%s", $key,
					rawurlencode($value));
			}
		}

		return $data;
	}
}

?>
