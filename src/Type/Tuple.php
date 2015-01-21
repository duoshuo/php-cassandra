<?php
namespace Cassandra\Type;

class Tuple extends Base{

	protected $_types;

	public function __construct($value, $types){
		if ((array)$value !== $value || (array)$types !== $types) throw new Exception('Incoming value must be of type array.');

		$this->_value = $value;
		$this->_types = $types;
	}

	public function getBinary(){
		$data = '';
		foreach ($this->_types as $key => $type) {
			$typeObject = Base::getTypeObject($type, $this->_value[$key]);

			if ($typeObject === null) {
				$data .= "\xff\xff\xff\xff";
			}
			else {
				$binary = $typeObject->getBinary();
				$data .= pack('N', strlen($binary)) . $binary;
			}
		}

		return $data;
	}
}
