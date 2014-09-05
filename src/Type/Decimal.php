<?php
namespace Cassandra\Type;

class Decimal extends Base{
	
	public function __construct($value){
		if (!is_numeric($value)) throw new Exception('Incoming value must be numeric.');
		
		$this->_value = $value;
	}
	
	public function getBinary(){
		$scaleLen = strlen(strstr($this->_value, '.'));
		if ($scaleLen) {
			$scaleLen--;
			$this->_value = str_replace('.', '', $this->_value);
		}
		$highMap = 0xffffffff00000000;
		$lowMap = 0x00000000ffffffff;
		$higher = ($this->_value & $highMap) >> 32;
		$lower = $this->_value & $lowMap;
		return pack('NNN', $scaleLen, $higher, $lower);
	}
}
