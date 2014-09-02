<?php
namespace Cassandra\Response;
use Cassandra\Protocol\Frame;

class Response implements Frame{
	use StreamReader;
	
	/**
	 * @var array
	 */
	protected $_header;
	
	/**
	 * 
	 * @param array $header
	 * @param $body
	 */
	public function __construct($header, $body){
		$this->_header = $header;
		
		$this->data = $body;
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
		return $this->data;
	}
}
