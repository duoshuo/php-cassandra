<?php
namespace Cassandra\Type;

class Custom extends Base{
    
    /**
     * 
     * @param string $value
     * @param array $definition
     * @throws Exception
     */
    public function __construct($value, array $definition){
        if ($value === null)
            return;
        $this->_definition = $definition;
        
        if (!is_string($value))
            throw new Exception('Incoming value must be type of string.');
    
        $this->_value = $value;
    }
    
    public static function binary($value, array $definition){
        return pack('n', strlen($value)) . $value;
    }
    
    public static function parse($binary, array $definition){
        $length = unpack('n', substr($binary, 0, 2))[1];
        return substr($binary, 2, $length);
    }
}
