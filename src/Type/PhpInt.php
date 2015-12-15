<?php
namespace Cassandra\Type;

class PhpInt extends Base{

    /**
     * @param int|string $value
     * @throws Exception
     */
    public function __construct($value = null){
        if ($value === null)
            return;
        
        if (!is_numeric($value))
            throw new Exception('Incoming value must be type of int.');
    
        $this->_value = (int) $value;
    }
    
    public static function binary($value){
        return pack('N', $value);
    }
    
    /**
     * @param string $binary
     * @return int
     */
    public static function parse($binary){
        $bits = PHP_INT_SIZE * 8 - 32;
        return unpack('N', $binary)[1] << $bits >> $bits;
    }
}
