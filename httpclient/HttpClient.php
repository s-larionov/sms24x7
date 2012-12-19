<?php

require_once 'HttpUri.php';
require_once 'HttpResponse.php';
require_once 'HttpException.php';

class HttpClient {
	const REQUEST_METHOD_GET	= 'GET';
	const REQUEST_METHOD_POST	= 'POST';

	/**
	 * POST data encoding methods
	 */
	const ENC_URLENCODED = 'application/x-www-form-urlencoded';
	const ENC_FORMDATA   = 'multipart/form-data';

	/**
	 * @var HttpUri|null
	 */
	protected $_uri = null;

	/**
	 * @var resource|null
	 */
	protected $_context			= null;

	/**
	 * @var resource|null
	 */
	protected $_socket			= null;

	/**
	 * @var array
	 */
	protected $_auth			= array('username' => null, 'password' => null);

	protected $_parametersGet	= array();
	protected $_parametersPost	= array();

	protected $_headers			= array();

	/**
	 * Load response error string.
	 * Is null if there was no error while response loading
	 *
	 * @var string|null
	 */
	protected $_loadResponseErrorStr		= null;

	/**
	 * Load response error code.
	 * Is null if there was no error while response loading
	 *
	 * @var int|null
	 */
	protected $_loadResponseErrorCode		= null;

	/**
	 * @var int[]|string[]
	 */
	protected $_config = array(
		'fetchBody'		=> true,
		'fetchHeaders'	=> true,
		'timeout'		=> 10
	);

	/**
	 * @param HttpUri|string|null $uri
	 */
	public function __construct($uri = null) {
		if ($uri !== null) {
			$this->setUri($uri);
		}
	}

	/**
	 * @param string $method
	 * @return HttpResponse
	 */
	public function request($method = self::REQUEST_METHOD_GET) {
		// Warnings and errors are suppressed
		set_error_handler(array($this, 'loadFileErrorHandler'));

		$this->setConfig(array('method' => $method));

		$this->_createContext();
		$this->_connect();

		$response = new HttpResponse($this->_fetchHeaders(), $this->_fetchBody());

		$this->_disconnect();
		restore_error_handler();

		return $response;
	}

	/**
	 * @return array
	 * @throws HttpException
	 */
	protected function _fetchHeaders() {
		if ($this->getConfig('fetchHeaders')) {
			$metaData = stream_get_meta_data($this->_socket);
			if ($this->_loadResponseErrorStr !== null) {
				$this->_disconnect();
				throw new HttpException("Can't fetch response headers from {$this->getUrl()} [{$this->_loadResponseErrorStr}]");
			}
			$headers = $metaData['wrapper_data'];
		} else {
			$headers = array('HTTP/1.0 200 OK');
		}
		return $headers;
	}

	/**
	 * @return string
	 * @throws HttpException
	 */
	protected function _fetchBody() {
		if ($this->getConfig('fetchBody')) {
			$body = stream_get_contents($this->_socket);
			if ($this->_loadResponseErrorStr !== null) {
				$this->_disconnect();
				throw new HttpException("Can't fetch response content from {$this->getUrl()} [{$this->_loadResponseErrorStr}]", $this->_loadResponseErrorCode);
			}
		} else {
			$body = '';
		}
		return $body;
	}


	/**
	 * @return null|resource
	 * @throws HttpException
	 */
	protected function _createContext() {
		$httpOptions = array(
			'method'		=> $this->getConfig('method'),
			'timeout'		=> $this->getConfig('timeout'),
			'max_redirects'	=> 0,
			'ignore_errors'	=> 1,
			'header'		=> $this->_prepareHeaders()
		);

		// setup POST-paramteres
		if ($this->getConfig('method') == self::REQUEST_METHOD_POST) {
			$httpOptions['content'] = http_build_query($this->_parametersPost, null, '&');
		}

		$this->_context = stream_context_create(array(
			'http' => $httpOptions
		));
		if ($this->_loadResponseErrorStr !== null) {
			throw new HttpException($this->_loadResponseErrorStr, $this->_loadResponseErrorCode);
		}
		return $this->_context;
	}

	protected function _prepareHeaders() {
		$headers = '';

		$this->setHeader('Host', $this->getUrl()->getHost());
		if (($username = $this->getAuth('username')) && ($password = $this->getAuth('password'))) {
			$this->setHeader('Authorization', "Basic " . base64_encode(urlencode($username) . ':' . urlencode($password)));
		}
		$this->setHeader('Content-Type', $this->getConfig('method') == self::REQUEST_METHOD_POST? self::ENC_URLENCODED: self::ENC_FORMDATA);

		foreach($this->_headers as $name => $value) {
			$headers .= "{$name}: {$value}\n";
		}

		return $headers;
	}

