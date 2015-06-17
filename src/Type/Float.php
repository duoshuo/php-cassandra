<?php
namespace Cassandra\Type;

class Float extends Base{
    /**
     * @param double $value
     * @throws Exception
     */
    public function __construct($value = null){
        if ($value === null)
            return;
        
        if (!is_double($value))
            throw new Exception('Incoming value must be type of double.');
    
        $this->_value = $value;
    }
    
    public function getBinary(){
        if ($this->_binary === null)
            $this->_binary = strrev(pack('f', $this->_value));
        return $this->_binary;
    }
    
    public function getValue(){
        if ($this->_value === null){
            $this->_value = unpack('f', strrev($this->_binary))[1];
        }
        return $this->_value;
    }
}
