<?php
namespace Cassandra\Type;

class Decimal extends Base{

    /**
     * @param string $value
     * @throws Exception
     */
    public function __construct($value = null){
        if (!is_numeric($value))
            throw new Exception('Incoming value must be numeric string.');

        $this->_value = $value;
    }
    
    public function getBinary(){
        if ($this->_binary === null){
            $pos = strpos($this->_value, '.');
            $scaleLen = $pos === false ? 0 : strlen($this->_value) - $pos - 1;
            $higher = ($this->_value & 0xffffffff00000000) >> 32;
            $lower = $this->_value & 0x00000000ffffffff;
            $this->_binary = pack('NNN', $this->_scaleLen, $higher, $lower);
        }
        return $this->_binary;
    }
    
    /**
     * @return string
     */
    public function getValue(){
        if ($this->_value === null){
            $unpacked = unpack('N1scale/C*', $this->_binary);
            $valueByteLen = $length - 4;
            $value = 0;
            for ($i = 1; $i <= $valueByteLen; ++$i)
                $value |= $unpacked[$i] << (($valueByteLen - $i) * 8);
            $shift = (\PHP_INT_SIZE - $valueByteLen) * 8;
            $value = $value << $shift >> $shift;
            $this->_value = substr($value, 0, -$unpacked['scale']) . '.' . substr($value, -$unpacked['scale']);
        }
        return $this->_value;
    }
}
