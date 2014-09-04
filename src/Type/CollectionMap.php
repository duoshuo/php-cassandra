<?php
namespace Cassandra\Type;

class CollectionMap extends Base{
	/**
	 * 
	 * @var int|array
	 */
	protected $_keyType;
	
	/**
	 * 
	 * @var int|array
	 */
	protected $_valueType;
	
	/**
	 * @param array $values
	 * @param int|array $keyType
	 * @param int|array $valueType
	 * @throws Exception
	 */
	public function __construct($value, $keyType, $valueType) {
		if ((array)$value !== $value)
			throw new Exception('Incoming value must be of type array.');
		
		$this->value = $value;
		$this->_keyType = $keyType;
		$this->_valueType = $valueType;
	}
	
	public function getBinary(){
		$data = pack('n', count($this->value));
		foreach($this->value as $key => $value) {
			$keyPacked = Base::getTypeObject($this->_keyType, $key)->getBinary();
			$data .= pack('n', strlen($keyPacked)) . $keyPacked;
			$valuePacked = Base::getTypeObject($this->_valueType, $value)->getBinary();
			$data .= pack('n', strlen($valuePacked)) . $valuePacked;
		}
		return $data;
	}
}
