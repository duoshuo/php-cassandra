<?php
namespace Cassandra;
use Cassandra\Enum\ConsistencyEnum;
use Cassandra\Enum\OpcodeEnum;
use Cassandra\Exception\CassandraException;
use Cassandra\Exception\ConnectionException;
use Cassandra\Exception\QueryException;
use Cassandra\Protocol\RequestFactory;
use Cassandra\Protocol\Response\Rows;

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
	 * @var string
	 */
	private $batchQuery = '';

	/**
	 * @var array
	 */
	private $batchQueryData = [];

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
		$responseType = $response->getType();
		switch($responseType) {
			case OpcodeEnum::ERROR:
				throw new ConnectionException($response->getData());
				break;

			case OpcodeEnum::AUTHENTICATE:
				$nodeOptions = $this->connection->getNode()->getOptions();
				$response = $this->connection->sendRequest(
					RequestFactory::credentials(
						$nodeOptions['username'],
						$nodeOptions['password']
					)
				);
		}
		if ($responseType === OpcodeEnum::ERROR) throw new ConnectionException($response->getData());
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
	public function beginBatch() {
		if (!$this->batchQuery) {
			$this->batchQuery = "BEGIN BATCH\n";
			$this->batchQueryData = [];
		}
	}

	/**
	 * Exec transaction
	 */
	public function applyBatch($consistency = ConsistencyEnum::CONSISTENCY_QUORUM) {
		$this->batchQuery .= 'APPLY BATCH;';
		// exec
		$result = $this->query($this->batchQuery, $this->batchQueryData, $consistency);
		// cleaning
		$this->batchQuery = '';
		$this->batchQueryData = [];
		return $result;
	}

	/**
	 * @param string $cql
	 * @param array $values
	 */
	private function appendQueryToStack($cql, array $values) {
		$valuesModified = false;
		foreach($values as $key => $value) {
			if (is_string($key) && isset($this->batchQueryData[$key])) {
				$newFieldName = $key . self::POSTFIX_DUPLICATE_QUERY_VARIABLE;
				$cql = str_replace(":{$key}", ":{$newFieldName}", $cql);
				unset($values[$key]);
				$values[$newFieldName] = $value;
				$valuesModified = true;
			}
		}
		if ($valuesModified) {
			// Retry
			$this->appendQueryToStack($cql, $values);
		} else {
			$this->batchQuery .= rtrim($cql, ';') . ";\n";
			$this->batchQueryData = array_merge($this->batchQueryData, $values);
		}
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
	public function query($cql, array $values = [], $consistency = ConsistencyEnum::CONSISTENCY_QUORUM) {
		if ($this->batchQuery && in_array(substr($cql, 0, 6), ['INSERT', 'UPDATE', 'DELETE'])) {
			$this->appendQueryToStack($cql, $values);
			return true;
		}
		if (empty($values)) {
			$response = $this->connection->sendRequest(RequestFactory::query($cql, $consistency));
		} else {
			$response = $this->connection->sendRequest(RequestFactory::prepare($cql));
			$responseType = $response->getType();
			if ($responseType !== OpcodeEnum::RESULT)  {
				throw new QueryException($response->getData());
			} else {
				$preparedData = $response->getData();
			}
			$response = $this->connection->sendRequest(
				RequestFactory::execute($preparedData, $values, $consistency)
			);
		}

		if ($response->getType() === OpcodeEnum::ERROR) {
			throw new CassandraException($response->getData());
		} else {
			$data = $response->getData();
			if ($data instanceof Rows) {
				return $data->asArray();
			}
		}

		return !empty($data) ? $data : $response->getType() === OpcodeEnum::RESULT;
	}

	/**
	 * @param string $keyspace
	 * @throws Exception\CassandraException
	 */
	public function setKeyspace($keyspace) {
		$this->keyspace = $keyspace;
		if ($this->connection->isConnected()) {
			$response = $this->connection->sendRequest(
				RequestFactory::query("USE {$this->keyspace};", ConsistencyEnum::CONSISTENCY_QUORUM)
			);
			if ($response->getType() === OpcodeEnum::ERROR) throw new CassandraException($response->getData());
		}
	}
}