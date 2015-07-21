<?php
namespace Cassandra\Type;

class Tuple extends Base{

    /**
     * @param array $value
     * @param array $definition
     * @throws Exception
     */
    public function __construct($value, array $definition){
        $this->_definition = $definition;
        
        if ($value === null)
            return;
        
        if (!is_array($value))
            throw new Exception('Incoming value must be type of array.');

        $this->_value = $value;
    }

    public static function binary($value, array $definition){
        $binary = '';
        foreach ($definition as $key => $type) {
            if ($value[$key] === null) {
                $binary .= "\xff\xff\xff\xff";
            }
            else {
                $valueBinary = $value[$key] instanceof Base
                    ? $value[$key]->getBinary()
                    : Base::getBinaryByType($type, $value[$key]);
                
                $binary .= pack('N', strlen($valueBinary)) . $valueBinary;
            }
        }
        
        return $binary;
    }
    
    /**
     * 
     * @param string $binary
     * @param array $definition
     * @return array
     */
    public static function parse($binary, array $definition){
        return (new \Cassandra\Response\StreamReader($binary))->readTuple($definition);
    }
}
