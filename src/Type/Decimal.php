<?php
namespace Cassandra\Type;

class Decimal extends Base{

	protected $_scaleLen;

	/**
	 * @param int|string $value
	 * @param int|string $scaleLen
	 * @throws Exception
	 */
	public function __construct($value, $scaleLen = 0){
		if (!is_numeric($value) || !is_numeric($scaleLen))
			throw new Exception('Incoming value must be of type int.');

		$this->_value = (int)$value;
		$this->_scaleLen = max((int)$scaleLen, 0);
	}
	
	public function getBinary(){
		$highMap = 0xffffffff00000000;
		$lowMap = 0x00000000ffffffff;
		$higher = ($this->_value & $highMap) >> 32;
		$lower = $this->_value & $lowMap;
		return pack('NNN', $this->_scaleLen, $higher, $lower);
	}
}
