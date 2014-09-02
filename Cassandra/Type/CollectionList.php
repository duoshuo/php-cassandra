<?php
namespace Cassandra\Type;

class CollectionList extends Base{
	
	public function __construct($values, array $valueType) {
		if ((array)$values !== $values) throw new Exception('Incoming value must be of type array.');
		
	}
	
	public function getBinary(){
		$data = pack('n', count($this->values));
		foreach($this->values as $value) {
			$itemPacked = Base::getTypeObject($valueType, $value)->getBinary();
			$data .= pack('n', strlen($itemPacked)) . $itemPacked;
		}
		return $data;
	}
}
