<?php
namespace Cassandra\Protocol\Response;

class Rows extends \SplFixedArray {

	/**
	 * @var array
	 */
	protected $_columns = [];

	public function __construct(DataStream $stream, array $columns) {
		$rowCount = $stream->readInt();
		parent::__construct($rowCount);
		$this->_columns = $columns;
		
		for ($i = 0; $i < $rowCount; ++$i) {
			$row = array();
			
			foreach ($columns as $column) {
				try {
					$dataStream = new DataStream($stream->readBytes());
					$row[$column['name']] = $dataStream->readByType($column['type']);
				} catch (\Exception $e) {
					$row[$column['name']] = null;
				}
			}
			$this[$i] = $row;
		}
	}
}
