<?php
namespace Cassandra\Connection;

class Socket {

	/**
	 * @var resource
	 */
	protected $_socket;

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
	 * @param array $options
	 */
	public function __construct(array $options) {
		if (isset($options['socket'])) {
			$options['socket'] += $this->_options['socket'];
		}
		$this->_options = array_merge($this->_options, $options);
		
		$this->_connect();
	}

	/**
	 * 
	 * @throws SocketException
	 * @return resource
	 */
	protected function _connect() {
		if (!empty($this->_socket)) return $this->_socket;

		$this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		if ($this->_socket === false){
			$errorCode = socket_last_error($this->_socket);
			throw new SocketException(socket_strerror($errorCode), $errorCode);
		}

		socket_set_option($this->_socket, SOL_TCP, TCP_NODELAY, 1);
		
		foreach($this->_options['socket'] as $optname => $optval)
			socket_set_option($this->_socket, SOL_SOCKET, $optname, $optval);
		
		$result = socket_connect($this->_socket, $this->_options['host'], $this->_options['port']);
		
		if ($result === false){
			$errorCode = socket_last_error($this->_socket);
			//Unable to connect to Cassandra node: {$this->_options['host']}:{$this->_options['port']}
			throw new SocketException(socket_strerror($errorCode), $errorCode);
		}
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->_options;
	}
	

	/**
	 * @param $length
	 * @throws SocketException
	 * @return string
	 */
	public function read($length) {
		$data = socket_read($this->_socket, $length);
		
		if ($data === false){
			$errorCode = socket_last_error($this->_socket);
			throw new SocketException(socket_strerror($errorCode), $errorCode);
		}
		
		$remainder = $length - strlen($data);
		
		while($remainder > 0) {
			$readData = socket_read($this->_socket, $remainder);
	
			if ($readData === false){
				$errorCode = socket_last_error($this->_socket);
				throw new SocketException(socket_strerror($errorCode), $errorCode);
			}
			
			$data .= $readData;
			$remainder -= strlen($readData);
		}
		
		return $data;
	}

	/**
	 * @param $length
	 * @throws SocketException
	 * @return string
	 */
	public function readOnce($length){
		$data = socket_read($this->_socket, $length);
		
		if ($data === false){
			$errorCode = socket_last_error($this->_socket);
			throw new SocketException(socket_strerror($errorCode), $errorCode);
		}
		
		return $data;
	}
	
	/**
	 * 
	 * @param string $binary
	 * @throws SocketException
	 */
	public function write($binary){
		do{
			$sentBytes = socket_write($this->_socket, $binary);
			
			if ($sentBytes === false){
				$errorCode = socket_last_error($this->_socket);
				throw new SocketException(socket_strerror($errorCode), $errorCode);
			}
			$binary = substr($binary, $sentBytes);
		}
		while(!empty($binary));
	}
	
	public function close(){
		socket_shutdown($this->_socket);
	}
}
