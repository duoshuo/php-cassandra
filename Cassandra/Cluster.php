<?php
namespace Cassandra;
use Cassandra\Cluster\Node;
use Cassandra\Exception\ClusterException;

class Cluster {

	/**
	 * @var array
	 */
	private $nodes;

	/**
	 * @param array $nodes
	 */
	public function __construct(array $nodes = []) {
		$this->nodes = $nodes;
	}

	/**
	 * @param string $host
	 */
	public function appendNode($host) {
		$this->nodes[] = $host;
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws Exception\ClusterException
	 * @return Node|null
	 */
	public function getRandomNode() {
		if (empty($this->nodes)) throw new ClusterException('Node list is empty.');
		shuffle($this->nodes);
		while(!empty($this->nodes)) {
			$host = end($this->nodes);
			try {
				if ((array)$host === $host) {
					$nodeKey = key($this->nodes);
					$node = new Node($nodeKey, $host);
					unset($this->nodes[$nodeKey]);
				} else {
					$node = new Node($host);
					unset($this->nodes[$host]);
				}
				break;
			} catch (\InvalidArgumentException $e) {
				trigger_error($e->getMessage());
			}
		}

		if (empty($node)) throw new \InvalidArgumentException('Incorrect connection parameters for all nodes.');

		return $node;
	}
}