<?php
namespace Cassandra;
use Cassandra\Protocol\Frame;

class Connection {

	/**
	 * Connection options
	 * @var array
	 */
	protected $options = [
		'CQL_VERSION' => '3.0.0'
	];

	/**
	 * @var string
	 */
	protected $keyspace;

	/**
	 * @var array|\Traversable
	 */
	protected $nodes;

	/**
	 * @var Node
	 */
	protected $node;
	
	/**
	 * @var resource
	 */
	protected $connection;
	
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
	 * @param array|\Traversable $nodes
	 * @param string $keyspace
	 * @param array $options
	 */
	public function __construct($nodes, $keyspace = '', array $options = []) {
		if (is_array($nodes))
			shuffle($nodes);
		
		$this->nodes = $nodes;
		$this->options = array_merge($this->options, $options);
		$this->keyspace = $keyspace;
		$this->_recycledStreams = new \SplQueue();
	}
	
	/**
	 * 
	 */
	protected function _connect() {
		foreach($this->nodes as $options){
			try {
				$this->node = new Connection\Node($options);
				$this->connection = $this->node->getConnection();
				return;
			} catch (Connection\Exception $e) {
				continue;
			}
		}
		
		throw new Exception("Unable to connect to all Cassandra nodes.");
	}
	
	/**
	 * @return bool
	 */
	public function disconnect() {
		if ($this->connection === null)
			return true;

		return fclose($this->connection);
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
	protected function fetchData($length) {
		$data = '';
		$remainder = $length;

		while($remainder > 0) {
			$readData = fread($this->connection,$remainder);

			if ($readData === false || stream_get_meta_data($this->connection)['timed_out'])
				throw new Connection\Exception(socket_strerror(socket_last_error()));

			$data .= $readData;
			$remainder -= strlen($readData);
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
		$header = unpack('Cversion/Cflags/nstream/Copcode/Nlength', $this->fetchData(9));
		if ($header['length']) {
			$body = $this->fetchData($header['length']);
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
		return $this->node;
	}

	/**
	 * Connect to database
	 * @throws Exception
	 * @return bool
	 */
	public function connect() {
		if ($this->connection !== null)
			return true;
		
		$this->_connect();
		
		$response = $this->syncRequest(new Request\Startup($this->options));
		
		if ($response instanceof Response\Authenticate){
			$nodeOptions = $this->node->getOptions();
			$this->syncRequest(new Request\AuthResponse($nodeOptions['username'], $nodeOptions['password']));
		}
		
		if (!empty($this->keyspace))
			$this->syncRequest(new Request\Query("USE {$this->keyspace};"));
		
		return true;
	}

	/**
	 * @param Request\Request $request
	 * @throws Exception
	 * @return Response\Response
	 */
	public function syncRequest(Request\Request $request) {
		if ($this->connection === null)
			$this->connect();

		fwrite($this->connection,$request);
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
		fwrite($this->connection,$request);
		
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
		$request = new Request\Execute($queryId, $values, $consistency, $options);
		
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
		$request = new Request\Execute($queryId, $values, $consistency, $options);
		
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
