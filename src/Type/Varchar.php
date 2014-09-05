<?php
namespace Cassandra\Type;

class Varchar extends Base{
	
	public function __construct($value){
		if (!is_string($value)) throw new Exception('Incoming value must be of type string.');
	
		$this->_value = $value;
	}
	
	public function getBinary(){
		return (string)$this->_value;
	}
}
