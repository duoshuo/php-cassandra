<?php
namespace Cassandra\Type;

class Int extends Base{

    /**
     * @param int|string $value
     * @throws Exception
     */
    public function __construct($value = null){
        if ($value === null)
            return;
        
        if (!is_numeric($value))
            throw new Exception('Incoming value must be of type int.');
    
        $this->_value = (int) $value;
    }
    
    public function getBinary(){
        if ($this->_binary === null)
            $this->_binary = pack('N', $this->_value);
        return $this->_binary;
    }
    
    /**
     * @return int
     */
    public function getValue(){
        if ($this->_value === null){
            $this->_value = unpack('N', $this->_binary)[1] << 32 >> 32;
        }
        return $this->_value;
    }
}
