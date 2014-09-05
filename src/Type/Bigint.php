<?php
namespace Cassandra\Type;

class Bigint extends Base{
	
	public function getBinary(){
		$highMap = 0xffffffff00000000;
		$lowMap = 0x00000000ffffffff;
		$higher = ($this->_value & $highMap) >>32;
		$lower = $this->_value & $lowMap;
		return pack('NN', $higher, $lower);
	}
}
