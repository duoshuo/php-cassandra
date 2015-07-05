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
            throw new Exception('Incoming value must be type of string.');
        
        $this->_binary = $this->_value = $value;
    }
    
    public static function binary($value){
        return $value;
    }
    
    public static function parse($binary){
        return $binary;
    }
}
