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
		if ((array)$values !== $values) throw new Exception('Incoming value must be of type array.');
		
		$this->value = $value;
		$this->_valueType = $valueType;
	}
	
	public function getBinary(){
		$data = pack('n', count($this->value));
		foreach($this->value as $value) {
			$itemPacked = Base::getTypeObject($this->_valueType, $value)->getBinary();
			$data .= pack('n', strlen($itemPacked)) . $itemPacked;
		}
		return $data;
	}
}
