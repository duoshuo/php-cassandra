<?php
namespace Cassandra\Type;

class Bigint extends Base{
    
    /**
     * @param int|string $value
     * @throws Exception
     */
    public function __construct($value = null){
        if ($value === null)
            return;
    
        if (!is_numeric($value))
            throw new Exception('Incoming value must be type of int.');
    
        $this->_value = (int) $value;
    }
    
    public function getBinary(){
        if ($this->_binary === null){
            $higher = ($this->_value & 0xffffffff00000000) >>32;
            $lower = $this->_value & 0x00000000ffffffff;
            $this->_binary = pack('NN', $higher, $lower);
        }
        return $this->_binary;
    }
    
    /**
     * @return int
     */
    public function getValue(){
        if ($this->_value === null){
            $unpacked = unpack('N2', $this->_binary);
            $this->_value = $unpacked[1] << 32 | $unpacked[2];
        }
        return $this->_value;
    }
}
