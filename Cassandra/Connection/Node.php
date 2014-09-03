<?php
namespace Cassandra\Connection;

class Node {

	const STREAM_TIMEOUT = 10;

	/**
	 * @var resource
	 */
	private $socket;

	/**
	 * @var array
	 */
	private $_options = [
		'host'     => null,
		'port'     => 9042,
		'username' => null,
		'password' => null,
	];

	/**
	 * @param string|array $options
	 */
	public function __construct($options) {
		if (is_string($options)){
			$pos = strpos($options, ':');
			if ($pos === false) {
				$this->_options['host'] = $options;
			}
			else{
				$this->_options['host'] = substr($options, 0, $pos);
				$this->_options['port'] = (int) substr($options, $pos + 1);
			}
		}
		else{
			$this->_options = array_merge($this->_options, $options);
		}
	}

	/**
	 * @return resource
	 * @throws \Exception
	 */
	public function getConnection() {
		if (!empty($this->socket)) return $this->socket;

		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($this->socket, getprotobyname('TCP'), TCP_NODELAY, 1);
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ["sec" => self::STREAM_TIMEOUT, "usec" => 0]);
		if (!socket_connect($this->socket, $this->_options['host'], $this->_options['port'])) {
			throw new Exception("Unable to connect to Cassandra node: {$this->_options[host]}:{$this->_options[port]}");
		}

		return $this->socket;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->_options;
	}
}
