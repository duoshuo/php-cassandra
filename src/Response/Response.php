<?php
namespace Cassandra\Response;
use Cassandra\Protocol\Frame;

class Response implements Frame{
	/**
	 * @var array
	 */
	protected $_header;

	/**
	 * @var
	 */
	protected $_stream;
	
	/**
	 * 
	 * @param array $header
	 * @param $stream
	 */
	public function __construct($header, $stream){
		$this->_header = $header;
		
		$this->_stream = $stream;
	}
	
	public function getVersion(){
		return $this->_header['version'];
	}
	
	public function getFlags(){
		return $this->_header['flags'];
	}
	
	public function getStream(){
		return $this->_header['stream'];
	}
	
	public function getOpcode(){
		return $this->_header['opcode'];
	}
	
	public function getBody(){
		return $this->_stream;
	}
}
