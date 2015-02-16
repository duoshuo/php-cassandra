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
	 * Create connection and return socket resource
	 *
	 * @throws Exception
	 * @return resource
	 */
	public function getConnection() {

		if(!$this->isConnected()) {
			try {
				$this->connect();
			} catch(\Exception $e) {
				throw new Exception("Unable to connect to Cassandra node: {$this->_options['host']}:{$this->_options['port']}. Reason: ". $e->getMessage(), 0, $e);
			}
		}

		return $this->socket;
	}

	/**
	 * @return array
	 */
	public function getOptions()
	{
		return $this->_options;
	}

	/**
	 * Connect to host/port
	 *
	 * @throws Exception
	 */
	public function connect()
	{
		$this->createConnection();
		$this->setConnectionOptions();

		$result = @socket_connect($this->socket, $this->_options['host'], $this->_options['port']);

		if($result === false) {
			throw new Exception("Unable to connect: " . socket_strerror(socket_last_error($this->socket)));
		}
	}

	/**
	 * Disconnect
	 */
	public function disconnect()
	{
		if(is_resource($this->socket)) {
			@socket_close($this->socket);
		}

		$this->socket = null;
	}

	/**
	 * @return bool
	 */
	public function isConnected()
	{
		return is_resource($this->socket);
	}


	/**
	 * @throws Exception
	 */
	private function createConnection()
	{
		$this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		if($this->socket === false) {
			throw new Exception("Unable to create socket: ". socket_strerror(socket_last_error()));
		}
	}

	/**
	 * @throws Exception
	 */
	private function setConnectionOptions()
	{
		$this->setConnectionOption(getprotobyname('tcp'), TCP_NODELAY, 1);

		foreach($this->_options['socket'] as $name => $value) {
			$this->setConnectionOption(SOL_SOCKET, $name, $value);
		}
	}

	/**
	 * @param int $level
	 * @param int $name
	 * @param mixed $value
	 * @throws Exception
	 */
	private function setConnectionOption($level, $name, $value)
	{
		$result = @socket_set_option($this->socket, $level, $name, $value);

		if($result === false) {
			throw new Exception("Unable to set socket option: $level, $name");
		}
	}
}
