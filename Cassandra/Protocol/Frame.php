<?php
namespace Cassandra\Protocol;

class Frame {

	/**
	 * @var int
	 */
	private $version;

	/**
	 * @var int
	 */
	private $opcode;

	/**
	 * @var string
	 */
	private $body;

	/**
	 * @var int
	 */
	private $stream;

	/**
	 * @var int
	 */
	private $flags;

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
			'CCcCN',
			$this->version,
			$this->flags,
			$this->stream,
			$this->opcode,
			strlen($this->body)
		) . $this->body;
	}
}