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
	 * @var Response\Response
	 */
	protected $_response;
	
	public function __construct($connection, $streamId){
		$this->_connection = $connection;
		$this->_streamId = $streamId;		
	}
	
	/**
	 * 
	 * @throws Response\Exception
	 * @return Response\Response
	 */
	public function getResponse(){
		if($this->_response === null){
			$this->_connection->getResponse($this->_streamId);
		}
		
		if ($this->_response instanceof Response\Error)
			throw $this->_response->getException();
		
		return $this->_response;
	}
	
	/**
	 * 
	 * @param Response\Response $response
	 */
	public function setResponse($response){
		$this->_response = $response;
	}
}
