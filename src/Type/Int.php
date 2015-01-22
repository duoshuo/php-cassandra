<?php
namespace Cassandra\Type;

class Int extends Base{

	/**
	 * @param int|string $value
	 * @throws Exception
	 */
	public function __construct($value){
		if (!is_numeric($value))
			throw new Exception('Incoming value must be of type int.');
	
		$this->_value = (int) $value;
	}
	
	public function getBinary(){
		return pack('N', $this->_value);
	}
}
