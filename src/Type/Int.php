<?php
namespace Cassandra\Type;

class Int extends Base{
	
	public function __construct($value){
		if (!is_int($value)) throw new Exception('Incoming value must be of type int.');
	
		$this->value = $value;
	}
	
	public function getBinary(){
		return pack('N', $this->value);
	}
}
