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
	 * read a [bytes] and read by type
	 *
	 * @param int|array $type
	 * @return mixed
	 */
	protected function _readBytesAndConvertToType($type){
		$length = unpack('N', substr($this->data, $this->offset, 4))[1];
		$this->offset += 4;
		
		if ($length === 0xffffffff)
			return null;
		
		// do not use $this->read() for performance
		$data = substr($this->data, $this->offset, $length);
		$this->offset += $length;
		
		switch ($type) {
			case Type\Base::ASCII:
			case Type\Base::VARCHAR:
			case Type\Base::TEXT:
				return $data;
			case Type\Base::BIGINT:
			case Type\Base::COUNTER:
			case Type\Base::VARINT:
			case Type\Base::TIMESTAMP:	//	use big int to present microseconds timestamp
				$unpacked = unpack('N2', $data);
				return $unpacked[1] << 32 | $unpacked[2];
			case Type\Base::BLOB:
				$length = unpack('N', substr($data, 0, 4))[1];
				return substr($data, 4, $length);
			case Type\Base::BOOLEAN:
				return (bool) unpack('C', $data)[1];
			case Type\Base::DECIMAL:
				$unpacked = unpack('N3', $data);
				$value = $unpacked[2] << 32 | $unpacked[3];
				$len = strlen($value);
				return substr($value, 0, $len - $unpacked[1]) . '.' . substr($value, $len - $unpacked[1]);
			case Type\Base::DOUBLE:
				return unpack('d', strrev($data))[1];
			case Type\Base::FLOAT:
				return unpack('f', strrev($data))[1];
			case Type\Base::INT:
				return unpack('N', $data)[1];
			case Type\Base::UUID:
			case Type\Base::TIMEUUID:
				$uuid = '';
				$unpacked = unpack('n8', $data);
				
				for ($i = 1; $i <= 8; ++$i) {
					if ($i == 3 || $i == 4 || $i == 5 || $i == 6) {
						$uuid .= '-';
					}
					$uuid .= str_pad(dechex($unpacked[$i]), 4, '0', STR_PAD_LEFT);
				}
				return $uuid;
			case Type\Base::INET:
				return inet_ntop($data);
			default:
				if (is_array($type)){
					switch($type['type']){
						case Type\Base::COLLECTION_LIST:
						case Type\Base::COLLECTION_SET:
							$dataStream = new DataStream($data);
							return $dataStream->readList($type['value']);
						case Type\Base::COLLECTION_MAP:
							$dataStream = new DataStream($data);
							return $dataStream->readMap($type['key'], $type['value']);
						case Type\Base::UDT:
							throw new Exception('Unsupported Type UDT.');
						case Type\Base::TUPLE:
							throw new Exception('Unsupported Type Tuple.');
						case Type\Base::CUSTOM:
						default:
							$length = unpack('N', substr($data, 0, 4))[1];
							return substr($data, 4, $length);
					}
				}
				
				trigger_error('Unknown type ' . var_export($type, true));
				return null;
		}
	}
	
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
					'type'	=> $type,
					'keyspace'=>self::readString(),
					'name'	=> self::readString(),
					'names'	=>	[],
				];
				$length = self::readShort();
				for($i = 0; $i < $length; ++$i){
					$key = self::readString();
					$data['names'][$key] = self::readType();
				}
				return $data;
			case Type\Base::TUPLE:
				$data = [
					'type'	=> $type,
					'types'	=>	[],
				];
				$length = self::readShort();
				for($i = 0; $i < $length; ++$i){
					$data['types'][] = self::readType();
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
				$data[$column['name']] = $this->_readBytesAndConvertToType($column['type']);
				
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
			foreach($columns as $j => $column){
				$value = $this->_readBytesAndConvertToType($column['type']);
	
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
				$value = $this->_readBytesAndConvertToType($column['type']);
	
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
			$data[$column['name']] = $this->_readBytesAndConvertToType($column['type']);
	
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
			return $this->_readBytesAndConvertToType($column['type']);
	
		return null;
	}
}
