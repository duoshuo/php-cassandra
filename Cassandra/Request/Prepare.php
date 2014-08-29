<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;

class Prepare extends Request{

	public function __construct($cql) {
		$body = pack('N', strlen($cql)) . $cql;
		parent::__construct(Frame::OPCODE_BATCH, $body);
	}
	
}