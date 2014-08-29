<?php
namespace Cassandra;
use Cassandra\Cluster\Node;
use Cassandra\Enum;
use Cassandra\Enum\OpcodeEnum;
use Cassandra\Exception\ConnectionException;
use Cassandra\Response;
use Cassandra\Protocol\Frame;
use Cassandra\Protocol\Request;

class Connection {

	/**
	 * @var Cluster
	 */
	private $cluster;

	/**
	 * @var Node
	 */
	private $node;

	/**
	 * @var resource
	 */
	private $connection;

	/**
	 * @param Cluster $cluster
	 */
	public function __construct(Cluster $cluster) {
		$this->cluster = $cluster;
	}

	public function connect() {
		try {
			$this->node = $this->cluster->getRandomNode();
			$this->connection = $this->node->getConnection();
		} catch (ConnectionException $e) {
			$this->connect();
		}
	}

	/**
	 * @return bool
	 */
	public function disconnect() {
		return socket_shutdown($this->connection);
	}

	/**
	 * @return bool
	 */
	public function isConnected() {
		return $this->connection !== null;
	}

	/**
	 * @param Request $request
	 * @return \Cassandra\Response\DataStream
	 */
	public function sendRequest(Request $request) {
		$frame = new Frame($request->getVersion(), $request->getType(), $request);
		socket_write($this->connection, $frame);
		return $this->getResponse();
	}

	/**
	 * @param $length
	 * @throws Exception\ConnectionException
	 * @return string
	 */
	private function fetchData($length) {
		$data = socket_read($this->connection, $length);
		while (strlen($data) < $length) {
			$data .= socket_read($this->connection, $length);
		}
		if (socket_last_error($this->connection) == 110) {
			throw new ConnectionException('Connection timed out');
		}

		return $data;
	}

	/**
	 * 
	 * @throws Response\Exception
	 * @return \Cassandra\Response\DataStream
	 */
	private function getResponse() {
		$data = $this->fetchData(9);
		$data = unpack('Cversion/Cflags/nstream/Copcode/Nlength', $data);
		if ($data['length']) {
			$body = $this->fetchData($data['length']);
		} else {
			$body = '';
		}
		
		switch($data['opcode']){
			case OpcodeEnum::ERROR:
				return new Response\Error($body);
		
			case OpcodeEnum::READY:
				return new Response\Ready($body);
		
			case OpcodeEnum::AUTHENTICATE:
				return new Response\Authenticate($body);
		
			case OpcodeEnum::SUPPORTED:
				return new Response\Supported($body);
			
			case OpcodeEnum::RESULT:
				return new Response\Result($body);
		
			case OpcodeEnum::EVENT:
				return new Response\Event($body);
		
			default:
				throw new Response\Exception('Unknown response');
		}
	}

	/**
	 * @return \Cassandra\Cluster\Node
	 */
	public function getNode() {
		return $this->node;
	}
}
