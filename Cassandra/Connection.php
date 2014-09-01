<?php
namespace Cassandra;
use Cassandra\Protocol\Frame;

class Connection {

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
	protected $_preparedCqls = [];

	/**
	 * @var array
	 */
	private $nodes;
	
	/**
	 * @var Node
	 */
	private $node;
	
	/**
	 * @var resource
	 */
	private $connection;
	
	/**
	 * @var int
	 */
	protected $_lastStreamId = 0;
	
	/**
	 * 
	 * @var array
	 */
	protected $_statements = array();

	/**
	 * @param array $nodes
	 * @param string $keyspace
	 * @param array $options
	 */
	public function __construct(array $nodes, $keyspace = '', array $options = []) {
		$this->nodes = $nodes;
		$this->options = array_merge($this->options, $options);
		$this->keyspace = $keyspace;
	}
	
	/**
	 * @param string $host
	 */
	public function appendNode($host) {
		$this->nodes[] = $host;
	}
	
	/**
	 * @return Node
	 * @throws Connection\Exception
	 */
	public function getRandomNode() {
		if (empty($this->nodes)) throw new Connection\Exception('Node list is empty.');
		$nodeKey = array_rand($this->nodes);
		$node = $this->nodes[$nodeKey];
		try {
			if ((array)$node === $node) {
				$node = new Connection\Node($nodeKey, $node);
				unset($this->nodes[$nodeKey]);
			} else {
				$node = new Connection\Node($node);
				unset($this->nodes[$nodeKey]);
			}
		} catch (\InvalidArgumentException $e) {
			trigger_error($e->getMessage());
		}
	
		return $node;
	}
	
	protected function _connect() {
		try {
			$this->node = $this->getRandomNode();
			$this->connection = $this->node->getConnection();
		} catch (Connection\Exception $e) {
			$this->_connect();
		}
	}
	
	/**
	 * @return bool
	 */
	public function disconnect() {
		if ($this->connection === null)
			return true;
		
		return socket_shutdown($this->connection);
	}
	
	/**
	 * @return bool
	 */
	public function isConnected() {
		return $this->connection !== null;
	}
	
	/**
	 * @param $length
	 * @throws Connection\Exception
	 * @return string
	 */
	private function fetchData($length) {
		$data = socket_read($this->connection, $length);
		while (strlen($data) < $length) {
			$data .= socket_read($this->connection, $length);
		}
		
		$errorCode = socket_last_error($this->connection);
		
		if ($errorCode !== 0) {
			throw new Connection\Exception(socket_strerror($errorCode));
		}
	
		return $data;
	}
	
	/**
	 * 
	 * @param int $streamId
	 */
	public function getResponse($streamId = 0){
		do{
			$response = $this->_getResponse();
			
			if ($response->stream !== 0){
				$this->_statements[$response->stream]->setResponse($response);
				unset($this->_statements[$response->stream]);
			}
		}
		while($response->stream !== $streamId);
		
		return $response;
	}
	
	/**
	 *
	 * @throws Response\Exception
	 * @return \Cassandra\Response\DataStream
	 */
	protected function _getResponse() {
		$data = $this->fetchData(9);
		$data = unpack('Cversion/Cflags/nstream/Copcode/Nlength', $data);
		if ($data['length']) {
			$body = $this->fetchData($data['length']);
		} else {
			$body = '';
		}
		
		switch($data['opcode']){
			case Frame::OPCODE_ERROR:
				$response = new Response\Error($body);
				break;
			case Frame::OPCODE_READY:
				$response = new Response\Ready($body);
				break;
			case Frame::OPCODE_AUTHENTICATE:
				$response = new Response\Authenticate($body);
				break;
			case Frame::OPCODE_SUPPORTED:
				$response = new Response\Supported($body);
				break;
			case Frame::OPCODE_RESULT:
				$response = new Response\Result($body);
				break;
			case Frame::OPCODE_EVENT:
				$response = new Response\Event($body);
				break;
			default:
				throw new Response\Exception('Unknown response');
		}
		
		$response->stream = $data['stream'];
		
		return $response;
	}
	
