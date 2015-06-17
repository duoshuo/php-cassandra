<?php
namespace Cassandra\Type;

class CollectionMap extends Base{
    /**
     * 
     * @var int|array
     */
    protected $_keyType;
    
    /**
     * 
     * @var int|array
     */
    protected $_valueType;
    
    /**
     * @param array $value
     * @param int|array $keyType
     * @param int|array $valueType
     * @throws Exception
     */
    public function __construct($value, $keyType, $valueType) {
        $this->_keyType = $keyType;
        $this->_valueType = $valueType;
    	if ($value === null)
            return;
    
        if (!is_array($value))
            throw new Exception('Incoming value must be of type array.');
        
        $this->_value = $value;
    }
    
    public function getBinary(){
        if ($this->_binary === null){
            $this->_binary = pack('N', count($this->_value));
            foreach($this->_value as $key => $value) {
                $keyPacked = Base::getTypeObject($this->_keyType, $key)->getBinary();
                $this->_binary .= pack('N', strlen($keyPacked)) . $keyPacked;
                $valuePacked = Base::getTypeObject($this->_valueType, $value)->getBinary();
                $this->_binary .= pack('N', strlen($valuePacked)) . $valuePacked;
            }
        }
        return $this->_binary;
    }
    
    public function getValue(){
        if ($this->_value === null){
            $this->_value = \Cassandra\Response\StreamReader::createFromData($this->_binary)->readMap($this->_keyType, $this->_valueType);
        }
        return $this->_value;
    }
}
