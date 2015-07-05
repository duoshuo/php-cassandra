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
            throw new Exception('Incoming value must be type of boolean.');

        $this->_value = $value;
    }
    
    public static function binary($value){
        return $value ? "\1" : "\0";
    }
    
    /**
     * @return bool
     */
    public static function parse($binary){
        return $binary !== "\0";
    }
}
