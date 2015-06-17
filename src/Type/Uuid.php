<?php
namespace Cassandra\Type;

class Uuid extends Base{

    /**
     * @param string $value
     * @throws Exception
     */
    public function __construct($value = null){
        if ($value === null)
            return;
        
        if (!is_string($value))
            throw new Exception('Incoming value must be of type string.');
    
        $this->_value = $value;
    }
    
    public function getBinary(){
        if ($this->_binary === null)
            $this->_binary = pack('H*', str_replace('-', '', $this->_value));
        return $this->_binary;
    }
    
    public function getValue(){
        if ($this->_value === null){
            $unpacked = unpack('n8', $this->_binary);
            
            $this->_value = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', $unpacked[1], $unpacked[2], $unpacked[3], $unpacked[4], $unpacked[5], $unpacked[6], $unpacked[7], $unpacked[8]);
        }
        return $this->_value;
    }
}
