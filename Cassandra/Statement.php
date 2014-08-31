<?php
namespace Cassandra;

class Statement{
	/**
	 * @var Connection
	 */
	protected $_connection;
	
	/**
	 * 
	 * @var int
	 */
	protected $_streamId;
	
	/**
	 * 
	 * @var Response\DataStream
	 */
	protected $_response;
	
	public function __construct($connection, $streamId){
		$this->_connection = $connection;
		$this->_streamId = $streamId;		
	}
	
	public function getResponse(){
		if($this->_response === null){
			return $this->_connection->getResponse($this->_streamId);
		}
		
		return $this->_response;
	}
	
	/**
	 * 
	 * @param Response\DataStream $response
	 */
	public function setResponse($response){
		$this->_response = $response;
	}
}
