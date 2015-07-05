<?php
namespace Cassandra\Type;

class Uuid extends Base{

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
        return pack('H*', str_replace('-', '', $value));
    }
    
    public static function parse($binary){
        $unpacked = unpack('n8', $binary);
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', $unpacked[1], $unpacked[2], $unpacked[3], $unpacked[4], $unpacked[5], $unpacked[6], $unpacked[7], $unpacked[8]);
    }
}
