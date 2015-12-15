<?php
namespace Cassandra\Type;

abstract class Base{
    
    const CUSTOM = 0x0000;
    const ASCII = 0x0001;
    const BIGINT = 0x0002;
    const BLOB = 0x0003;
    const BOOLEAN = 0x0004;
    const COUNTER = 0x0005;
    const DECIMAL = 0x0006;
    const DOUBLE = 0x0007;
    const FLOAT = 0x0008;
    const INT = 0x0009;
    const TEXT = 0x000A;        // deprecated in Protocol v3
    const TIMESTAMP = 0x000B;
    const UUID = 0x000C;
    const VARCHAR = 0x000D;
    const VARINT = 0x000E;
    const TIMEUUID = 0x000F;
    const INET = 0x0010;
    const COLLECTION_LIST = 0x0020;
    const COLLECTION_MAP = 0x0021;
    const COLLECTION_SET = 0x0022;
    const UDT = 0x0030;
    const TUPLE = 0x0031;
    
    public static $typeClassMap = [
        self::ASCII     => 'Cassandra\Type\Ascii',
        self::VARCHAR   => 'Cassandra\Type\Varchar',
        self::TEXT      => 'Cassandra\Type\Varchar',  // deprecated in Protocol v3
        self::VARINT    => 'Cassandra\Type\Varint',
        self::BIGINT    => 'Cassandra\Type\Bigint',
        self::COUNTER   => 'Cassandra\Type\Counter',
        self::TIMESTAMP => 'Cassandra\Type\Timestamp',
        self::BLOB      => 'Cassandra\Type\Blob',
        self::BOOLEAN   => 'Cassandra\Type\Boolean',
        self::DECIMAL   => 'Cassandra\Type\Decimal',
        self::DOUBLE    => 'Cassandra\Type\Double',
        self::FLOAT     => 'Cassandra\Type\PhpFloat',
        self::INT       => 'Cassandra\Type\PhpInt',
        self::UUID      => 'Cassandra\Type\Uuid',
        self::TIMEUUID  => 'Cassandra\Type\Timeuuid',
        self::INET      => 'Cassandra\Type\Inet',
        self::COLLECTION_LIST => 'Cassandra\Type\CollectionList',
        self::COLLECTION_SET  => 'Cassandra\Type\CollectionSet',
        self::COLLECTION_MAP  => 'Cassandra\Type\CollectionMap',
        self::UDT       => 'Cassandra\Type\UDT',
        self::TUPLE     => 'Cassandra\Type\Tuple',
        self::CUSTOM    => 'Cassandra\Type\Custom',
    ];

    /**
     * 
     * @var array
     */
    protected $_definition;

    /**
     * 
     * @var mixed
     */
    protected $_value;
    
    /**
     * @var string
     */
    protected $_binary;
    
    /**
     * 
     * @param mixed $value
     */
    public function __construct($value = null){
        $this->_value = $value;
    }
    
    /**
     * 
     * @param string $binary
     * @return self
     */
    public function setBinary($binary){
        $this->_binary = $binary;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getBinary(){
        if ($this->_binary === null)
            $this->_binary = static::binary($this->_value, $this->_definition);
        
        return $this->_binary;
    }
    
    /**
     * @return mixed
     */
    public function getValue(){
        if ($this->_value === null && $this->_binary !== null)
            $this->_value = static::parse($this->_binary, $this->_definition);
        
        return $this->_value;
    }
    
    /**
     * @return string
     */
    public function __toString(){
        return (string) $this->_value;
    }
    
    public static function getBinaryByType($dataType, $value){
        if (is_array($dataType)){
            if (!isset($dataType['definition']))
                throw new Exception('Since v0.7, collection types should have "definition" directive.');
            $class = self::$typeClassMap[$dataType['type']];
            return $class::binary($value, $dataType['definition']);
        }
        else{
            $class = self::$typeClassMap[$dataType];
            return $class::binary($value);
        }
    }
    
    /**
     * 
     * @param int|array $dataType
     * @param mixed $value
     * @throws Exception
     * @return Base|null
     */
    public static function getTypeObject($dataType, $value) {
        if ($value === null)
            return null;
        
        if (!is_array($dataType)){
            $class = self::$typeClassMap[$dataType];
            return new $class($value);
        }
        else{
            $class = self::$typeClassMap[$dataType['type']];
            return new $class($value, $dataType['definition']);
        }
    }
}
