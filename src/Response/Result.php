<?php 
namespace Cassandra\Response;
use Cassandra\Protocol\Frame;
use Cassandra\Type;

class Result extends Response {
    const VOID = 0x0001;
    const ROWS = 0x0002;
    const SET_KEYSPACE = 0x0003;
    const PREPARED = 0x0004;
    const SCHEMA_CHANGE = 0x0005;
    
    const ROWS_FLAG_GLOBAL_TABLES_SPEC = 0x0001;
    const ROWS_FLAG_HAS_MORE_PAGES = 0x0002;
    const ROWS_FLAG_NO_METADATA = 0x0004;
    
    /**
     * 
     * @var int
     */
    protected $_kind;
    
    /**
     * 
     * @var array
     */
    protected $_metadata;
    
    /**
     * 
     * @var string
     */
    protected $_rowClass;

    /**
     * @return \SplFixedArray|string|array|null
     */
    public function getData() {
        $this->_stream->offset(4);
        switch($this->getKind()) {
            case self::VOID:
                return null;
    
            case self::ROWS:
                return $this->fetchAll();
    
            case self::SET_KEYSPACE:
                return $this->_stream->readString();
    
            case self::PREPARED:
                return [
                    'id' => $this->_stream->readString(),
                    'metadata' => $this->_readMetadata(),
                    'result_metadata' => $this->_readMetadata(),
                ];
    
            case self::SCHEMA_CHANGE:
                return [
                    'change' => $this->_stream->readString(),
                    'keyspace' => $this->_stream->readString(),
                    'table' => $this->_stream->readString()
                ];
        }
    
        return null;
    }
    
    public function getKind(){
        if ($this->_kind === null){
            $this->_stream->offset(0);
            $this->_kind = $this->_stream->readInt();
        }
        
        return $this->_kind;
    }

