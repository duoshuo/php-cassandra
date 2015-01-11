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
			if ($value instanceof Base){
				$binary = $value->getBinary();
			}
			elseif ($value === null){
				$binary = null;
			}
			elseif (is_int($value)){
				$binary = pack('N', $value);
			}
			elseif (is_string($value)){
				$binary = $value;
			}
			elseif (is_bool($value)){
				$binary = $value ? chr(1) : chr(0);
			}
			else{
				throw new Exception('Unknown type.');
			}

			if ($binary === null)
				$data .= pack('N', 0xffffffff);
			else
				$data .= pack('N', strlen($binary)) . $binary;
		}
		return $data;
	}
}
