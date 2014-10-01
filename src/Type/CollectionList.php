<?php
namespace Cassandra\Type;

class CollectionList extends Base{
	
	/**
	 * 
	 * @var int|array
	 */
	protected $_valueType;
	
	/**
	 * 
	 * @param array $values
	 * @param int|array $valueType
	 * @throws Exception
	 */
	public function __construct($value, $valueType) {
		if ((array)$value !== $value) throw new Exception('Incoming value must be of type array.');
		
		$this->_value = $value;
		$this->_valueType = $valueType;
	}
	
	public function getBinary(){
		$data = pack('N', count($this->_value));
		foreach($this->_value as $value) {
			$itemPacked = Base::getTypeObject($this->_valueType, $value)->getBinary();
			$data .= pack('N', strlen($itemPacked)) . $itemPacked;
		}
		return $data;
	}
}
