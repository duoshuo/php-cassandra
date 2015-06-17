<?php
namespace Cassandra\Type;

class Tuple extends Base{

    protected $_types;

    /**
     * @param array $value
     * @param array $types
     * @throws Exception
     */
    public function __construct($value, $types){
        $this->_types = $types;
        
    	if ($value === null)
            return;
        
        if (!is_array($value) || !is_array($types))
            throw new Exception('Incoming value must be of type array.');

        $this->_value = $value;
    }

    public function getBinary(){
        if ($this->_binary === null){
            $this->_binary = '';
            foreach ($this->_types as $key => $type) {
                $typeObject = $this->_value[$key] instanceof Base
                    ? $this->_value[$key]
                    : Base::getTypeObject($type, $this->_value[$key]);
    
                if ($typeObject === null) {
                    $this->_binary .= "\xff\xff\xff\xff";
                }
                else {
                    $binary = $typeObject->getBinary();
                    $this->_binary .= pack('N', strlen($binary)) . $binary;
                }
            }
        }

        return $this->_binary;
    }
    
    public function getValue(){
        if ($this->_value === null){
            $this->_value = \Cassandra\Response\StreamReader::createFromData($this->_binary)->readTuple($this->_types);
        }
        return $this->_value;
    }
}
