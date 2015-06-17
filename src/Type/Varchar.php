<?php
namespace Cassandra\Type;

class Varchar extends Base{

    /**
     * @param string $value
     * @throws Exception
     */
    public function __construct($value = null){
        if ($value === null)
            return;
        
        $this->_binary = $this->_value = $value;
    }
    
    public function getBinary(){
        return $this->_binary;
    }
    
    public function getValue(){
        return $this->_value;
    }
}