	/**
	 * @return \Cassandra\Connection\Node
	 */
	public function getNode() {
		return $this->node;
	}

	/**
	 * Connect to database
	 * @throws Connection\Exception
	 * @throws Exception
	 * @return bool
	 */
	public function connect() {
		if ($this->connection !== null)
			return true;
		
		$this->_connect();
		
		socket_write($this->connection, new Request\Startup($this->options));
		$response = $this->getResponse();
		
		if ($response instanceof Response\Error)
			throw new Connection\Exception($response->getData());
		
		if ($response instanceof Response\Authenticate){
			$nodeOptions = $this->node->getOptions();
			socket_write($this->connection, 
				new Request\Credentials(
					$nodeOptions['username'],
					$nodeOptions['password']
				)
			);
			$response = $this->getResponse();
		}
		
		if (!empty($this->keyspace))
			$this->setKeyspace($this->keyspace);
		
		return true;
	}

	/**
	 * @param Request\Request $request
	 * @return \Cassandra\Response\DataStream
	 */
	public function sendRequest(Request\Request $request) {
		if ($this->connection === null)
			$this->connect();
	
		socket_write($this->connection, $request);
		return $this->getResponse();
	}
	
	public function asyncRequest(Request\Request $request) {
		if ($this->connection === null)
			$this->connect();
		
		$streamId = $this->_getNewStreamId();
		$request->setStream($streamId);
		socket_write($this->connection, $request);
		
		return $this->_statements[$streamId] = new Statement($this, $streamId);
	}
	
	public function prepare($cql) {
		if (!isset($this->_preparedCqls[$cql])) {
			if ($this->connection === null)
				$this->connect();
			
			socket_write($this->connection, new Request\Prepare($cql));
			$response = $this->getResponse();
			if (!$response instanceof Response\Result) {
				throw new Response\Exception($response->getData());
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
	 * @return Response\DataStream
	 */
	public function exec($cql, array $values = [], $consistency = Request\Request::CONSISTENCY_QUORUM, $serialConsistency = null){
		if ($this->connection === null)
			$this->connect();
		
		if (empty($values)) {
			$request = new Request\Query($cql, $consistency, $serialConsistency);
		} else {
			$preparedData = $this->prepare($cql);
			$request = new Request\Execute($preparedData, $values, $consistency, $serialConsistency);
		}
		socket_write($this->connection, $request);
		$response = $this->getResponse();
		
		if ($response instanceof Response\Error)
			throw new Exception($response->getData());
		
		return $response;
	}
	
	protected function _getNewStreamId(){
		return ++$this->_lastStreamId;
	}
	
	/**
	 * 
	 * @param string $cql
	 * @param array $values
	 * @param int $consistency
	 * @param int $serialConsistency
	 * @throws Exception
	 * @return Statement
	 */
	public function execAsync($cql, array $values = [], $consistency = Request\Request::CONSISTENCY_QUORUM, $serialConsistency = null){
		if ($this->connection === null)
			$this->connect();
	
		if (empty($values)) {
			$request = new Request\Query($cql, $consistency, $serialConsistency);
		} else {
			$preparedData = $this->prepare($cql);
			$request = new Request\Execute($preparedData, $values, $consistency, $serialConsistency);
		}
		
		$streamId = $this->_getNewStreamId();
		$request->setStream($streamId);
		socket_write($this->connection, $request);
		
		return $this->_statements[$streamId] = new Statement($this, $streamId);
	}

	/**
	 * @param string $keyspace
	 * @throws Exception
	 */
	public function setKeyspace($keyspace) {
		$this->keyspace = $keyspace;
		
		if ($this->connection === null)
			return;
		
		socket_write($this->connection, new Request\Query("USE {$this->keyspace};", Request\Request::CONSISTENCY_QUORUM, null));
		
		$response = $this->getResponse();
		
		if ($response instanceof Response\Error)
			throw new Exception($response->getData());
	}
}
