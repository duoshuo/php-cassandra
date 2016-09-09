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
		'connectTimeout'=>5,
		'persistent'=> false,
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options) {
		$this->_options = array_merge($this->_options, $options);

		$this->_connect();
	}

	/**
	 *
	 * @throws StreamException
	 * @return resource
	 */
	protected function _connect() {
		if (!empty($this->_stream)) return $this->_stream;

		$context = stream_context_create();
		if (isset($this->_options['ssl'])){
			foreach($this->_options['ssl'] as $optname => $optval)
				stream_context_set_option($context, 'ssl', $optname, $optval);
		}

		$connFlag = $this->_options['persistent'] ? STREAM_CLIENT_PERSISTENT : STREAM_CLIENT_CONNECT ;
 		$this->_stream = stream_socket_client($this->_options['host'].':'. $this->_options['port'], $errorCode, $errorMessage, $this->_options['connectTimeout'], $connFlag, $context);

		if ($this->_stream === false){
			throw new StreamException($errorMessage, $errorCode);
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
	 * @throws StreamException
	 * @return string
	 */
	public function read($length) {
		$data = '';
		do{
			$readData = fread($this->_stream, $length);

			if (feof($this->_stream))
				throw new StreamException('Connection reset by peer');

			if (stream_get_meta_data($this->_stream)['timed_out'])
				throw new StreamException('Connection timed out');

			if (strlen($readData) == 0)
				throw new StreamException("Unknown error");

			$data .= $readData;
			$length -= strlen($readData);
		}
		while($length > 0);
		
		return $data;
	}

	/**
	 * @param $length
	 * @throws StreamException
	 * @return string
	 */
	public function readOnce($length){
		$readData = fread($this->_stream, $length);

		if (feof($this->_stream))
			throw new StreamException('Connection reset by peer');

		if (stream_get_meta_data($this->_stream)['timed_out'])
			throw new StreamException('Connection timed out');

		if (strlen($readData) == 0)
			throw new StreamException("Unknown error");

		return $readData;
	}

	/**
	 *
	 * @param string $binary
	 * @throws StreamException
	 */
	public function write($binary){
		do{
			$sentBytes = fwrite($this->_stream, $binary);
			
			if (feof($this->_stream))
				throw new StreamException('Connection reset by peer');
			
			if (stream_get_meta_data($this->_stream)['timed_out'])
				throw new StreamException('Connection timed out');
			
			if ($sentBytes == 0)
				throw new StreamException("Uknown error");
			
			$binary = substr($binary, $sentBytes);
		}
		while(!empty($binary));
	}

	public function close(){
		 fclose($this->_stream);
	}
}
