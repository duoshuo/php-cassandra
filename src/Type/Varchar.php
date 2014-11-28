<?php
namespace Cassandra\Type;

class Varchar extends Base{
	
	public function __construct($value){
		$this->_value = (string)$value;
	}
	
	public function getBinary(){
		return $this->_value;
	}
}
