<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;
use Cassandra\Protocol;
use Cassandra\Connection;
use Cassandra\Type;

class Batch extends Request{
    const TYPE_LOGGED = 0;
    const TYPE_UNLOGGED = 1;
    const TYPE_COUNTER = 2;
    
    protected $opcode = Frame::OPCODE_BATCH;
    
    /**
     * @var array
     */
    protected $_queryArray = [];

    /**
     * @var int
     */
    protected $_batchType;
    
    /**
     * 
     * @var int
     */
    protected $_consistency;
    
    /**
     * 
     * @var array
     */
    protected $_options;
    
    /**
     * 
     * @param string $type
     * @param string $consistency
     * @param array $options
     */
    public function __construct($type = null, $consistency = null, $options = []) {
        $this->_batchType = $type === null ? Batch::TYPE_LOGGED : $type;
        $this->_consistency = $consistency === null ? Request::CONSISTENCY_ONE : $consistency;
        $this->_options = $options;
    }

    /**
     * Exec transaction
     */
    public function getBody() {
        return pack('C', $this->_batchType)
            . pack('n', count($this->_queryArray)) . implode('', $this->_queryArray)
            . self::queryParameters($this->_consistency, $this->_options);
    }

    /**
     * @param string $cql
     * @param array $values
     * @return self
     */
    public function appendQuery($cql, array $values = []) {
        $binary = pack('C', 0);
    
        $binary .= pack('N', strlen($cql)) . $cql;
        $binary .= Request::valuesBinary($values, !empty($this->_options['names_for_values']));
        
        $this->_queryArray[] = $binary;
        
        return $this;
    }
    
    /**
     * 
     * @param string $queryId
     * @param array $values
     * @return self
     */
    public function appendQueryId($queryId, array $values = []) {
        $binary = pack('C', 1);
        
        $binary .= pack('n', strlen($queryId)) . $queryId;
        $binary .= Request::valuesBinary($values, !empty($this->_options['names_for_values']));
        
        $this->_queryArray[] = $binary;
        
        return $this;
    }
    
    /**
     *
     * @param int $consistency
     * @param array $options
     * @return string
     */
    public static function queryParameters($consistency, array $options = [], array $values = []){
        $flags = 0;
        $optional = '';
    
        if (isset($options['serial_consistency'])) {
            $flags |= Query::FLAG_WITH_SERIAL_CONSISTENCY;
            $optional .= pack('n', $options['serial_consistency']);
        }
    
        if (isset($options['default_timestamp'])) {
            $flags |= Query::FLAG_WITH_DEFAULT_TIMESTAMP;
            $optional .= Type\Bigint::binary($options['default_timestamp']);
        }
    
        if (!empty($options['names_for_values'])){
            /**
             * @link https://github.com/duoshuo/php-cassandra/issues/40
             */
            throw new \Cassandra\Exception('NAMES_FOR_VALUES in batch request seems never work in Cassandra 2.1.x.  Keep NAMES_FOR_VALUES flag false to avoid this bug.');
            
            $flags |= Query::FLAG_WITH_NAMES_FOR_VALUES;
        }
        
        return pack('n', $consistency) . pack('C', $flags) . $optional;
    }
}
