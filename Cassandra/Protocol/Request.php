<?php
namespace Cassandra\Protocol;

class Request extends Frame{

	protected $version = 0x03;

	/**
	 * @param int $type Frame::* constants
	 * @param string $binary
	 */
	public function __construct($type, $binary = '') {
		parent::__construct($this->version, $type, $binary);
	}
}
