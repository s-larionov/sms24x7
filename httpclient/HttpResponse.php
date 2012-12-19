<?php

class HttpResponse {
	protected $_statusCode	= 200;
	protected $_statusText	= 'OK';
	protected $_body		= '';
	protected $_headers		= array();

	public function __construct(array $headers, $body) {
		list(, $statusCode, $statusText) = explode(' ', array_shift($headers), 3);
		$this->_setStatus($statusCode, $statusText);

		$this->_setHeaders($headers);
		$this->_setBody($body);
	}

	/**
	 * @param int $code
	 * @param string $text
	 * @return HttpResponse
	 */
	protected function _setStatus($code, $text) {
		$this->_statusCode = (int) $code;
		$this->_statusText = (string) $text;
		return $this;
	}

	protected function _setHeaders(array $headers) {
		foreach($headers as $header) {
			list($name, $value) = explode(':', $header, 2);
			$this->_headers[$name] = trim($value);
		}
		return $this;
	}

	/**
	 * @param null $name
	 * @return string[]|string|null
	 */
	public function getHeader($name = null) {
		if ($name === null) {
			return $this->_headers;
		} else if (array_key_exists($name, $this->_headers)) {
			return $this->_headers[$name];
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getBody() {
		return $this->_body;
	}

	/**
	 * @param string $body
	 * @return string
	 */
	protected function _setBody($body) {
		$this->_body = (string) $body;
	}

	/**
	 * @return int
	 */
	public function getStatus() {
		return $this->_statusCode;
	}

	/**
	 * @return string
	 */
	public function getStatusText() {
		return $this->_statusText;
	}
}
