<?php
namespace Cassandra\Type;

class Blob extends Base{
	
	public function __construct($value){
		if (!is_string($value)) throw new Exception('Incoming value must be of type string.');
		
		$this->value = $value;
	}
	
	public function getBinary(){
		return pack('N', strlen($this->value)) . $this->value;
	}
}
