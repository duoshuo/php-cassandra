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
            throw new Exception('Incoming value must be type of string.');
        
        $this->_value = $value;
    }
    
    public static function binary($value){
        return inet_pton($value);
    }
    
    public static function parse($binary){
        return inet_ntop($binary);
    }
}