    /**
     * 
     * @param array $metadata
     * @return self
     */
    public function setMetadata(array $metadata) {
        $this->_metadata = $metadata;
        
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    public function getMetadata(){
        if (empty($this->_metadata)){
            $this->_stream->offset(4);
            $this->_metadata = $this->_readMetadata();
        }
        
        return $this->_metadata;
    }
    
    /**
     * 
     * @param string $rowClass
     * @return self
     */
    public function setRowClass($rowClass) {
        $this->_rowClass = $rowClass;
    
        return $this;
    }
    
    /**
     * Return metadata
     * @return array
     */
    protected function _readMetadata() {
        $metadata = [
            'flags' => $this->_stream->readInt(),
            'columns_count' => $this->_stream->readInt(),
        ];
        $flags = $metadata['flags'];

        if ($flags & self::ROWS_FLAG_HAS_MORE_PAGES)
            $metadata['page_state'] = $this->_stream->readBytes();

        if (!($flags & self::ROWS_FLAG_NO_METADATA)) {
            $metadata['columns'] = [];
            
            if ($flags & self::ROWS_FLAG_GLOBAL_TABLES_SPEC) {
                $keyspace = $this->_stream->readString();
                $tableName = $this->_stream->readString();

                for ($i = 0; $i < $metadata['columns_count']; ++$i) {
                    $metadata['columns'][] = [
                        'keyspace' => $keyspace,
                        'tableName' => $tableName,
                        'name' => $this->_stream->readString(),
                        'type' => $this->_stream->readType()
                    ];
                }
            }
            else {
                for ($i = 0; $i < $metadata['columns_count']; ++$i) {
                    $metadata['columns'][] = [
                        'keyspace' => $this->_stream->readString(),
                        'tableName' => $this->_stream->readString(),
                        'name' => $this->_stream->readString(),
                        'type' => $this->_stream->readType()
                    ];
                }
            }
        }
    
        return $metadata;
    }
    
    /**
     *
     * @param string $rowClass
     * @throws Exception
     * @return \SplFixedArray
     */
    public function fetchAll($rowClass = null){
        if ($this->getKind() !== self::ROWS){
            throw new Exception('Unexpected Response: ' . $this->getKind());
        }
        $this->_stream->offset(4);
        $this->_metadata = $this->_metadata ? array_merge($this->_metadata, $this->_readMetadata()) : $this->_readMetadata();

        if (!isset($this->_metadata['columns']))
            throw new Exception('Missing Result Metadata');

        $rowCount = $this->_stream->readInt();
        $rows = new \SplFixedArray($rowCount);
        
        if ($rowClass === null)
            $rowClass = $this->_rowClass;
    
        for ($i = 0; $i < $rowCount; ++$i) {
            $data = [];
    
            foreach ($this->_metadata['columns'] as $column)
                $data[$column['name']] = $this->_stream->readValue($column['type']);
                
            $rows[$i] = $rowClass === null ? $data : new $rowClass($data);
        }
    
        return $rows;
    }

    /**
     *
     * @param int $index
     * @throws Exception
     * @return \SplFixedArray
     */
    public function fetchCol($index = 0){
        if ($this->getKind() !== self::ROWS){
            throw new Exception('Unexpected Response: ' . $this->getKind());
        }
        $this->_stream->offset(4);
        $this->_metadata = $this->_metadata ? array_merge($this->_metadata, $this->_readMetadata()) : $this->_readMetadata();

        if (!isset($this->_metadata['columns']))
            throw new Exception('Missing Result Metadata');

        $rowCount = $this->_stream->readInt();
    
        $array = new \SplFixedArray($rowCount);
    
        for($i = 0; $i < $rowCount; ++$i){
            foreach($this->_metadata['columns'] as $j => $column){
                $value = $this->_stream->readValue($column['type']);
    
                if ($j == $index)
                    $array[$i] = $value;
            }
        }
    
        return $array;
    }
    
    /**
     * 
     * @throws Exception
     * @return array
     */
    public function fetchPairs(){
        if ($this->getKind() !== self::ROWS){
            throw new Exception('Unexpected Response: ' . $this->getKind());
        }
        $this->_stream->offset(4);
        $this->_metadata = $this->_metadata ? array_merge($this->_metadata, $this->_readMetadata()) : $this->_readMetadata();
    
        if (!isset($this->_metadata['columns']))
            throw new Exception('Missing Result Metadata');
    
        $rowCount = $this->_stream->readInt();
    
        $map = [];
    
        for($i = 0; $i < $rowCount; ++$i){
            foreach($this->_metadata['columns'] as $j => $column){
                $value = $this->_stream->readValue($column['type']);
    
                if ($j === 0){
                    $key = $value;
                }
                elseif($j === 1){
                    $map[$key] = $value;
                }
            }
        }
    
        return $map;
    }
    
    /**
     *
     * @param string $rowClass
     * @throws Exception
     * @return \ArrayObject
     */
    public function fetchRow($rowClass = null){
        if ($this->getKind() !== self::ROWS){
            throw new Exception('Unexpected Response: ' . $this->getKind());
        }
        $this->_stream->offset(4);
        $this->_metadata = $this->_metadata ? array_merge($this->_metadata, $this->_readMetadata()) : $this->_readMetadata();

        if (!isset($this->_metadata['columns']))
            throw new Exception('Missing Result Metadata');

        $rowCount = $this->_stream->readInt();
    
        if ($rowCount === 0)
            return null;
    
        if ($rowClass === null)
            $rowClass = $this->_rowClass;
        
        $data = [];
        foreach ($this->_metadata['columns'] as $column)
            $data[$column['name']] = $this->_stream->readValue($column['type']);
    
        return $rowClass === null ? $data : new $rowClass($data);
    }
    
    /**
     *
     * @throws Exception
     * @return mixed
     */
    public function fetchOne(){
        if ($this->getKind() !== self::ROWS){
            throw new Exception('Unexpected Response: ' . $this->getKind());
        }
        $this->_stream->offset(4);
        $this->_metadata = $this->_metadata ? array_merge($this->_metadata, $this->_readMetadata()) : $this->_readMetadata();

        if (!isset($this->_metadata['columns']))
            throw new Exception('Missing Result Metadata');

        $rowCount = $this->_stream->readInt();
    
        if ($rowCount === 0)
            return null;
    
        foreach ($this->_metadata['columns'] as $column)
            return $this->_stream->readValue($column['type']);
    
        return null;
    }
}
