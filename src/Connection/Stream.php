<?php
namespace Cassandra\Connection;

class Stream {

	/**
	 * @var resource
	 */
	protected $_stream;

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

		$this->_connect();
	}

	/**
	 *
	 * @throws SocketException
	 * @return resource
	 */
	protected function _connect() {
		if (!empty($this->_stream)) return $this->_stream;

		$errorCode = 0;
		$this->_stream = fsockopen($this->_options['host'], $this->_options['port'], $errorCode);

		if ($this->_stream === false){
			throw new Connection\Exception(socket_strerror($errorCode));
		}

		stream_set_timeout($this->_stream,$this->_options['timeout']);
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
		$data = '';
		$remainder = $length;

		for(;$length > 0;$length -= strlen($readData)) {
			$readData = fread($this->_stream,1);

			if (feof($this->_stream))
				throw new Connection\Exception('Connection reset by peer');

			if (stream_get_meta_data($this->_stream)['timed_out'])
				throw new Connection\Exception('Connection timed out');

			if (strlen($readData) == 0)
				throw new Connection\Exception("Unknown error");

			$data .= $readData;
		}
		return $data;
	}

	/**
	 *
	 * @param string $binary
	 * @throws SocketException
	 */
	public function write($binary){
	   for ($written = 0; $written < strlen($binary); $written += $fwrite) {
	        $fwrite = fwrite($this->_stream, substr($binary, $written));

	        if (feof($this->_stream))
				throw new Connection\Exception('Connection reset by peer');

	        if (stream_get_meta_data($this->_stream)['timed_out'])
				throw new Connection\Exception('Connection timed out');

	        if ($fwrite == 0)
				throw new Connection\SocketException("Uknown error");
	    }
	}

	public function close(){
		 fclose($this->_stream);
	}
}
