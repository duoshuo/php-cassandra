<?php
namespace Cassandra\Type;

class Uuid extends Base{
	
	public function __construct($value){
		if (!is_string($value)) throw new Exception('Incoming value must be of type string.');
	
		$this->value = $value;
	}
	
	public function getBinary(){
		return pack('H*', str_replace('-', '', $this->value));
	}
}
