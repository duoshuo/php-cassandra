<?php
namespace Cassandra\Type;

class Boolean extends Base{
	
	/**
	 * @param bool $value
	 * @throws Exception
	 */
	public function __construct($value){
		if (!is_bool($value))
			throw new Exception('Incoming value must be of type boolean.');

		$this->_value = $value;
	}
	
	public function getBinary(){
		return $this->_value ? chr(1) : chr(0);
	}
}
