<?php
namespace Cassandra;
use Cassandra\Protocol\Frame;

class Connection {

	/**
	 * Connection options
	 * @var array
	 */
	protected $_options = [
		'CQL_VERSION' => '3.0.0'
	];

	/**
	 * @var string
	 */
	protected $_keyspace;

	/**
	 * @var array|\Traversable
	 */
	protected $_nodes;

	/**
	 * @var Connection\Socket|Connection\Stream
	 */
	protected $_node;
	
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
	 * 
	 * @var \SplQueue
	 */
	protected $_recycledStreams;

	/**
	 * @var int
	 */
	protected $_consistency = Request\Request::CONSISTENCY_ONE;

	/**
	 * @param array|\Traversable $nodes
	 * @param string $keyspace
	 * @param array $options
	 */
	public function __construct($nodes, $keyspace = '', array $options = []) {
		if (is_array($nodes))
			shuffle($nodes);
		
		$this->_nodes = $nodes;
		$this->_options = array_merge($this->_options, $options);
		$this->_keyspace = $keyspace;
		$this->_recycledStreams = new \SplQueue();
	}
	
	/**
	 * @throws Exception
	 */
	protected function _connect() {
		foreach($this->_nodes as $options){
			try {
				if (is_string($options)){
					$pos = strpos($options, ':');
					$options = $pos === false
						? ['host' => $options,]
						: ['host' => substr($options, 0, $pos), 'port'	=> (int) substr($options, $pos + 1),];
				}
				
				if (isset($options['class'])){
					$className = $options['class'];
					$this->_node = new $className($options);
				}
				else{
					$this->_node = new Connection\Socket($options);
				}
				return;
			} catch (Exception $e) {
				continue;
			}
		}
		
		throw new Exception("Unable to connect to all Cassandra nodes.");
	}
	
	/**
	 * @return bool
	 */
	public function disconnect() {
		if ($this->_node === null)
			return true;
		
		return $this->_node->close();
	}
	
