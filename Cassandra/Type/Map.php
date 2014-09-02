<?php
namespace Cassandra\Type;

class Map extends Base{
	
	/**
	 * @param array $values
	 * @param array $keyType
	 * @param array $valueType
	 * @throws Exception
	 */
	public function __construct($values, array $keyType, array $valueType) {
		if ((array)$values !== $values)
			throw new Exception('Incoming value must be of type array.');
		
		
	}
	
	public function getBinary(){
		$data = pack('n', count($this->values));
		foreach($this->values as $key => $value) {
			$keyPacked = Base::getTypeObject($keyType, $key)->getBinary();
			$data .= pack('n', strlen($keyPacked)) . $keyPacked;
			$valuePacked = Base::getTypeObject($valueType, $value)->getBinary();
			$data .= pack('n', strlen($valuePacked)) . $valuePacked;
		}
		return $data;
	}
}
