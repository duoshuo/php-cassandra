<?php
namespace Cassandra\Type;

class Double extends Base{
	
	public function __construct($value){
		if (!is_double($value)) throw new Exception('Incoming value must be of type double.');
	
		$this->value = $value;
	}
	
	public function getBinary(){
		return strrev(pack('d', $this->value));
	}
}
