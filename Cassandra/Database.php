<?php
namespace Cassandra;
use Cassandra\Enum\ConsistencyEnum;
use Cassandra\Enum\BatchTypeEnum;
use Cassandra\Exception\CassandraException;
use Cassandra\Exception\ConnectionException;
use Cassandra\Exception\QueryException;
use Cassandra\Protocol\RequestFactory;

class Database {

	const POSTFIX_DUPLICATE_QUERY_VARIABLE = '_prefix';

	/**
	 * @var Cluster
	 */
	private $cluster;

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * Connection options
	 * @var array
	 */
	private $options = [
		'CQL_VERSION' => '3.0.0'
	];

	/**
	 * @var string
	 */
	private $keyspace;

	/**
	 * @var array
	 */
	protected $_batchQueryArray = [];

	/**
	 * @var int
	 */
	protected $_batchType = null;

	/**
	 * @var array
	 */
	protected $_preparedCqls = [];

	/**
	 * @param array $nodes
	 * @param string $keyspace
	 * @param array $options
	 */
	public function __construct(array $nodes, $keyspace = '', array $options = []) {
		$this->cluster = new Cluster($nodes);
		$this->connection = new Connection($this->cluster);
		$this->options = array_merge($this->options, $options);
		$this->keyspace = $keyspace;
	}

	/**
	 * Connect to database
	 * @throws Exception\ConnectionException
	 * @throws Exception\CassandraException
	 * @return bool
	 */
	public function connect() {
		if ($this->connection->isConnected()) return true;
		$this->connection->connect();
		$response = $this->connection->sendRequest(
			RequestFactory::startup($this->options)
		);
		
		if ($response instanceof Response\Error)
			throw new ConnectionException($response->getData());
		
		if ($response instanceof Response\Authenticate){
			$nodeOptions = $this->connection->getNode()->getOptions();
			$response = $this->connection->sendRequest(
				RequestFactory::credentials(
					$nodeOptions['username'],
					$nodeOptions['password']
				)
			);
		}
		
		if (!empty($this->keyspace)) $this->setKeyspace($this->keyspace);
		
		return true;
	}

	/**
	 * Disconnect to database
	 * @return bool
	 */
	public function disconnect() {
		if ($this->connection->isConnected()) return $this->connection->disconnect();
		return true;
	}

	/**
	 * Start transaction
	 */
	public function beginBatch($type = BatchTypeEnum::LOGGED) {
		if (!isset($this->_batchType)) {
			$this->_batchType = $type;
			$this->_batchQueryArray = [];
		}
	}

	/**
	 * Exec transaction
	 */
	public function applyBatch($consistency = ConsistencyEnum::CONSISTENCY_QUORUM, $serialConsistency = null) {
		if (!isset($this->_batchType))
			return false;
		$body = pack('C', $this->_batchType);
		$body .= pack('n', count($this->_batchQueryArray)) . implode('', $this->_batchQueryArray);

		$body .= RequestFactory::queryParameters($consistency, $serialConsistency);
		// exec
		$response = $this->connection->sendRequest(RequestFactory::batch($body));
		// cleaning
		$this->_batchType = null;
		$this->_batchQueryArray = [];

		//batch return void kind of RESULT, rows kind of RESULT if conditional
		if ($response instanceof Response\Error)
			throw new CassandraException($response->getData());
		return $response->getData();
	}

	/**
	 * @param string $cql
	 * @param array $values
	 */
	private function appendQueryToStack($cql, array $values) {
		$kind = empty($values) ? 0 : 1;
		$binary = pack('C', $kind);

		if ($kind == 0) {
			$binary .= pack('N', strlen($cql)) . $cql;
			// 0 of following values
			$binary .= pack('n', 0);
		}
		else {
			$preparedData = $this->_getPreparedData($cql);
			$binary .= pack('n', strlen($preparedData['id'])) . $preparedData['id'];
			$binary .= RequestFactory::valuesBinary($preparedData, $values);
		}
		$this->_batchQueryArray[] = $binary;
	}

	protected function _getPreparedData($cql) {
		if (!isset($this->_preparedCqls[$cql])) {
			$response = $this->connection->sendRequest(RequestFactory::prepare($cql));
			if (!$response instanceof Response\Result) {
				throw new QueryException($response->getData());
			}
			$this->_preparedCqls[$cql] = $response->getData();
		}
		return $this->_preparedCqls[$cql];
	}
	
	/**
	 * 
	 * @param string $cql
	 * @param array $values
	 * @param int $consistency
	 * @param int $serialConsistency
	 */
	public function exec($cql, array $values = [], $consistency = ConsistencyEnum::CONSISTENCY_QUORUM, $serialConsistency = null){
		if (empty($values)) {
			$response = $this->connection->sendRequest(RequestFactory::query($cql, $consistency, $serialConsistency));
		} else {
			$preparedData = $this->_getPreparedData($cql);
			$response = $this->connection->sendRequest(
					RequestFactory::execute($preparedData, $values, $consistency, $serialConsistency)
			);
		}
		
		if ($response instanceof Response\Error)
			throw new CassandraException($response->getData());
		
		return $response;
	}

	/**
	 * Send query into database
	 * @param string $cql
	 * @param array $values
	 * @param int $consistency
	 * @throws Exception\QueryException
	 * @throws Exception\CassandraException
	 * @return array|null
	 */
	public function query($cql, array $values = [], $consistency = ConsistencyEnum::CONSISTENCY_QUORUM, $serialConsistency = null) {
		if (isset($this->_batchType) && in_array(strtoupper(substr($cql, 0, 6)), ['INSERT', 'UPDATE', 'DELETE'])) {
			$this->appendQueryToStack($cql, $values);
			return true;
		}
		if (empty($values)) {
			$response = $this->connection->sendRequest(RequestFactory::query($cql, $consistency, $serialConsistency));
		} else {
			$preparedData = $this->_getPreparedData($cql);
			$response = $this->connection->sendRequest(
				RequestFactory::execute($preparedData, $values, $consistency, $serialConsistency)
			);
		}

		if ($response instanceof Response\Error) {
			throw new CassandraException($response->getData());
		}
		
		return $response->getData();
	}

	/**
	 * @param string $keyspace
	 * @throws Exception\CassandraException
	 */
	public function setKeyspace($keyspace) {
		$this->keyspace = $keyspace;
		if ($this->connection->isConnected()) {
			$response = $this->connection->sendRequest(
				RequestFactory::query("USE {$this->keyspace};", ConsistencyEnum::CONSISTENCY_QUORUM, null)
			);
			if ($response instanceof Response\Error)
				throw new CassandraException($response->getData());
		}
	}
}
