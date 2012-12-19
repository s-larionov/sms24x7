<?php

/**
 * Parse urls: [scheme://][username[:password]@]host[:port]path
 */
class HttpUri {
	const SCHEME_HTTP	= 'http';
	const SCHEME_HTTPS	= 'https';

	protected $_scheme	= self::SCHEME_HTTP;

	protected $_username= null;
	protected $_password= null;

	protected $_host;
	protected $_port	= 80;
	protected $_path	= '/';
	protected $_query	= '';
	protected $_fragment= '';

	/**
	 * @param string $url
	 */
	public function __construct($url) {
		$parsedUrl = parse_url($url);

		isset($parsedUrl['scheme'])		&& $this->setScheme($parsedUrl['scheme']);
		isset($parsedUrl['user'])		&& $this->setUsername($parsedUrl['user']);
		isset($parsedUrl['password'])	&& $this->setPassword($parsedUrl['password']);
		isset($parsedUrl['host'])		&& $this->setHost($parsedUrl['host']);
		isset($parsedUrl['port'])		&& $this->setPort($parsedUrl['port']);
		isset($parsedUrl['path'])		&& $this->setPath($parsedUrl['path']);
		isset($parsedUrl['query'])		&& $this->setQuery($parsedUrl['query']);
		isset($parsedUrl['fragment'])	&& $this->setFragment($parsedUrl['fragment']);
	}

	/**
	 * @return string
	 */
	public function getScheme() {
		return $this->_scheme;
	}

	/**
	 * @param string $scheme
	 * @return HttpUri
	 * @throws HttpException
	 */
	public function setScheme($scheme) {
		switch ($scheme) {
			case self::SCHEME_HTTP:
				// do nothing
				break;
			case self::SCHEME_HTTPS:
				if ($this->getPort() === 80) {
					$this->setPort(443);
				}
				break;
			default:
				throw new HttpException('Unsupported uri scheme');
		}
		$this->_scheme = $scheme;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getUsername() {
		return $this->_username;
	}

	/**
	 * @param string|null $username
	 * @return HttpUri
	 */
	public function setUsername($username) {
		$this->_username = $username === null? null: (string) $username;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getPassword() {
		return $this->_password;
	}

	/**
	 * @param string $password
	 * @return HttpUri
	 */
	public function setPassword($password) {
		$this->_password = $password === null? null: (string) $password;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getHost() {
		return $this->_host;
	}

	/**
	 * @param $host
	 * @return HttpUri
	 */
	public function setHost($host) {
		$this->_host = (string) $host;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getPort() {
		return $this->_port;
	}

	/**
	 * @param int $port
	 * @return HttpUri
	 */
	public function setPort($port) {
		$this->_port = (int) $port;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->_path;
	}

	/**
	 * @param string $path
	 * @return HttpUri
	 */
	public function setPath($path) {
		$this->_path = '/' . ltrim($path, '/');
		return $this;
	}

	/**
	 * @return string
	 */
	public function getQuery() {
		return $this->_query;
	}

	/**
	 * @param string $query
	 * @return HttpUri
	 */
	public function setQuery($query) {
		$this->_query = (string) $query;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFragment() {
		return $this->_fragment;
	}

	/**
	 * @param string $fragment
	 * @return HttpUri
	 */
	public function setFragment($fragment) {
		$this->_fragment = ltrim($fragment, '#');
		return $this;
	}

	/**
	 * @return string
	 */
	function __toString() {
		$host = '';
		if ($this->getHost() != '') {
			$auth = $this->getUsername();
			if ($password = $this->getPassword()) {
				$auth .= ":{$password}";
			}
			if (!empty($auth)) {
				$auth .= '@';
			}
			$host = "{$this->getScheme()}://{$auth}{$this->getHost()}:{$this->getPort()}";
		}
		$uri = "{$host}{$this->getPath()}";
		if ($query = $this->getQuery()) {
			$uri .= '?' . $query;
		}
		if ($fragment = $this->getFragment()) {
			$uri .= '#' . $fragment;
		}
		return $uri;
	}
}
