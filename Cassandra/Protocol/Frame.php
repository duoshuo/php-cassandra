<?php
namespace Cassandra\Protocol;

class Frame {

	const OPCODE_ERROR = 0x00;
	const OPCODE_STARTUP = 0x01;
	const OPCODE_READY = 0x02;
	const OPCODE_AUTHENTICATE = 0x03;
	const OPCODE_CREDENTIALS = 0x04;
	const OPCODE_OPTIONS = 0x05;
	const OPCODE_SUPPORTED = 0x06;
	const OPCODE_QUERY = 0x07;
	const OPCODE_RESULT = 0x08;
	const OPCODE_PREPARE = 0x09;
	const OPCODE_EXECUTE = 0x0A;
	const OPCODE_REGISTER = 0x0B;
	const OPCODE_EVENT = 0x0C;
	const OPCODE_BATCH = 0x0D;
	const OPCODE_AUTH_CHALLENGE = 0x0E;
	const OPCODE_AUTH_RESPONSE = 0x0F;
	const OPCODE_AUTH_SUCCESS = 0x10;
	
	/**
	 * @var int
	 */
	protected $version;

	/**
	 * @var int
	 */
	protected $opcode;

	/**
	 * @var string
	 */
	protected $body;

	/**
	 * @var int
	 */
	protected $stream;

	/**
	 * @var int
	 */
	protected $flags;

	/**
	 * @param int $version
	 * @param int $opcode
	 * @param string $body
	 * @param int $stream
	 * @param int $flags
	 */
	public function __construct($version, $opcode, $body, $stream = 0, $flags = 0) {
		$this->version = $version;
		$this->opcode = $opcode;
		$this->body = $body;
		$this->stream = $stream;
		$this->flags = $flags;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return pack(
			'CCnCN',
			$this->version,
			$this->flags,
			$this->stream,
			$this->opcode,
			strlen($this->body)
		) . $this->body;
	}
}
