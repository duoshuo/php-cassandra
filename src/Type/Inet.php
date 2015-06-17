<?php
namespace Cassandra\Type;

class Inet extends Base{

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
            $this->_binary = inet_pton($this->_value);
        return $this->_binary;
    }
    
    public function getValue(){
        if ($this->_value === null){
            $this->_value = inet_ntop($this->_binary);
        }
        return $this->_value;
    }
}
