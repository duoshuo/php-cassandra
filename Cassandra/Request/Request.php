<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;
use Cassandra\Protocol\BinaryData;

class Request extends Frame{
	
	protected $version = 0x03;

	/**
	 * @param int $type Frame::* constants
	 * @param string $binary
	 */
	public function __construct($type, $binary = '') {
		parent::__construct($this->version, $type, $binary);
	}
	
	public static function valuesBinary(array $prepareData, array $values) {
		$valuesBinary = pack('n', count($prepareData['columns']));
		// column names in lower case in metadata
		$values = array_change_key_case($values);

		foreach($prepareData['columns'] as $key => $column) {
			if (isset($values[$column['name']])) {
				$value = $values[$column['name']];
			} elseif (isset($values[$key])) {
				$value = $values[$key];
			} else {
				$value = null;
			}
			$binary = new BinaryData($column['type'], $value);
			$valuesBinary .= pack('N', strlen($binary)) . $binary;
		}
		return $valuesBinary;
	}
}
