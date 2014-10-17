<?php
namespace Cassandra\Connection;

class Node {

	/**
	 * @var resource
	 */
	protected $socket;

	/**
	 * @var array
	 */
	protected $_options = [
		'host'		=> null,
		'port'		=> 9042,
		'username'	=> null,
		'password'	=> null,
		'socket'	=> [
			SO_RCVTIMEO => ["sec" => 30, "usec" => 0],
			SO_SNDTIMEO => ["sec" => 5, "usec" => 0],
		],
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
			if (isset($options['socket'])) {
				$options['socket'] += $this->_options['socket'];
			}
			$this->_options = array_merge($this->_options, $options);
		}
	}

	/**
	 * 
	 * @throws Exception
	 * @return resource
	 */
	public function getConnection() {
		if (!empty($this->socket)) return $this->socket;

		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		if ($this->socket === false)
			throw new Exception(socket_strerror(socket_last_error()));

		socket_set_option($this->socket, getprotobyname('TCP'), TCP_NODELAY, 1);
		
		foreach($this->_options['socket'] as $optname => $optval)
			socket_set_option($this->socket, SOL_SOCKET, $optname, $optval);
		
		if (!socket_connect($this->socket, $this->_options['host'], $this->_options['port']))
			throw new Exception("Unable to connect to Cassandra node: {$this->_options['host']}:{$this->_options['port']}");

		return $this->socket;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->_options;
	}
}
