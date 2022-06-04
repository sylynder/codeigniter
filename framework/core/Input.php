<?php

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2019, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright	Copyright (c) 2014 - 2019, British Columbia Institute of Technology (https://bcit.ca/)
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 * @since	Version 1.0.0
 * @filesource
 */
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Input Class
 *
 * Pre-processes global input data for security
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Input
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/userguide3/libraries/input.html
 */
class CI_Input
{

	/**
	 * IP address of the current user
	 *
	 * @var	string
	 */
	protected $ip_address = false;

	/**
	 * Allow GET array flag
	 *
	 * If set to false, then $_GET will be set to an empty array.
	 *
	 * @var	bool
	 */
	protected $_allow_get_array = true;

	/**
	 * Standardize new lines flag
	 *
	 * If set to true, then newlines are standardized.
	 *
	 * @var	bool
	 */
	protected $_standardize_newlines;

	/**
	 * Enable XSS flag
	 *
	 * Determines whether the XSS filter is always active when
	 * GET, POST or COOKIE data is encountered.
	 * Set automatically based on config setting.
	 *
	 * @var	bool
	 */
	protected $_enable_xss = false;

	/**
	 * Enable CSRF flag
	 *
	 * Enables a CSRF cookie token to be set.
	 * Set automatically based on config setting.
	 *
	 * @var	bool
	 */
	protected $_enable_csrf = false;

	/**
	 * List of all HTTP request headers
	 *
	 * @var array
	 */
	protected $headers = [];

	/**
	 * Raw input stream data
	 *
	 * Holds a cache of php://input contents
	 *
	 * @var	string
	 */
	protected $_raw_input_stream;

	/**
	 * Parsed input stream data
	 *
	 * Parsed from php://input at runtime
	 *
	 * @see	CI_Input::input_stream()
	 * @var	array
	 */
	protected $_input_stream;

	protected $security;
	protected $uni;

	/**
	 * File Upload variables
	 *
	 * @var
	 */
	protected $tempfile;
	protected $error;
	protected $filepath;
	protected $filesize;
	protected $extension;
	protected $originalName;
	protected $originalMimeType;
	protected $givenName;
	protected $rawname;
	protected $isUploadedFile = false;



	// --------------------------------------------------------------------

	/**
	 * Class constructor
	 *
	 * Determines whether to globally enable the XSS processing
	 * and whether to allow the $_GET array.
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->_allow_get_array		= (config_item('allow_get_array') !== false);
		$this->_enable_xss		= (config_item('global_xss_filtering') === true);
		$this->_enable_csrf		= (config_item('csrf_protection') === true);
		$this->_standardize_newlines	= (bool) config_item('standardize_newlines');

		$this->security = &load_class('Security', 'core');

		// Do we need the UTF-8 class?
		if (UTF8_ENABLED === true) {
			$this->uni = &load_class('Utf8', 'core');
		}

		// Sanitize global arrays
		$this->_sanitize_globals();

		// CSRF Protection check
		if ($this->_enable_csrf === true && !is_cli()) {
			$this->security->csrf_verify();
		}

		log_message('info', 'Input Class Initialized');
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch from array
	 *
	 * Internal method used to retrieve values from global arrays.
	 *
	 * @param	array	&$array		$_GET, $_POST, $_COOKIE, $_SERVER, etc.
	 * @param	mixed	$index		Index for item to be fetched from $array
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	protected function _fetch_from_array(&$array, $index = null, $xss_clean = false)
	{
		is_bool($xss_clean) or $xss_clean = $this->_enable_xss;

		// If $index is null, it means that the whole $array is requested
		isset($index) or $index = array_keys($array);

		// allow fetching multiple keys at once
		if (is_array($index)) {
			$output = [];
			foreach ($index as $key) {
				$output[$key] = $this->_fetch_from_array($array, $key, $xss_clean);
			}

			return $output;
		}

		if (isset($array[$index])) {
			$value = $array[$index];
		} elseif (($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $index, $matches)) > 1) // Does the index contain array notation
		{
			$value = $array;
			for ($i = 0; $i < $count; $i++) {
				$key = trim($matches[0][$i], '[]');
				if ($key === '') // Empty notation will return the value as array
				{
					break;
				}

				if (isset($value[$key])) {
					$value = $value[$key];
				} else {
					return null;
				}
			}
		} else {
			return null;
		}

		return ($xss_clean === true)
			? $this->security->xss_clean($value)
			: $value;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch an item from the GET array
	 *
	 * @param	mixed	$index		Index for item to be fetched from $_GET
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	public function get($index = null, $xss_clean = false)
	{
		return $this->_fetch_from_array($_GET, $index, $xss_clean);
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch an item from the POST array
	 *
	 * @param	mixed	$index		Index for item to be fetched from $_POST
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	public function post($index = null, $xss_clean = false)
	{
		return $this->_fetch_from_array($_POST, $index, $xss_clean);
	}

	/**
	 * Verify if an item from the POST array exists
	 *
	 * @param	mixed	$index		Index for item to be checked from $_POST
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	bool
	 */
	public function has($index = null, $xss_clean = false)
	{
		$exists = $this->_fetch_from_array($_POST, $index, $xss_clean);

		if ($exists) {
			return true;
		}

		return false;
	}

	/**
	 * Fetch only items from the POST array
	 *
	 * @param	mixed	$indexes		Indexes for item to be fetched from $_POST
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	public function only(array $indexes = [], $xss_clean = false)
	{
		return $this->_fetch_from_array($_POST, $indexes, $xss_clean);
	}

	/**
	 * Fetch all except items given form the POST array
	 *
	 * @param array $indexes
	 * @param bool $xss_clean
	 * @return void
	 */
	public function except(array $indexes = [], $xss_clean = false)
	{
		$post = array_diff_key($_POST, array_flip($indexes));

		return $this->_fetch_from_array($post, null, $xss_clean);
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch an item from POST data with fallback to GET
	 *
	 * @param	string	$index		Index for item to be fetched from $_POST or $_GET
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	public function post_get($index, $xss_clean = false)
	{
		return isset($_POST[$index])
			? $this->post($index, $xss_clean)
			: $this->get($index, $xss_clean);
	}

	/**
	 * Alias To Method Above
	 *
	 * @param string $index
	 * @param bool $xss_clean
	 * @return mixed
	 */
	public function postGet($index, $xss_clean = false)
	{
		return $this->post_get($index, $xss_clean);
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch an item from GET data with fallback to POST
	 *
	 * @param	string	$index		Index for item to be fetched from $_GET or $_POST
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	public function get_post($index, $xss_clean = false)
	{
		return isset($_GET[$index])
			? $this->get($index, $xss_clean)
			: $this->post($index, $xss_clean);
	}

	/**
	 * Alias To Method Above
	 *
	 * @param string $index
	 * @param bool $xss_clean
	 * @return mixed
	 */
	public function getPost($index, $xss_clean = false)
	{
		return $this->get_post($index, $xss_clean);
	}

	/**
	 * Get single current uploaded file using $_FILES
	 * through Http Post
	 * @param string $index
	 * @return array|string
	 */
	public function file($index = '')
	{
		if (isset($_FILES[$index])) {
			return $_FILES[$index];
		}

		return [];
	}

	/**
	 * Get all current uploaded files using $_FILES
	 * through Http Post 
	 *
	 * @return array
	 */
	public function files()
	{
		return $_FILES;
	}

	/**
	 * Check if file to upload is not empty
	 *
	 * @param string $file
	 * @return bool
	 */
	public function hasFile($file = '')
	{
		
		if ($file !== '' && isset($_FILES[$file])) {
			$file = $_FILES[$file];
		}

		return (empty($file['name']))
			? false
			: true;
	}

	/**
	 * Is this file uploaded with a POST request?
	 *
	 * hard dependency on the `is_uploaded_file` function.
	 *
	 * @return bool
	 */
	public function isUploadedFile($fieldname)
	{
		$file =  $_FILES[$fieldname];
		return is_uploaded_file($file['tmp_name']);
	}

	/**
	 * Verify uploaded file is true
	 *
	 * @return bool
	 */
	public function isValid()
	{
		return is_uploaded_file($this->tempfile) ? true : false;
	}

	/**
	 * Retrieve all file data for easy manipulation
	 *
	 * @param array $file
	 * @param string $name
	 * @param string $path
	 * @return CI_Input
	 */
	public function filedata($file = [], $name = null, $path = '')
	{
		if (empty($file)) {
			return '';
		}

		$this->tempfile = $file['tmp_name'];
		$this->error = $file['error'];
		$this->filepath = ($path) ? realpath($path) : realpath(WRITABLEPATH . 'uploads');
		$this->extension = pathinfo($file['name'], PATHINFO_EXTENSION);
		$this->originalName = $file['name'];
		$this->originalMimeType = $file['type'];
		$this->filesize = $file['size'];
		$this->rawname = substr($this->originalName, 0, strrpos($this->originalName, '.') - strlen($this->extension));

		$filename = '';

		if ($name !== null) {
			$filename =  $name . '.' . $this->extension;
		}

		if ($name === null) {
			$filename =  random_bytes(2) . str_shuffle('file') . random_bytes(16);
			$filename = bin2hex($filename) . '.' . $this->extension;
		}

		$this->givenName = $filename;

		return $this;
	}

	/**
	 * Upload file to a given destination
	 *
	 * @param mixed $file
	 * @param string $path
	 * @param string $name
	 * @return CI_Input
	 */
	public function upload($file = [], $path = '', $name = null)
	{
		if (empty($file)) {
			return '';
		}

		$this->tempfile = $file['tmp_name'];
		$this->filepath = ($path) ? realpath($path) : realpath(WRITABLEPATH.'uploads');
		$this->extension = pathinfo($file['name'], PATHINFO_EXTENSION);
		$this->originalName = $file['name'];
		$this->originalMimeType = $file['type'];
		$this->filesize = $file['size'];
		$this->rawname = substr($this->originalName, 0, strrpos($this->originalName, DOT)-strlen($this->extension));

		$filename = '';

		if ($name !== null) {
			$filename =  $name . '.' . $this->extension;
		}

		if ($name === null) {
			$filename =  random_bytes(2) . str_shuffle('file') . random_bytes(16);
			$filename = bin2hex($filename) . '.' . $this->extension;
		}

		$this->givenName = $filename;

		$this->move($this->filepath, $this->givenName);

		return $this;
	}

	/**
	 * Move file from location to destination
	 *
	 * @param string $filepath
	 * @param string $filename
	 * @return bool
	 */
	public function move($filepath, $filename)
	{
		$targetfile = $filepath . DIRECTORY_SEPARATOR . $filename;

		if (move_uploaded_file($this->tempfile, $targetfile)) {
			$this->isUploadedFile = true;
			return true;
		}

		return false;
	}

	/**
	 * Upload file size
	 *
	 * @return string
	 */
	public function size()
	{
		return $this->filesize;
	}

	/**
	 * Upload file path
	 *
	 * @return string
	 */
	public function path()
	{
		return $this->filepath;
	}

	/**
	 * Upload file extension
	 *
	 * @return string
	 */
	public function extension()
	{
		return $this->extension;
	}

	/**
	 * Upload file original name
	 *
	 * @return string
	 */
	public function originalName()
	{
		return $this->originalName;
	}

	/**
	 * Upload file given name
	 *
	 * @return string
	 */
	public function filename()
	{
		return $this->givenName;
	}

	/**
	 * Upload file rawname
	 *
	 * @return string
	 */
	public function rawname()
	{
		return $this->rawname;
	}

	/**
	 * Upload file mime type
	 *
	 * @return string
	 */
	public function mimetype()
	{
		return $this->originalMimeType;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch an item from the COOKIE array
	 *
	 * @param	mixed	$index		Index for item to be fetched from $_COOKIE
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	public function cookie($index = null, $xss_clean = false)
	{
		return $this->_fetch_from_array($_COOKIE, $index, $xss_clean);
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch an item from the SERVER array
	 *
	 * @param	mixed	$index		Index for item to be fetched from $_SERVER
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	public function server($index, $xss_clean = false)
	{
		return $this->_fetch_from_array($_SERVER, $index, $xss_clean);
	}

	// ------------------------------------------------------------------------

	/**
	 * Fetch an item from the php://input stream
	 *
	 * Useful when you need to access PUT, DELETE or PATCH request data.
	 *
	 * @param	string	$index		Index for item to be fetched
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	public function input_stream($index = null, $xss_clean = false)
	{
		// Prior to PHP 5.6, the input stream can only be read once,
		// so we'll need to check if we have already done that first.
		if (!is_array($this->_input_stream)) {
			// $this->raw_input_stream will trigger __get().
			parse_str($this->raw_input_stream, $this->_input_stream);
			is_array($this->_input_stream) or $this->_input_stream = [];
		}

		return $this->_fetch_from_array($this->_input_stream, $index, $xss_clean);
	}

	/**
	 * Alias To Method Above
	 *
	 * @param	string	$index		Index for item to be fetched
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	public function inputStream($index = null, $xss_clean = false)
	{
		return $this->input_stream($index, $xss_clean);
	}

	// ------------------------------------------------------------------------

	/**
	 * Set cookie
	 *
	 * Accepts an arbitrary number of parameters (up to 7) or an associative
	 * array in the first parameter containing all the values.
	 *
	 * @param   string|mixed[]  $name   Cookie name or an array containing parameters
	 * @param   string      $value      Cookie value
	 * @param   int         $expire     Cookie expiration time in seconds
	 * @param   string      $domain     Cookie domain (e.g.: '.yourdomain.com')
	 * @param   string      $path       Cookie path (default: '/')
	 * @param   string      $prefix     Cookie name prefix
	 * @param   bool        $secure     Whether to only transfer cookies via SSL
	 * @param   bool        $httponly   Whether to only makes the cookie accessible via HTTP (no javascript)
	 * @param   string|null $samesite   The SameSite cookie setting (Possible values: 'Lax', 'Strict', 'None', null, default: null)
	 * @return  void
	 */
	public function set_cookie($name, $value = '', $expire = 0, $domain = '', $path = '/', $prefix = '', $secure = null, $httponly = null, $samesite = null)
	{
		if (is_array($name)) {
			// always leave 'name' in last place, as the loop will break otherwise, due to $$item
			foreach (['value', 'expire', 'domain', 'path', 'prefix', 'secure', 'httponly', 'samesite', 'name'] as $item) {
				if (isset($name[$item])) {
					$$item = $name[$item];
				}
			}
		}

		if ($prefix === '' && config_item('cookie_prefix') !== '') {
			$prefix = config_item('cookie_prefix');
		}

		if ($domain == '' && config_item('cookie_domain') != '') {
			$domain = config_item('cookie_domain');
		}

		if ($path === '/' && config_item('cookie_path') !== '/') {
			$path = config_item('cookie_path');
		}

		$secure = ($secure === null && config_item('cookie_secure') !== null)
			? (bool) config_item('cookie_secure')
			: (bool) $secure;

		$httponly = ($httponly === null && config_item('cookie_httponly') !== null)
			? (bool) config_item('cookie_httponly')
			: (bool) $httponly;

		if (!is_numeric($expire)) {
			$expire = time() - 86500;
		} else {
			$expire = ($expire > 0) ? time() + $expire : 0;
		}

		// Handle cookie 'samesite' attribute
		isset($samesite) or $samesite = config_item('cookie_samesite');

		if (isset($samesite)) {
			$samesite = ucfirst(strtolower($samesite));
			in_array($samesite, ['Lax', 'Strict', 'None'], true) or $samesite = 'Lax';
		} else {
			$samesite = 'Lax';
		}

		if ($samesite === 'None' && !$secure) {
			log_message('error', $name . ' cookie sent with SameSite=None, but without Secure attribute.');
		}

		if (!is_php('7.3')) {
			$maxage = $expire - time();
			if ($maxage < 1) {
				$maxage = 0;
			}

			$cookie_header = 'Set-Cookie: ' . $prefix . $name . '=' . rawurlencode($value);
			$cookie_header .= ($expire === 0 ? '' : '; Expires=' . gmdate('D, d-M-Y H:i:s T', $expire)) . '; Max-Age=' . $maxage;
			$cookie_header .= '; Path=' . $path . ($domain !== '' ? '; Domain=' . $domain : '');
			$cookie_header .= ($secure ? '; Secure' : '') . ($httponly ? '; HttpOnly' : '') . '; SameSite=' . $samesite;
			header($cookie_header);
			return;
		}

		// using setcookie with array option to add cookie 'samesite' attribute
		$setcookie_options = [
			'expires' => $expire,
			'path' => $path,
			'domain' => $domain,
			'secure' => $secure,
			'httponly' => $httponly,
			'samesite' => $samesite,
		];
		setcookie($prefix . $name, $value, $setcookie_options);
	}

	/**
	 * Alias To Method Above
	 * 
	 * @param   string|mixed[]  $name   Cookie name or an array containing parameters
	 * @param   string      $value      Cookie value
	 * @param   int         $expire     Cookie expiration time in seconds
	 * @param   string      $domain     Cookie domain (e.g.: '.yourdomain.com')
	 * @param   string      $path       Cookie path (default: '/')
	 * @param   string      $prefix     Cookie name prefix
	 * @param   bool        $secure     Whether to only transfer cookies via SSL
	 * @param   bool        $httponly   Whether to only makes the cookie accessible via HTTP (no javascript)
	 * @param   string|null $samesite   The SameSite cookie setting (Possible values: 'Lax', 'Strict', 'None', null, default: null)
	 * @return  void
	 */
	public function setCookie($name, $value = '', $expire = 0, $domain = '', $path = '/', $prefix = '', $secure = null, $httponly = null, $samesite = null)
	{
		$this->set_cookie($name, $value, $expire, $domain, $path, $prefix, $secure, $httponly, $samesite);
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch the IP Address
	 *
	 * Determines and validates the visitor's IP address.
	 *
	 * @return	string	IP address
	 */
	public function ip_address()
	{
		if ($this->ip_address !== false) {
			return $this->ip_address;
		}

		$proxy_ips = config_item('proxy_ips');
		if (!empty($proxy_ips) && !is_array($proxy_ips)) {
			$proxy_ips = explode(',', str_replace(' ', '', $proxy_ips));
		}

		$this->ip_address = $this->server('REMOTE_ADDR');

		if ($proxy_ips) {
			foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP'] as $header) {
				if (($spoof = $this->server($header)) !== null) {
					// Some proxies typically list the whole chain of IP
					// addresses through which the client has reached us.
					// e.g. client_ip, proxy_ip1, proxy_ip2, etc.
					sscanf($spoof, '%[^,]', $spoof);

					if (!$this->valid_ip($spoof)) {
						$spoof = null;
					} else {
						break;
					}
				}
			}

			if ($spoof) {
				for ($i = 0, $c = count($proxy_ips); $i < $c; $i++) {
					// Check if we have an IP address or a subnet
					if (strpos($proxy_ips[$i], '/') === false) {
						// An IP address (and not a subnet) is specified.
						// We can compare right away.
						if ($proxy_ips[$i] === $this->ip_address) {
							$this->ip_address = $spoof;
							break;
						}

						continue;
					}

					// We have a subnet ... now the heavy lifting begins
					isset($separator) or $separator = $this->valid_ip($this->ip_address, 'ipv6') ? ':' : '.';

					// If the proxy entry doesn't match the IP protocol - skip it
					if (strpos($proxy_ips[$i], $separator) === false) {
						continue;
					}

					// Convert the REMOTE_ADDR IP address to binary, if needed
					if (!isset($ip, $sprintf)) {
						if ($separator === ':') {
							// Make sure we're have the "full" IPv6 format
							$ip = explode(
								':',
								str_replace(
									'::',
									str_repeat(':', 9 - substr_count($this->ip_address, ':')),
									$this->ip_address
								)
							);

							for ($j = 0; $j < 8; $j++) {
								$ip[$j] = intval($ip[$j], 16);
							}

							$sprintf = '%016b%016b%016b%016b%016b%016b%016b%016b';
						} else {
							$ip = explode('.', $this->ip_address);
							$sprintf = '%08b%08b%08b%08b';
						}

						$ip = vsprintf($sprintf, $ip);
					}

					// Split the netmask length off the network address
					sscanf($proxy_ips[$i], '%[^/]/%d', $netaddr, $masklen);

					// Again, an IPv6 address is most likely in a compressed form
					if ($separator === ':') {
						$netaddr = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($netaddr, ':')), $netaddr));
						for ($j = 0; $j < 8; $j++) {
							$netaddr[$j] = intval($netaddr[$j], 16);
						}
					} else {
						$netaddr = explode('.', $netaddr);
					}

					// Convert to binary and finally compare
					if (strncmp($ip, vsprintf($sprintf, $netaddr), $masklen) === 0) {
						$this->ip_address = $spoof;
						break;
					}
				}
			}
		}

		if (!$this->valid_ip($this->ip_address)) {
			return $this->ip_address = '0.0.0.0';
		}

		return $this->ip_address;
	}

	/**
	 * Alias To Method Above
	 *
	 * @return	string	IP address
	 */
	public function ipAddress()
	{
		return $this->ip_address();
	}

	// --------------------------------------------------------------------

	/**
	 * Validate IP Address
	 *
	 * @param	string	$ip	IP address
	 * @param	string	$which	IP protocol: 'ipv4' or 'ipv6'
	 * @return	bool
	 */
	public function valid_ip($ip = '', $which = '')
	{
		switch (strtolower($which)) {
			case 'ipv4':
				$which = FILTER_FLAG_IPV4;
				break;
			case 'ipv6':
				$which = FILTER_FLAG_IPV6;
				break;
			default:
				$which = FILTER_DEFAULT;
				break;
		}

		return (bool) filter_var($ip, FILTER_VALIDATE_IP, $which);
	}

	/**
	 * Alias To Method Above
	 *
	 * @param	string	$ip	IP address
	 * @param	string	$which	IP protocol: 'ipv4' or 'ipv6'
	 * @return	bool
	 */
	public function validIp($ip = '', $which = '')
	{
		return $this->valid_ip($ip, $which);
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch User Agent string
	 *
	 * @return	string|null	User Agent string or null if it doesn't exist
	 */
	public function user_agent($xss_clean = false)
	{
		return $this->_fetch_from_array($_SERVER, 'HTTP_USER_AGENT', $xss_clean);
	}

	/**
	 * Alias To Method Above 
	 *
	 * @param bool $xss_clean
	 * @return string|null
	 */
	public function userAgent($xss_clean = false)
	{
		return $this->user_agent($xss_clean);
	}

	// --------------------------------------------------------------------

	/**
	 * Sanitize Globals
	 *
	 * Internal method serving for the following purposes:
	 *
	 *	- Unsets $_GET data, if query strings are not enabled
	 *	- Cleans POST, COOKIE and SERVER data
	 * 	- Standardizes newline characters to PHP_EOL
	 *
	 * @return	void
	 */
	protected function _sanitize_globals()
	{
		// Is $_GET data allowed? If not we'll set the $_GET to an empty array
		if ($this->_allow_get_array === false) {
			$_GET = [];
		} elseif (is_array($_GET)) {
			foreach ($_GET as $key => $val) {
				$_GET[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
			}
		}

		// Clean $_POST Data
		if (is_array($_POST)) {
			foreach ($_POST as $key => $val) {
				$_POST[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
			}
		}

		// Clean $_COOKIE Data
		if (is_array($_COOKIE)) {
			// Also get rid of specially treated cookies that might be set by a server
			// or silly application, that are of no use to a CI application anyway
			// but that when present will trip our 'Disallowed Key Characters' alarm
			// http://www.ietf.org/rfc/rfc2109.txt
			// note that the key names below are single quoted strings, and are not PHP variables
			unset(
				$_COOKIE['$Version'],
				$_COOKIE['$Path'],
				$_COOKIE['$Domain']
			);

			foreach ($_COOKIE as $key => $val) {
				if (($cookie_key = $this->_clean_input_keys($key)) !== false) {
					$_COOKIE[$cookie_key] = $this->_clean_input_data($val);
				} else {
					unset($_COOKIE[$key]);
				}
			}
		}

		// Sanitize PHP_SELF
		$_SERVER['PHP_SELF'] = strip_tags($_SERVER['PHP_SELF']);

		log_message('debug', 'Global POST, GET and COOKIE data sanitized');
	}

	// --------------------------------------------------------------------

	/**
	 * Clean Input Data
	 *
	 * Internal method that aids in escaping data and
	 * standardizing newline characters to PHP_EOL.
	 *
	 * @param	string|string[]	$str	Input string(s)
	 * @return	string
	 */
	protected function _clean_input_data($str)
	{
		if (is_array($str)) {
			$new_array = [];
			foreach (array_keys($str) as $key) {
				$new_array[$this->_clean_input_keys($key)] = $this->_clean_input_data($str[$key]);
			}
			return $new_array;
		}

		// Clean UTF-8 if supported
		if (UTF8_ENABLED === true) {
			$str = $this->uni->clean_string($str);
		}

		// Remove control characters
		$str = remove_invisible_characters($str, false);

		// Standardize newlines if needed
		if ($this->_standardize_newlines === true) {
			return preg_replace('/(?:\r\n|[\r\n])/', PHP_EOL, $str);
		}

		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Clean Keys
	 *
	 * Internal method that helps to prevent malicious users
	 * from trying to exploit keys we make sure that keys are
	 * only named with alpha-numeric text and a few other items.
	 *
	 * @param	string	$str	Input string
	 * @param	bool	$fatal	Whether to terminate script exection
	 *				or to return false if an invalid
	 *				key is encountered
	 * @return	string|bool
	 */
	protected function _clean_input_keys($str, $fatal = true)
	{
		if (!preg_match('/^[a-z0-9:_\/|-]+$/i', $str)) {
			if ($fatal === true) {
				return false;
			} else {
				set_status_header(503);
				echo 'Disallowed Key Characters.';
				exit(7); // EXIT_USER_INPUT
			}
		}

		// Clean UTF-8 if supported
		if (UTF8_ENABLED === true) {
			return $this->uni->clean_string($str);
		}

		return $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Request Headers
	 *
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	array
	 */
	public function request_headers($xss_clean = false)
	{
		// If header is already defined, return it immediately
		if (!empty($this->headers)) {
			return $this->_fetch_from_array($this->headers, null, $xss_clean);
		}

		// In Apache, you can simply call apache_request_headers()
		if (function_exists('apache_request_headers')) {
			$this->headers = apache_request_headers();
		} else {
			isset($_SERVER['CONTENT_TYPE']) && $this->headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];

			foreach ($_SERVER as $key => $val) {
				if (sscanf($key, 'HTTP_%s', $header) === 1) {
					// take SOME_HEADER and turn it into Some-Header
					$header = str_replace('_', ' ', strtolower($header));
					$header = str_replace(' ', '-', ucwords($header));

					$this->headers[$header] = $_SERVER[$key];
				}
			}
		}

		return $this->_fetch_from_array($this->headers, null, $xss_clean);
	}

	/**
	 * Alias To Method Above
	 *
	 * @param bool $xss_clean
	 * @return array
	 */
	public function requestHeaders($xss_clean = false)
	{
		return $this->request_headers($xss_clean);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Get Request Header
	 *
	 * Returns the value of a single member of the headers class member
	 *
	 * @param	string		$index		Header name
	 * @param	bool		$xss_clean	Whether to apply XSS filtering
	 * @return	string|null	The requested header on success or null on failure
	 */
	public function get_request_header($index, $xss_clean = false)
	{
		static $headers;

		if (!isset($headers)) {
			empty($this->headers) && $this->request_headers();
			foreach ($this->headers as $key => $value) {
				$headers[strtolower($key)] = $value;
			}
		}

		$index = strtolower($index);

		if (!isset($headers[$index])) {
			return null;
		}

		return ($xss_clean === true)
			? $this->security->xss_clean($headers[$index])
			: $headers[$index];
	}

	/**
	 * Alias To Method Above
	 * 
	 * @param string $index
	 * @param bool $xss_clean
	 * @return string|null
	 */
	public function getRequestHeader($index, $xss_clean = false)
	{
		return $this->get_request_header($index, $xss_clean);
	}

	// --------------------------------------------------------------------

	/**
	 * Is AJAX request?
	 *
	 * Test to see if a request contains the HTTP_X_REQUESTED_WITH header.
	 *
	 * @return 	bool
	 */
	public function is_ajax_request()
	{
		return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
	}

	/**
	 * Alias To Method Above
	 *
	 * @return bool
	 */
	public function isAjaxRequest()
	{
		return $this->is_ajax_request();
	}

	// --------------------------------------------------------------------

	/**
	 * Is CLI request?
	 *
	 * Test to see if a request was made from the command line.
	 *
	 * @deprecated	3.0.0	Use is_cli() instead
	 * @return	bool
	 */
	public function is_cli_request()
	{
		return is_cli();
	}

	// --------------------------------------------------------------------

	/**
	 * Get Request Method
	 *
	 * Return the request method
	 *
	 * @param	bool	$upper	Whether to return in upper or lower case
	 *				(default: false)
	 * @return 	string
	 */
	public function method($upper = false)
	{
		return ($upper)
			? strtoupper($this->server('REQUEST_METHOD'))
			: strtolower($this->server('REQUEST_METHOD'));
	}

	// ------------------------------------------------------------------------

	/**
	 * Magic __get()
	 *
	 * Allows read access to protected properties
	 *
	 * @param	string	$name
	 * @return	mixed
	 */
	public function __get($name)
	{
		if ($name === 'raw_input_stream') {
			isset($this->_raw_input_stream) or $this->_raw_input_stream = file_get_contents('php://input');
			return $this->_raw_input_stream;
		} elseif ($name === 'ip_address') {
			return $this->ip_address;
		}
	}
}
