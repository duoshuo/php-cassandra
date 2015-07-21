<?php
namespace Cassandra\Type;

class CollectionMap extends Base{
    
    /**
     * @param array $value
     * @param array $definition
     * @throws Exception
     */
    public function __construct($value, array $definition) {
        $this->_definition = $definition;
        if ($value === null)
            return;
    
        if (!is_array($value))
            throw new Exception('Incoming value must be type of array.');
        
        $this->_value = $value;
    }
    
    /**
     * 
     * @param array $value
     * @param array $definition [$keyType, $valueType]
     * @return string
     */
    public static function binary($value, array $definition){
        list($keyType, $valueType) = $definition;
        $binary = pack('N', count($value));
        foreach($value as $key => $val) {
            $keyPacked = $key instanceof Base
                ? $key->getBinary()
                : Base::getBinaryByType($keyType, $key);
            
            $valuePacked = $val instanceof Base
                ? $val->getBinary()
                : Base::getBinaryByType($valueType, $val);
            
            $binary .= pack('N', strlen($keyPacked)) . $keyPacked;
            $binary .= pack('N', strlen($valuePacked)) . $valuePacked;
        }
        return $binary;
    }
    
    public static function parse($binary, array $definition){
        return (new \Cassandra\Response\StreamReader($binary))->readMap($definition);
    }
}
