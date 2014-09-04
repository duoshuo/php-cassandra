<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;

class Options extends Request{
	
	protected $opcode = Frame::OPCODE_OPTIONS;
	
	/**
	 * OPTIONS
	 *
	 * Asks the server to return what STARTUP options are supported. The body of an
	 * OPTIONS message should be empty and the server will respond with a SUPPORTED
	 * message.
	 */
	public function __construct() {
	}
}