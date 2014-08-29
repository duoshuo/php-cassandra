<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;

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
		$valuesBinary = pack('n', count($values));
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

	public static function queryParameters($consistency, $serialConsistency, array $prepareData = array(), array $values = array()) {
		$binary = pack('n', $consistency);

		$flags = 0;
		$remainder = '';

		if (!empty($values)) {
			$flags |= Query::FLAG_VALUES;
			$remainder .= self::valuesBinary($prepareData, $values);
		}

		if (isset($serialConsistency)) {
			$flags |= Query::FLAG_WITH_SERIAL_CONSISTENCY;
			$remainder .= pack('n', $serialConsistency);
		}

		$binary .= pack('C', $flags) . $remainder;

		return $binary;
	}
}
