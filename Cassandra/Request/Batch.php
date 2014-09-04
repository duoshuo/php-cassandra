<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;
use Cassandra\Protocol;
use Cassandra\Connection;

class Batch extends Request{
	const TYPE_LOGGED = 0;
	const TYPE_UNLOGGED = 1;
	const TYPE_COUNTER = 2;
	
	protected $opcode = Frame::OPCODE_BATCH;
	
	/**
	 * @var array
	 */
	protected $_queryArray = [];

	/**
	 * @var int
	 */
	protected $_batchType = null;
	
	/**
	 * 
	 * @var int
	 */
	protected $_consistency;
	
	/**
	 * 
	 * @var array
	 */
	protected $_options;
	
	public function __construct($type = null, $consistency = null, $options = array()) {
		$this->_batchType = $type ?: Batch::TYPE_LOGGED;
		$this->_consistency = $consistency ?: Request::CONSISTENCY_QUORUM;
		$this->_options = $options;
	}

	/**
	 * Exec transaction
	 */
	public function getBody() {
		$body = pack('C', $this->_batchType);
		$body .= pack('n', count($this->_queryArray)) . implode('', $this->_queryArray);
		
		$body .= Request::queryParameters($this->_consistency, [], $this->_options);
		return $body;
	}

	/**
	 * @param string $cql
	 * @param array $values
	 * @return self
	 */
	public function appendQuery($cql, array $values = array()) {
		$binary = pack('C', 0);
	
		$binary .= pack('N', strlen($cql)) . $cql;
		$binary .= Request::valuesBinary($values);
		
		$this->_queryArray[] = $binary;
		
		return $this;
	}
	
	/**
	 * 
	 * @param string $queryId
	 * @param array $values
	 * @return self
	 */
	public function appendQueryId($queryId, array $values = array()) {
		$binary = pack('C', 1);
		
		$binary .= pack('n', strlen($queryId)) . $queryId;
		$binary .= Request::valuesBinary($values);
		
		$this->_queryArray[] = $binary;
		
		return $this;
	}
}
