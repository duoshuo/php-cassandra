<?php
namespace Cassandra\Type;

class PhpFloat extends Base{
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
    
    public static function binary($value){
        return strrev(pack('f', $value));
    }
    
    public static function parse($binary){
        return unpack('f', strrev($binary))[1];
    }
}
