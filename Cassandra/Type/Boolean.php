<?php
namespace Cassandra\Type;

class Boolean extends Base{
	
	public function __construct($value){
		if (!is_bool($value)) throw new Exception('Incoming value must be of type string.');
	
	}
	
	public function getBinary(){
		return $this->value ? chr(1) : chr(0);
	}
}
