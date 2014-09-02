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
	protected $_statements = [];

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
	
	/**
	 * 
	 */
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
		$data = '';
		$receivedBytes = 0;
		do{
			$data .= socket_read($this->connection, $length - $receivedBytes);
			$receivedBytes = strlen($data);
		}
		while($receivedBytes < $length);
		
		$errorCode = socket_last_error($this->connection);
		
		if ($errorCode !== 0) {
			throw new Connection\Exception(socket_strerror($errorCode));
		}
	
		return $data;
	}
	
	/**
	 * 
	 * @param Response\Event $response
	 */
	public function trigger($response){
	}
	
	/**
	 * 
	 * @param int $streamId
	 * @return Response\Response
	 */
	public function getResponse($streamId = 0){
		do{
			$response = $this->_getResponse();
			$responseStream = $response->getStream();
			if ($responseStream !== 0){
				if (isset($this->_statements[$responseStream])){
					$this->_statements[$responseStream]->setResponse($response);
					unset($this->_statements[$responseStream]);
				}
				elseif ($response instanceof Response\Event){
					$this->trigger($response);
				}
			}
		}
		while($responseStream !== $streamId);
		
		return $response;
	}
	
	/**
	 *
	 * @throws Response\Exception
	 * @return Response\Response
	 */
	protected function _getResponse() {
		$header = unpack('Cversion/Cflags/nstream/Copcode/Nlength', $this->fetchData(9));
		if ($header['length']) {
			$body = $this->fetchData($header['length']);
		} else {
			$body = '';
		}
		
		static $responseClassMap = array(
			Frame::OPCODE_ERROR			=> 'Cassandra\Response\Error',
			Frame::OPCODE_READY			=> 'Cassandra\Response\Ready',
			Frame::OPCODE_AUTHENTICATE	=> 'Cassandra\Response\Authenticate',
			Frame::OPCODE_SUPPORTED		=> 'Cassandra\Response\Supported',
			Frame::OPCODE_RESULT		=> 'Cassandra\Response\Result',
			Frame::OPCODE_EVENT			=> 'Cassandra\Response\Event',
			Frame::OPCODE_AUTH_SUCCESS	=> 'Cassandra\Response\AuthSuccess',
		);
		
		if (!isset($responseClassMap[$header['opcode']]))
			throw new Response\Exception('Unknown response');
		
		$responseClass = $responseClassMap[$header['opcode']];
		$response = new $responseClass($header, $body);
		
		return $response;
	}
	
	/**
	 * @return Connection\Node
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
			$this->syncRequest(new Request\AuthResponse($nodeOptions['username'], $nodeOptions['password']));
		}
		
		if (!empty($this->keyspace))
			$this->setKeyspace($this->keyspace);
		
		return true;
	}

	/**
	 * @param Request\Request $request
	 * @return Response\Response
	 */
	public function syncRequest(Request\Request $request) {
		if ($this->connection === null)
			$this->connect();
	
		socket_write($this->connection, $request);
		$response = $this->getResponse();
		
		if ($response instanceof Response\Error)
			throw new Exception($response->getData());
		
		return $response;
	}
	
	/**
	 * 
	 * @param Request\Request $request
	 * @return Statement
	 */
	public function asyncRequest(Request\Request $request) {
		if ($this->connection === null)
			$this->connect();
		
		$streamId = $this->_getNewStreamId();
		$request->setStream($streamId);
		socket_write($this->connection, $request);
		
		return $this->_statements[$streamId] = new Statement($this, $streamId);
	}

	/**
	 * 
	 * @throws Exception
	 * @return int
	 */
	protected function _getNewStreamId(){
		$looped = false;
		do{
			++$this->_lastStreamId;
				
			if ($this->_lastStreamId === 32768){
				if ($looped)
					throw new Exception('Too many streams.');
	
				$this->_lastStreamId = 1;
				$looped = true;
			}
		}
		while(isset($this->_statements[$this->_lastStreamId]));
		return $this->_lastStreamId;
	}
	
	/***** Shorthand Methods ******/
	/**
	 * 
	 * @param string $cql
	 * @return array
	 */
	public function prepare($cql) {
		$response = $this->syncRequest(new Request\Prepare($cql));
		
		return $response->getData();
	}
	
	/**
	 * 
	 * @param string $queryId
	 * @param array $values
	 * @param int $consistency
	 * @param array $options
	 * @return Response\Response
	 */
	public function executeSync($queryId, array $values = [], $consistency = null, array $options = []){
		$request = new Request\Execute($queryId, $values, $consistency, $options);
		
		return $this->syncRequest($request);
	}
	
	/**
	 * 
	 * @param string $queryId
	 * @param array $values
	 * @param int $consistency
	 * @param array $options
	 * @return Statement
	 */
	public function executeAsync($queryId, array $values = [], $consistency = null, array $options = []){
		$request = new Request\Execute($queryId, $values, $consistency, $options);
		
		return $this->asyncRequest($request);
	}
	
	/**
	 * 
	 * @param string $cql
	 * @param array $values
	 * @param int $consistency
	 * @param array $options
	 * @return Response\Response
	 */
	public function querySync($cql, array $values = [], $consistency = null, array $options = []){
		$request = new Request\Query($cql, $values, $consistency, $options);
		
		return $this->syncRequest($request);
	}
	
	/**
	 *
	 * @param string $cql
	 * @param array $values
	 * @param int $consistency
	 * @param array $options
	 * @throws Exception
	 * @return Statement
	 */
	public function queryAsync($cql, array $values = [], $consistency = null, array $options = []){
		$request = new Request\Query($cql, $values, $consistency, $options);
		
		return $this->asyncRequest($request);
	}
	
	/**
	 * @param string $keyspace
	 * @throws Exception
	 * @return Response\Result
	 */
	public function setKeyspace($keyspace) {
		$this->keyspace = $keyspace;
		
		if ($this->connection === null)
			return;
		
		return $this->syncRequest(new Request\Query("USE {$this->keyspace};"));
	}
}
