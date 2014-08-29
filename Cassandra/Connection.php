<?php
namespace Cassandra;
use Cassandra\Cluster\Node;
use Cassandra\Enum;
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
		socket_write($this->connection, $request);
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
			case Frame::OPCODE_ERROR:
				return new Response\Error($body);
		
			case Frame::OPCODE_READY:
				return new Response\Ready($body);
		
			case Frame::OPCODE_AUTHENTICATE:
				return new Response\Authenticate($body);
		
			case Frame::OPCODE_SUPPORTED:
				return new Response\Supported($body);
			
			case Frame::OPCODE_RESULT:
				return new Response\Result($body);
		
			case Frame::OPCODE_EVENT:
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