	/**
	 * @return bool
	 */
	public function isConnected() {
		return $this->_node !== null;
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
	 * @throws Response\Exception
	 * @return Response\Response
	 */
	public function getResponse($streamId = 0){
		do{
			$response = $this->_getResponse();
		}
		while($response->getStream() !== $streamId);
		
		return $response;
	}
	
	/**
	 *
	 * @throws Response\Exception
	 * @return Response\Response
	 */
	protected function _getResponse() {
		$header = unpack('Cversion/Cflags/nstream/Copcode/Nlength', $this->_node->read(9));
		if ($header['length']) {
			$body = $this->_node->read($header['length']);
		} else {
			$body = '';
		}
		
		static $responseClassMap = [
			Frame::OPCODE_ERROR			=> 'Cassandra\Response\Error',
			Frame::OPCODE_READY			=> 'Cassandra\Response\Ready',
			Frame::OPCODE_AUTHENTICATE	=> 'Cassandra\Response\Authenticate',
			Frame::OPCODE_SUPPORTED		=> 'Cassandra\Response\Supported',
			Frame::OPCODE_RESULT		=> 'Cassandra\Response\Result',
			Frame::OPCODE_EVENT			=> 'Cassandra\Response\Event',
			Frame::OPCODE_AUTH_SUCCESS	=> 'Cassandra\Response\AuthSuccess',
		];
		
		if (!isset($responseClassMap[$header['opcode']]))
			throw new Response\Exception('Unknown response');
		
		$responseClass = $responseClassMap[$header['opcode']];
		$response = new $responseClass($header, $body);
		
		if ($header['stream'] !== 0){
			if (isset($this->_statements[$header['stream']])){
				$this->_statements[$header['stream']]->setResponse($response);
				unset($this->_statements[$header['stream']]);
				$this->_recycledStreams->enqueue($header['stream']);
			}
			elseif ($response instanceof Response\Event){
				$this->trigger($response);
			}
		}
		
		return $response;
	}
	
	/**
	 * Wait until all statements received response.
	 */
	public function flush(){
		while(!empty($this->_statements)){
			$this->_getResponse();
		}
	}
	
	/**
	 * @return Connection\Node
	 */
	public function getNode() {
		return $this->_node;
	}

	/**
	 * Connect to database
	 * @throws Exception
	 * @return bool
	 */
	public function connect() {
		if ($this->_node !== null)
			return true;
		
		$this->_connect();
		
		$response = $this->syncRequest(new Request\Startup($this->_options));
		
		if ($response instanceof Response\Authenticate){
			$nodeOptions = $this->_node->getOptions();
			$this->syncRequest(new Request\AuthResponse($nodeOptions['username'], $nodeOptions['password']));
		}
		
		if (!empty($this->_keyspace))
			$this->syncRequest(new Request\Query("USE {$this->_keyspace};"));
		
		return true;
	}

	/**
	 * @param Request\Request $request
	 * @throws Exception
	 * @return Response\Response
	 */
	public function syncRequest(Request\Request $request) {
		if ($this->_node === null)
			$this->connect();
		
		$this->_node->write($request->__toString());
		
		$response = $this->getResponse();
		
		if ($response instanceof Response\Error)
			throw $response->getException();
		
		return $response;
	}
	
	/**
	 * 
	 * @param Request\Request $request
	 * @return Statement
	 */
	public function asyncRequest(Request\Request $request) {
		if ($this->_node === null)
			$this->connect();
		
		$streamId = $this->_getNewStreamId();
		$request->setStream($streamId);
		
		$this->_node->write($request->__toString());
		
		return $this->_statements[$streamId] = new Statement($this, $streamId);
	}

	/**
	 * 
	 * @throws Exception
	 * @return int
	 */
	protected function _getNewStreamId(){
		if ($this->_lastStreamId < 32767)
			return ++$this->_lastStreamId;
		
		while ($this->_recycledStreams->isEmpty()){
			$this->_getResponse();
		}
		
		return $this->_recycledStreams->dequeue();
	}
	
	/***** Shorthand Methods ******/
	/**
	 * 
	 * @param string $cql
	 * @throws Exception
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
	 * @throws Exception
	 * @return Response\Response
	 */
	public function executeSync($queryId, array $values = [], $consistency = null, array $options = []){
		$request = new Request\Execute($queryId, $values, $consistency === null ? $this->_consistency : $consistency, $options);
		
		return $this->syncRequest($request);
	}
	
	/**
	 * 
	 * @param string $queryId
	 * @param array $values
	 * @param int $consistency
	 * @param array $options
	 * @throws Exception
	 * @return Statement
	 */
	public function executeAsync($queryId, array $values = [], $consistency = null, array $options = []){
		$request = new Request\Execute($queryId, $values, $consistency === null ? $this->_consistency : $consistency, $options);
		
		return $this->asyncRequest($request);
	}
	
	/**
	 * 
	 * @param string $cql
	 * @param array $values
	 * @param int $consistency
	 * @param array $options
	 * @throws Exception
	 * @return Response\Response
	 */
	public function querySync($cql, array $values = [], $consistency = null, array $options = []){
		$request = new Request\Query($cql, $values, $consistency === null ? $this->_consistency : $consistency, $options);

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
		$request = new Request\Query($cql, $values, $consistency === null ? $this->_consistency : $consistency, $options);

		return $this->asyncRequest($request);
	}
	
	/**
	 * @param string $keyspace
	 * @throws Exception
	 * @return Response\Result
	 */
	public function setKeyspace($keyspace) {
		$this->_keyspace = $keyspace;
		
		if ($this->_node === null)
			return;
		
		return $this->syncRequest(new Request\Query("USE {$this->_keyspace};"));
	}
	
	/**
	 * @param int  $consistency
	 */
	public function setConsistency($consistency){
		$this->_consistency = $consistency;
	}
}
