<?php
namespace Cassandra\Protocol;

class Request {

	protected $version = 0x03;

	/**
	 * @var string
	 */
	private $binary;

	/**
	 * @var int
	 */
	private $type;

	/**
	 * @param int $type Frame::* constants
	 * @param string $binary
	 */
	public function __construct($type, $binary = '') {
		$this->binary = $binary;
		$this->type = $type;
	}

	public function getVersion(){
		return $this->version;
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