<?php
namespace Cassandra\Protocol;

class Request {

	/**
	 * @var string
	 */
	private $binary;

	/**
	 * @var int
	 */
	private $type;

	/**
	 * @param int $type OpcodeEnum::* constants
	 * @param string $binary
	 */
	public function __construct($type, $binary = '') {
		$this->binary = $binary;
		$this->type = $type;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->binary;
	}
}