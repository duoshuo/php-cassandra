<?php
namespace Cassandra\Type;

class Boolean extends Base{
    
    /**
     * @param bool $value
     * @throws Exception
     */
    public function __construct($value = null){
        if ($value === null)
            return;
        
        if (!is_bool($value))
            throw new Exception('Incoming value must be of type boolean.');

        $this->_value = $value;
    }
    
    public function getBinary(){
        if ($this->_binary === null)
            $this->_binary = $this->_value ? "\1" : "\0";
        return $this->_binary;
    }
    
    /**
     * @return bool
     */
    public function getValue(){
        if ($this->_value === null){
            $this->_value = $this->_binary !== "\0";
        }
        return $this->_value;
    }
}
