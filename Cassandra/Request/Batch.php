<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;

class Batch extends Request{
	const TYPE_LOGGED = 0;
	const TYPE_UNLOGGED = 1;
	const TYPE_COUNTER = 2;
	
	public function __construct($body) {
		parent::__construct(Frame::OPCODE_BATCH, $body);
	}
	
}
