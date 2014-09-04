<?php
namespace Cassandra\Type;

class Decimal extends Base{
	
	public function __construct($value){
		if (!is_numeric($value)) throw new Exception('Incoming value must be numeric.');
		
		$this->value = $value;
	}
	
	public function getBinary(){
		$scaleLen = strlen(strstr($this->value, '.'));
		if ($scaleLen) {
			$scaleLen--;
			$this->value = str_replace('.', '', $this->value);
		}
		$highMap = 0xffffffff00000000;
		$lowMap = 0x00000000ffffffff;
		$higher = ($this->value & $highMap) >> 32;
		$lower = $this->value & $lowMap;
		return pack('NNN', $scaleLen, $higher, $lower);
	}
}
