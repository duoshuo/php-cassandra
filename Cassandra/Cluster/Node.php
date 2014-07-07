<?php
namespace Cassandra\Cluster;

use Cassandra\Exception\ConnectionException;

class Node {

	const STREAM_TIMEOUT = 10;

	/**
	 * @var string
	 */
	private $host;

	/**
	 * @var int
	 */
	private $port = 9042;

	/**
	 * @var resource
	 */
	private $socket;

	/**
	 * @var array
	 */
	private $options = [
		'username' => null,
		'password' => null
	];

	/**
	 * @param string $host
	 * @param array $options
	 * @throws \InvalidArgumentException
	 */
	public function __construct($host, array $options = []) {
		$this->host = $host;
		if (strstr($this->host, ':')) {
			$this->port = (int)substr(strstr($this->host, ':'), 1);
			$this->host = substr($this->host, 0, -1 - strlen($this->port));
			if (!$this->port) {
				throw new \InvalidArgumentException('Invalid port number');
			}
		}
		$this->options = array_merge($this->options, $options);
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
		if (!socket_connect($this->socket, $this->host, $this->port)) {
			throw new ConnectionException("Unable to connect to Cassandra node: {$this->host}:{$this->port}");
		}

		return $this->socket;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->options;
	}
}