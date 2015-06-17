<?php
namespace Cassandra\Type;

class Blob extends Base{

    /**
     * @param string $value
     * @throws Exception
     */
    public function __construct($value = null){
        if ($value === null)
            return;
        
        if (!is_string($value))
            throw new Exception('Incoming value must be of type string.');
        
        $this->_binary = $this->_value = $value;
    }
    
    public function getBinary(){
        return $this->_binary;
    }
    
    public function getValue(){
        return $this->_value;
    }
}
