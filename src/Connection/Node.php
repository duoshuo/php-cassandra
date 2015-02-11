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
		'timeout'	=> 30,
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
	 * 
	 * @throws Exception
	 * @return resource
	 */
	public function getConnection() {
		if (!empty($this->socket)) return $this->socket;

		$this->socket = fsockopen($this->_options['host'], $this->_options['port']);
		if (!$this->socket)
			throw new Exception("Unable to connect to Cassandra node: {$this->_options['host']}:{$this->_options['port']}");

		$t = $this->_options['timeout'];
		stream_set_timeout($this->socket,$this->_options['timeout']);		

		return $this->socket;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->_options;
	}
}
