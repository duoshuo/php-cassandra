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
	 * @param array $value
	 * @param int|array $keyType
	 * @param int|array $valueType
	 * @throws Exception
	 */
	public function __construct($value, $keyType, $valueType) {
		if (!is_array($value))
			throw new Exception('Incoming value must be of type array.');
		
		$this->_value = $value;
		$this->_keyType = $keyType;
		$this->_valueType = $valueType;
	}
	
	public function getBinary(){
		$data = pack('N', count($this->_value));
		foreach($this->_value as $key => $value) {
			$keyPacked = Base::getTypeObject($this->_keyType, $key)->getBinary();
			$data .= pack('N', strlen($keyPacked)) . $keyPacked;
			$valuePacked = Base::getTypeObject($this->_valueType, $value)->getBinary();
			$data .= pack('N', strlen($valuePacked)) . $valuePacked;
		}
		return $data;
	}
}
