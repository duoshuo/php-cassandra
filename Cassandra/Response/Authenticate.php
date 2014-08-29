<?php
namespace Cassandra\Response;

class Authenticate extends DataStream {
	public function getData(){
		return unpack('n', $this->data)[1];
	}
}
