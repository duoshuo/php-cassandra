<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;
use Cassandra\Type;

class Request implements Frame{

    const CONSISTENCY_ANY = 0x0000;
    const CONSISTENCY_ONE = 0x0001;
    const CONSISTENCY_TWO = 0x0002;
    const CONSISTENCY_THREE = 0x0003;
    const CONSISTENCY_QUORUM = 0x0004;
    const CONSISTENCY_ALL = 0x0005;
    const CONSISTENCY_LOCAL_QUORUM = 0x0006;
    const CONSISTENCY_EACH_QUORUM = 0x0007;
    const CONSISTENCY_SERIAL = 0x0008;
    const CONSISTENCY_LOCAL_SERIAL = 0x0009;
    const CONSISTENCY_LOCAL_ONE = 0x000A;
    
    /**
     * @var int
     */
    protected $version = 0x03;
    
    /**
     * @var int
     */
    protected $opcode;
    
    /**
     * @var int
     */
    protected $stream = 0;
    
    /**
     * @var int
     */
    protected $flags = 0;
    
    /**
     * @param int $opcode
     * @param int $stream
     * @param int $flags
     */
    public function __construct($opcode, $stream = 0, $flags = 0) {
        $this->opcode = $opcode;
        $this->stream = $stream;
        $this->flags = $flags;
    }
        
    public function getVersion(){
        return $this->version;
    }
    
    public function getFlags(){
        return $this->flags;
    }
    
    public function getStream(){
        return $this->stream;
    }
    
    public function getOpcode(){
        return $this->opcode;
    }
    
    public function getBody(){
        return '';
    }
    
    public function setStream($stream){
        $this->stream = $stream;
    }
    
    /**
     * @return string
     */
    public function __toString(){
        $body = $this->getBody();
        return pack(
                'CCnCN',
                $this->version,
                $this->flags,
                $this->stream,
                $this->opcode,
                strlen($body)
        ) . $body;
    }
    
    /**
     * 
     * @param array $values
     * @throws Type\Exception
     * @return string
     */
    public static function valuesBinary(array $values, $namesForValues = false) {
        $valuesBinary = pack('n', count($values));
        
        $index = 0;
        foreach($values as $name => $value) {
            switch (true) {
                case $value instanceof Type\Base:
                    $binary = $value->getBinary();
                    break;
                case $value === null:
                    $binary = null;
                    break;
                case is_int($value):
                    $binary = pack('N', $value);
                    break;
                case is_string($value):
                    $binary = $value;
                    break;
                case is_bool($value):
                    $binary = $value ? chr(1) : chr(0);
                    break;
                default:
                    throw new Type\Exception('Unknown type.');
            }

            if ($namesForValues){
                $valuesBinary .= pack('n', strlen($name)) . strtolower($name);
            }
            else{
                /**
                 * @see https://github.com/duoshuo/php-cassandra/issues/29
                 */
                if ($index++ !== $name)
                    throw new Type\Exception('$values should be an sequential array, associative array given.  Or you can set "names_for_values" option to true.');
            }

            $valuesBinary .= $binary === null
                ? "\xff\xff\xff\xff"
                : pack('N', strlen($binary)) . $binary;
        }
        
        return $valuesBinary;
    }
    
    /**
     * 
     * @param array $values
     * @param array $columns
     * @return array
     */
    public static function strictTypeValues(array $values, array $columns) {
        $strictTypeValues = [];
        foreach($columns as $index => $column) {
            $key = array_key_exists($column['name'], $values) ? $column['name'] : $index;
            
            if (!isset($values[$key])){
                $strictTypeValues[$key] = null;
            }
            elseif($values[$key] instanceof Type\Base){
                $strictTypeValues[$key] = $values[$key];
            }
            else{
                $strictTypeValues[$key] = Type\Base::getTypeObject($column['type'], $values[$key]);
            }
        }
        
        return $strictTypeValues;
    }
    
    /**
     * 
     * @param int $consistency
     * @param array $values
     * @param array $options
     * @return string
     */
    public static function queryParameters($consistency, array $values = [], array $options = []){
        $flags = 0;
        $optional = '';
        
        if (!empty($values)) {
            $flags |= Query::FLAG_VALUES;
            $optional .= Request::valuesBinary($values, !empty($options['names_for_values']));
        }

        if (!empty($options['skip_metadata']))
            $flags |= Query::FLAG_SKIP_METADATA;

        if (isset($options['page_size'])) {
            $flags |= Query::FLAG_PAGE_SIZE;
            $optional .= pack('N', $options['page_size']);
        }

        if (isset($options['paging_state'])) {
            $flags |= Query::FLAG_WITH_PAGING_STATE;
            $optional .= pack('N', strlen($options['paging_state'])) . $options['paging_state'];
        }

        if (isset($options['serial_consistency'])) {
            $flags |= Query::FLAG_WITH_SERIAL_CONSISTENCY;
            $optional .= pack('n', $options['serial_consistency']);
        }

        if (isset($options['default_timestamp'])) {
            $flags |= Query::FLAG_WITH_DEFAULT_TIMESTAMP;
            $optional .= Type\Bigint::binary($options['default_timestamp']);
        }

        if (!empty($options['names_for_values']))
            $flags |= Query::FLAG_WITH_NAMES_FOR_VALUES;

        return pack('n', $consistency) . pack('C', $flags) . $optional;
    }
}
