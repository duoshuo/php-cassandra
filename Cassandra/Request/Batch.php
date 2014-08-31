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
	
	protected $_consistency;
	
	protected $_serialConsistency;
	
	/**
	 * 
	 * @var \Cassandra\Connection
	 */
	protected $_connection;

	public function __construct($type = Batch::TYPE_LOGGED, $consistency = Request::CONSISTENCY_QUORUM, $serialConsistency = null) {
		$this->_batchType = $type;
		$this->_consistency = $consistency;
		$this->_serialConsistency = $serialConsistency;
	}
	
	/**
	 * 
	 * @param \Cassandra\Connection $connection
	 * @return self
	 */
	public function setConnection($connection){
		$this->_connection = $connection;
		
		return $this;
	}

	/**
	 * Exec transaction
	 */
	public function getBody() {
		$body = pack('C', $this->_batchType);
		$body .= pack('n', count($this->_queryArray)) . implode('', $this->_queryArray);

		$body .= Request::queryParameters($this->_consistency, $this->_serialConsistency);
		return $body;
	}

	/**
	 * @param string $cql
	 * @param array $values
	 */
	public function appendQuery($cql, array $values = array()) {
		$kind = empty($values) ? 0 : 1;
		$binary = pack('C', $kind);
	
		if ($kind == 0) {
			$binary .= pack('N', strlen($cql)) . $cql;
			// 0 of following values
			$binary .= pack('n', 0);
		}
		else {
			if ($this->_connection === null)
				throw new Connection\Exception('Cannot prepare query without a connection.');
			
			$preparedData = $this->_connection->prepare($cql);
			$binary .= pack('n', strlen($preparedData['id'])) . $preparedData['id'];
			$binary .= Request::valuesBinary($preparedData, $values);
		}
		$this->_queryArray[] = $binary;
		
		return $this;
	}
}
