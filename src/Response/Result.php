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
		$this->offset = 4;
		switch($this->getKind()) {
			case self::VOID:
				return null;
	
			case self::ROWS:
				return $this->fetchAll();
	
			case self::SET_KEYSPACE:
				return parent::readString();
	
			case self::PREPARED:
				return [
					'id' => parent::readString(),
					'metadata' => $this->_readMetadata(),
					'result_metadata' => $this->_readMetadata(),
				];
	
			case self::SCHEMA_CHANGE:
				return [
					'change' => parent::readString(),
					'keyspace' => parent::readString(),
					'table' => parent::readString()
				];
		}
	
		return null;
	}
	
	/**
	 * @return int|array
	 */
	protected function readType(){
		$type = unpack('n', $this->read(2))[1];
		switch ($type) {
			case Type\Base::CUSTOM:
				return [
					'type'	=> $type,
					'name'	=> self::readString(),
				];
			case Type\Base::COLLECTION_LIST:
			case Type\Base::COLLECTION_SET:
				return [
					'type'	=> $type,
					'value'	=> self::readType(),
				];
			case Type\Base::COLLECTION_MAP:
				return [
					'type'	=> $type,
					'key'	=> self::readType(),
					'value'	=> self::readType(),
				];
			case Type\Base::UDT:
				$data = [
					'type'		=> $type,
					'keyspace'	=> self::readString(),
					'name'		=> self::readString(),
					'typeMap'	=> [],
				];
				$length = self::readShort();
				for($i = 0; $i < $length; ++$i){
					$key = self::readString();
					$data['typeMap'][$key] = self::readType();
				}
				return $data;
			case Type\Base::TUPLE:
				$data = [
					'type'	=> $type,
					'typeList'	=>	[],
				];
				$length = self::readShort();
				for($i = 0; $i < $length; ++$i){
					$data['typeList'][] = self::readType();
				}
				return $data;
			default:
				return $type;
		}
	}

	public function getKind(){
		if ($this->_kind === null)
			$this->_kind = unpack('N', substr($this->data, 0, 4))[1];
	
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
			$this->offset = 4;
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
		$metadata = unpack('Nflags/Ncolumns_count', $this->read(8));
		$flags = $metadata['flags'];

		if ($flags & self::ROWS_FLAG_HAS_MORE_PAGES)
			$metadata['page_state'] = parent::readBytes();

		if (!($flags & self::ROWS_FLAG_NO_METADATA)) {
			$metadata['columns'] = [];
			
			if ($flags & self::ROWS_FLAG_GLOBAL_TABLES_SPEC) {
				$keyspace = $this->read(unpack('n', $this->read(2))[1]);
				$tableName = $this->read(unpack('n', $this->read(2))[1]);

				for ($i = 0; $i < $metadata['columns_count']; ++$i) {
					$metadata['columns'][] = [
						'keyspace' => $keyspace,
						'tableName' => $tableName,
						'name' => $this->read(unpack('n', $this->read(2))[1]),
						'type' => self::readType()
					];
				}
			}
			else {
				for ($i = 0; $i < $metadata['columns_count']; ++$i) {
					$metadata['columns'][] = [
						'keyspace' => $this->read(unpack('n', $this->read(2))[1]),
						'tableName' => $this->read(unpack('n', $this->read(2))[1]),
						'name' => $this->read(unpack('n', $this->read(2))[1]),
						'type' => self::readType()
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
		$this->offset = 4;
		$this->_metadata = $this->_metadata ? array_merge($this->_metadata, $this->_readMetadata()) : $this->_readMetadata();

		if (!isset($this->_metadata['columns']))
			throw new Exception('Missing Result Metadata');

		$rowCount = parent::readInt();
		$rows = new \SplFixedArray($rowCount);
		
		if ($rowClass === null)
			$rowClass = $this->_rowClass;
	
		for ($i = 0; $i < $rowCount; ++$i) {
			$data = [];
	
			foreach ($this->_metadata['columns'] as $column)
				$data[$column['name']] = $this->readBytesAndConvertToType($column['type']);
				
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
		$this->offset = 4;
		$this->_metadata = $this->_metadata ? array_merge($this->_metadata, $this->_readMetadata()) : $this->_readMetadata();

		if (!isset($this->_metadata['columns']))
			throw new Exception('Missing Result Metadata');

		$rowCount = parent::readInt();
	
		$array = new \SplFixedArray($rowCount);
	
		for($i = 0; $i < $rowCount; ++$i){
			foreach($this->_metadata['columns'] as $j => $column){
				$value = $this->readBytesAndConvertToType($column['type']);
	
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
		$this->offset = 4;
		$this->_metadata = $this->_metadata ? array_merge($this->_metadata, $this->_readMetadata()) : $this->_readMetadata();
	
		if (!isset($this->_metadata['columns']))
			throw new Exception('Missing Result Metadata');
	
		$rowCount = parent::readInt();
	
		$map = [];
	
		for($i = 0; $i < $rowCount; ++$i){
			foreach($this->_metadata['columns'] as $j => $column){
				$value = $this->readBytesAndConvertToType($column['type']);
	
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
		$this->offset = 4;
		$this->_metadata = $this->_metadata ? array_merge($this->_metadata, $this->_readMetadata()) : $this->_readMetadata();

		if (!isset($this->_metadata['columns']))
			throw new Exception('Missing Result Metadata');

		$rowCount = parent::readInt();
	
		if ($rowCount === 0)
			return null;
	
		if ($rowClass === null)
			$rowClass = $this->_rowClass;
		
		$data = [];
		foreach ($this->_metadata['columns'] as $column)
			$data[$column['name']] = $this->readBytesAndConvertToType($column['type']);
	
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
		$this->offset = 4;
		$this->_metadata = $this->_metadata ? array_merge($this->_metadata, $this->_readMetadata()) : $this->_readMetadata();

		if (!isset($this->_metadata['columns']))
			throw new Exception('Missing Result Metadata');

		$rowCount = parent::readInt();
	
		if ($rowCount === 0)
			return null;
	
		foreach ($this->_metadata['columns'] as $column)
			return $this->readBytesAndConvertToType($column['type']);
	
		return null;
	}
}
