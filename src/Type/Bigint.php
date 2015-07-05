<?php
namespace Cassandra\Type;

class Bigint extends Base{
    
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
        $higher = ($value & 0xffffffff00000000) >>32;
        $lower = $value & 0x00000000ffffffff;
        return pack('NN', $higher, $lower);
    }
    
    /**
     * @return int
     */
    public static function parse($binary){
        $unpacked = unpack('N2', $binary);
        return $unpacked[1] << 32 | $unpacked[2];
    }
}
