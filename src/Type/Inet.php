<?php
namespace Cassandra\Type;

class Inet extends Base{
	
	public function __construct($value){
		if (!is_string($value)) throw new Exception('Incoming value must be of type string.');
		
		$this->value = $value;
	}
	
	public function getBinary(){
		return inet_pton($this->value);
	}
}