	/**
	 * @param string $name
	 * @param string|null $value
	 * @return HttpClient
	 */
	protected function setHeader($name, $value) {
		if ($value === null && array_key_exists($name, $this->_headers)) {
			unset($this->_headers[$name]);
		} else {
			$this->_headers[$name] = $value;
		}
		return $this;
	}

	/**
	 * @return resource
	 * @throws HttpException
	 */
	protected function _connect() {
		// setup GET-parameters to URI
		$uri = clone $this->getUrl();
		$query = $uri->getQuery();
		if (!empty($query)) {
			$query .= '&';
		}
		$query .= http_build_query($this->_parametersGet);
		$uri->setQuery($query);

		// connect to http-server
		$this->_socket = fopen((string) $uri, 'r', null, $this->_context);

		if ($this->_loadResponseErrorStr !== null) {
			$this->_disconnect();
			throw new HttpException("Can't connect to {$this->getUrl()} [{$this->_loadResponseErrorStr}]", $this->_loadResponseErrorCode);
		}
		return $this->_socket;
	}

	/**
	 * @return HttpClient
	 */
	protected function _disconnect() {
		if (is_resource($this->_socket)) {
			fclose($this->_socket);
		}
		return $this;
	}

	/**
	 * Handle any errors from simplexml_load_file or parse_ini_file
	 *
	 * @param integer $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param integer $errline
	 */
	public function loadFileErrorHandler($errno, $errstr, $errfile, $errline) {
		$this->_loadResponseErrorCode	= $errno;
		if ($this->_loadResponseErrorStr === null) {
			$this->_loadResponseErrorStr	= $errstr;
		} else {
			$this->_loadResponseErrorStr .= (PHP_EOL . $errstr);
		}
	}

	/**
	 * @param string|null $username
	 * @param string|null $password
	 * @return HttpClient
	 */
	public function setAuth($username, $password) {
		$this->_auth = array(
			'username' => $username === null? null: (string) $username,
			'password' => $password === null? null: (string) $password
		);
		return $this;
	}

	/**
	 * @param string|null $field
	 * @return array|string
	 */
	public function getAuth($field = null) {
		if ($field === null) {
			return $this->_auth;
		} else if (array_key_exists($field, $this->_auth)) {
			return $this->_auth[$field];
		}
		return null;
	}

	/**
	 * @param array $config
	 * @return HttpClient
	 */
	public function setConfig(array $config) {
		$this->_config = array_merge($this->_config, $config);
		return $this;
	}

	/**
	 * @param null $parameter
	 * @return array|string|int|null
	 */
	public function getConfig($parameter = null) {
		if ($parameter === null) {
			return $this->_config;
		} else if (array_key_exists($parameter, $this->_config)) {
			return $this->_config[$parameter];
		}
		return null;
	}

	/**
	 * @param HttpUri|string $uri
	 * @return HttpClient;
	 */
	public function setUri($uri) {
		if (!$uri instanceof HttpUri) {
			$uri = new HttpUri($uri);
		}
		$this->_uri = $uri;
		return $this;
	}

	/**
	 * @return HttpUri|null
	 */
	public function getUrl() {
		if (!$this->_uri instanceof HttpUri) {
			throw HttpException('RequestUri is not setup');
		}
		return $this->_uri;
	}

	/**
	 * @param bool $resetHeaders
	 * @return HttpClient
	 */
	public function resetParameters($resetHeaders = false) {
		$this->_parametersGet	= array();
		$this->_parametersPost	= array();
		if ($resetHeaders) {
			$this->_headers = array();
		}
		return $this;
	}

	/**
	 * @param string[]|string $name
	 * @param string|null $value
	 * @return HttpClient
	 */
	public function setParameterGet($name, $value = null) {
		if (is_array($name)) {
			foreach($name as $k => $v) {
				$this->setParameterGet($k, $v);
			}
		} else {
			$this->_parametersGet[$name] = $value;
		}
		return $this;
	}

	/**
	 * @param string[]|string $name
	 * @param string|null $value
	 * @return HttpClient
	 */
	public function setParameterPost($name, $value = null) {
		if (is_array($name)) {
			foreach($name as $k => $v) {
				$this->setParameterPost($k, $v);
			}
		} else {
			$this->_parametersPost[$name] = $value;
		}
		return $this;
	}
}
