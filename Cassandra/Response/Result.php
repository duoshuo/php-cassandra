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
	
	protected $_kind;
	
	/**
	 * read a [bytes] and read by type
	 *
	 * @param array $type
	 * @return mixed
	 */
	protected function _readBytesAndConvertToType(array $type){
		$length = unpack('N', substr($this->data, $this->offset, 4))[1];
		$this->offset += 4;
		
		if ($length === 4294967295)
			return null;
		
		// do not use $this->read() for performance
		$data = substr($this->data, $this->offset, $length);
		$this->offset += $length;
		
		switch ($type['type']) {
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
			case Type\Base::CUSTOM:
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
			case Type\Base::COLLECTION_LIST:
			case Type\Base::COLLECTION_SET:
				$dataStream = new DataStream($data);
				return $dataStream->readList($type['value']);
			case Type\Base::COLLECTION_MAP:
				$dataStream = new DataStream($data);
				return $dataStream->readMap($type['key'], $type['value']);
			default:
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
				$columns = $this->getColumns();
				$rowCount = parent::readInt();
				$rows = new \SplFixedArray($rowCount);
				$rows->columns = $columns;
	
				for ($i = 0; $i < $rowCount; ++$i) {
					$row = new \ArrayObject();
						
					foreach ($columns as $column)
						$row[$column['name']] = $this->_readBytesAndConvertToType($column['type']);
						
					$rows[$i] = $row;
				}
	
				return $rows;
	
			case self::SET_KEYSPACE:
				return parent::readString();
	
			case self::PREPARED:
				return [
					'id' => parent::readString(),
					'metadata' => $this->getColumns()
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
	 * @return mixed
	 */
	protected function readType(){
		$data = [
			'type' => unpack('n', $this->read(2))[1]
		];
		switch ($data['type']) {
			case Type\Base::CUSTOM:
				$data['name'] = $this->read(unpack('n', $this->read(2))[1]);
				break;
			case Type\Base::COLLECTION_LIST:
			case Type\Base::COLLECTION_SET:
				$data['value'] = self::readType();
				break;
			case Type\Base::COLLECTION_MAP:
				$data['key'] = self::readType();
				$data['value'] = self::readType();
				break;
			default:
		}
		return $data;
	}

	public function getKind(){
		if ($this->_kind === null)
			$this->_kind = unpack('N', substr($this->data, 0, 4))[1];
	
		return $this->_kind;
	}
	
	/**
	 * Return metadata
	 * @return array
	 */
	private function getColumns() {
		$unpacked = unpack('N2', $this->read(8));
		$flags = $unpacked[1];
		$columnCount = $unpacked[2];
		
		if ($flags & self::ROWS_FLAG_GLOBAL_TABLES_SPEC) {
			$keyspace = $this->read(unpack('n', $this->read(2))[1]);
			$tableName = $this->read(unpack('n', $this->read(2))[1]);
			
			$columns = [];
			for ($i = 0; $i < $columnCount; ++$i) {
				$columnData = [
					'keyspace' => $keyspace,
					'tableName' => $tableName,
					'name' => $this->read(unpack('n', $this->read(2))[1]),
					'type' => self::readType()
				];
				$columns[] = $columnData;
			}
		}
		else {
			$columns = [];
			for ($i = 0; $i < $columnCount; ++$i) {
				$columnData = [
					'keyspace' => $this->read(unpack('n', $this->read(2))[1]),
					'tableName' => $this->read(unpack('n', $this->read(2))[1]),
					'name' => $this->read(unpack('n', $this->read(2))[1]),
					'type' => self::readType()
				];
				$columns[] = $columnData;
			}
		}
	
		return $columns;
	}
	
	/**
	 *
	 * @throws Exception
	 * @return \SplFixedArray
	 */
	public function fetchAll($rowClass = 'ArrayObject'){
		if ($this->getKind() !== self::ROWS){
			throw new Exception('Unexpected Response: ' . $this->getKind());
		}
		$this->offset = 4;
		$columns = $this->getColumns();
		$rowCount = parent::readInt();
		$rows = new \SplFixedArray($rowCount);
		$rows->columns = $columns;
	
		for ($i = 0; $i < $rowCount; ++$i) {
			$row = new $rowClass();
	
			foreach ($columns as $column)
				$row[$column['name']] = $this->_readBytesAndConvertToType($column['type']);
				
			$rows[$i] = $row;
		}
	
		return $rows;
	}
	
	/**
	 *
	 * @throws Exception
	 * @return \SplFixedArray
	 */
	public function fetchCol($index = 0){
		if ($this->getKind() !== self::ROWS){
			throw new Exception('Unexpected Response: ' . $this->getKind());
		}
		$this->offset = 4;
		$columns = $this->getColumns();
		$columnCount = count($columns);
		$rowCount = parent::readInt();
	
		$array = new \SplFixedArray($rowCount);
	
		for($i = 0; $i < $rowCount; ++$i){
			for($j = 0; $j < $columnCount; ++$j){
				$value = $this->_readBytesAndConvertToType($columns[$j]['type']);
	
				if ($j == $index)
					$array[$i] = $value;
			}
		}
	
		return $array;
	}
	
	/**
	 *
	 * @throws Exception
	 * @return \ArrayObject
	 */
	public function fetchRow($rowClass = 'ArrayObject'){
		if ($this->getKind() !== self::ROWS){
			throw new Exception('Unexpected Response: ' . $this->getKind());
		}
		$this->offset = 4;
		$columns = $this->getColumns();
		$rowCount = parent::readInt();
	
		if ($rowCount === 0)
			return null;
	
		$row = new $rowClass();
		foreach ($columns as $column)
			$row[$column['name']] = $this->_readBytesAndConvertToType($column['type']);
	
		return $row;
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
		$columns = $this->getColumns();
		$rowCount = parent::readInt();
	
		if ($rowCount === 0)
			return null;
	
		foreach ($columns as $column)
			return $this->_readBytesAndConvertToType($column['type']);
	
		return null;
	}
}
