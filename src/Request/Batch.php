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
	protected $_batchType;
	
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
	
	/**
	 * 
	 * @param string $type
	 * @param string $consistency
	 * @param array $options
	 */
	public function __construct($type = null, $consistency = null, $options = []) {
		$this->_batchType = $type === null ? Batch::TYPE_LOGGED : $type;
		$this->_consistency = $consistency === null ? Request::CONSISTENCY_ONE : $consistency;
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
	public function appendQuery($cql, array $values = []) {
		$binary = pack('C', 0);
	
		$binary .= pack('N', strlen($cql)) . $cql;
		$binary .= Request::valuesBinary($values, !empty($this->_options['names_for_values']));
		
		$this->_queryArray[] = $binary;
		
		return $this;
	}
	
	/**
	 * 
	 * @param string $queryId
	 * @param array $values
	 * @return self
	 */
	public function appendQueryId($queryId, array $values = []) {
		$binary = pack('C', 1);
		
		$binary .= pack('n', strlen($queryId)) . $queryId;
		$binary .= Request::valuesBinary($values, !empty($this->_options['names_for_values']));
		
		$this->_queryArray[] = $binary;
		
		return $this;
	}
}
