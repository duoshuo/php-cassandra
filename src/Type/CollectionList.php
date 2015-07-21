<?php
namespace Cassandra\Type;

class CollectionList extends Base{
    
    /**
     * 
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
    
    public static function binary($value, array $definition){
        $binary = pack('N', count($value));
        list($valueType) = $definition;
        foreach($value as $val) {
            $itemPacked = Base::getBinaryByType($valueType, $val);
            $binary .= pack('N', strlen($itemPacked)) . $itemPacked;
        }
        return $binary;
    }
    
    public static function parse($binary, array $definition){
        return (new \Cassandra\Response\StreamReader($binary))->readList($definition);
    }
}
