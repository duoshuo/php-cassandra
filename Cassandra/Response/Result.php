<?php 
namespace Cassandra\Response;
use Cassandra\Enum\DataTypeEnum;

class Result extends DataStream{
	const VOID = 0x0001;
	const ROWS = 0x0002;
	const SET_KEYSPACE = 0x0003;
	const PREPARED = 0x0004;
	const SCHEMA_CHANGE = 0x0005;
	
	/**
	 * build a data stream first and read by type
	 *
	 * @param array $type
	 * @return mixed
	 */
	public function readByTypeFromStream(array $type){
		try {
			$length = $this->readInt();
				
			if ($this->length < $this->offset + $length)
				return null;
			
			$dataStream = new DataStream($this->read($length));
			return $dataStream->readByType($type);
		} catch (\Exception $e) {
			return null;
		}
	}
	
	/**
	 * @return Rows|string|array|null
	 */
	public function getData() {
		$kind = parent::readInt();
		switch($kind) {
			case self::VOID:
				return null;
	
			case self::ROWS:
				$columns = $this->getColumns();
				$rowCount = parent::readInt();
				$rows = new \SplFixedArray($rowCount);
				$rows->columns = $columns;
	
				for ($i = 0; $i < $rowCount; ++$i) {
					$row = new ArrayObject();
						
					foreach ($columns as $column)
						$row[$column['name']] = self::readByTypeFromStream($column['type']);
						
					$rows[$i] = $row;
				}
	
				return $rows;
	
			case self::SET_KEYSPACE:
				return parent::readString();
	
			case self::PREPARED:
				return [
					'id' => parent::readString(),
					'columns' => $this->getColumns()
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
			'type' => parent::readShort()
		];
		switch ($data['type']) {
			case DataTypeEnum::CUSTOM:
				$data['name'] = parent::readString();
				break;
			case DataTypeEnum::COLLECTION_LIST:
			case DataTypeEnum::COLLECTION_SET:
				$data['value'] = self::readType();
				break;
			case DataTypeEnum::COLLECTION_MAP:
				$data['key'] = self::readType();
				$data['value'] = self::readType();
				break;
			default:
		}
		return $data;
	}
	
	/**
	 * Return metadata
	 * @return array
	 */
	private function getColumns() {
		$flags = parent::readInt();
		$columnCount = parent::readInt();
		$globalTableSpec = $flags & 0x0001;
		if ($globalTableSpec) {
			$keyspace = parent::readString();
			$tableName = parent::readString();
		}
	
		$columns = [];
		for ($i = 0; $i < $columnCount; ++$i) {
			if (isset($keyspace, $tableName)) {
				$columnData = [
				'keyspace' => $keyspace,
				'tableName' => $tableName,
				'name' => parent::readString(),
				'type' => self::readType()
				];
			} else {
				$columnData = [
				'keyspace' => parent::readString(),
				'tableName' => parent::readString(),
				'name' => parent::readString(),
				'type' => self::readType()
				];
			}
	
			$columns[] = $columnData;
		}
	
		return $columns;
	}
	
	/**
	 *
	 * @param int $kind
	 * @throws Exception
	 * @return NULL
	 */
	protected function _throwException($kind){
		switch($kind){
			case self::VOID:
				throw new Exception('Unexpected Response: VOID');
	
			case self::ROWS:
				throw new Exception('Unexpected Response: ROWS');
	
			case self::SET_KEYSPACE:
				throw new Exception('Unexpected Response: SET_KEYSPACE ' . parent::readString());
	
			case self::PREPARED:
				throw new Exception('Unexpected Response: PREPARED id:' . parent::readString() . ' columns:' . $this->getColumns());
	
			case self::SCHEMA_CHANGE:
				throw new Exception('Unexpected Response: SCHEMA_CHANGE change:' . parent::readString() . ' keyspace:' . parent::readString() . ' table:' . parent::readString());
	
			default:
				throw new Exception('Unexpected Response: ' . $kind);
		}
	}
	
	/**
	 *
	 * @throws Exception
	 * @return \SplFixedArray
	 */
	public function fetchAll($rowClass = 'ArrayObject'){
		$kind = parent::readInt();
	
		if ($kind !== self::ROWS){
			$this->_throwException($kind);
		}
	
		$columns = $this->getColumns();
		$rowCount = parent::readInt();
		$rows = new \SplFixedArray($rowCount);
		$rows->columns = $columns;
	
		for ($i = 0; $i < $rowCount; ++$i) {
			$row = new $rowClass();
	
			foreach ($columns as $column)
				$row[$column['name']] = self::readByTypeFromStream($column['type']);
				
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
		$kind = parent::readInt();
	
		if ($kind !== self::ROWS){
			$this->_throwException($kind);
		}
	
		$columns = $this->getColumns();
		$columnCount = count($columns);
		$rowCount = parent::readInt();
	
		$array = new \SplFixedArray($rowCount);
	
		for($i = 0; $i < $rowCount; ++$i){
			for($j = 0; $j < $columnCount; ++$j){
				$value = self::readByTypeFromStream($columns[$j]['type']);
	
				if ($j == $index)
					$array[$i] = $row;
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
		$kind = parent::readInt();
	
		if ($kind !== self::ROWS){
			$this->_throwException($kind);
		}
	
		$columns = $this->getColumns();
		$rowCount = parent::readInt();
	
		if ($rowCount === 0)
			return null;
	
		$row = new $rowClass();
		foreach ($columns as $column)
			$row[$column['name']] = self::readByTypeFromStream($column['type']);
	
		return $row;
	}
	
	/**
	 *
	 * @throws Exception
	 * @return mixed
	 */
	public function fetchOne(){
		$kind = parent::readInt();
	
		if ($kind !== self::ROWS){
			$this->_throwException($kind);
		}
	
		$columns = $this->getColumns();
		$rowCount = parent::readInt();
	
		if ($rowCount === 0)
			return null;
	
		foreach ($columns as $column)
			return self::readByTypeFromStream($column['type']);
	
		return null;
	}
}