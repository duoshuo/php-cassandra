<?php
namespace Cassandra\Type;

class Blob extends Base{

	/**
	 * @param string $value
	 * @throws Exception
	 */
	public function __construct($value){
		if (!is_string($value))
			throw new Exception('Incoming value must be of type string.');
		
		$this->_value = $value;
	}
	
	public function getBinary(){
		return pack('N', strlen($this->_value)) . $this->_value;
	}
}
