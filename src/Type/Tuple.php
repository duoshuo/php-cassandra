<?php
namespace Cassandra\Type;

class Tuple extends Base{

	public function __construct($value){
		if ((array)$value !== $value) throw new Exception('Incoming value must be of type array.');

		$this->_value = $value;
	}

	public function getBinary(){
		$data = '';
		foreach ($this->_value as $value) {
			switch(true){
				case $value instanceof Base:
					$binary = $value->getBinary();
					break;
				case $value === null:
					$binary = null;
					break;
				case is_int($value):
					$binary = pack('N', $value);
					break;
				case is_string($value):
					$binary = $value;
					break;
				case is_bool($value):
					$binary = $value ? chr(1) : chr(0);
					break;
				default:
					throw new Exception('Unknown type.');
			}

			$data .= $binary === null
				? "\xff\xff\xff\xff"
				: pack('N', strlen($binary)) . $binary;
		}
		return $data;
	}
}
