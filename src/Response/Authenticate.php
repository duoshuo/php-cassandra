<?php
namespace Cassandra\Response;
use Cassandra\Protocol\Frame;

class Authenticate extends Response {
	public function getData(){
		return unpack('n', $this->getBody())[1];
	}
}
