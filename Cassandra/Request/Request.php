<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;
use Cassandra\Protocol\DataType;

class Request implements Frame{

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
	protected $stream;
	
	/**
	 * @var int
	 */
	protected $flags;
	
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
			$binary = DataType::getBinary($column['type'], $value);
			$valuesBinary .= pack('N', strlen($binary)) . $binary;
		}
		return $valuesBinary;
	}
	
	public static function queryParameters($consistency, $serialConsistency, array $prepareData = array(), array $values = array()) {
		$binary = pack('n', $consistency);
	
		$flags = 0;
		$remainder = '';
	
		if (!empty($values)) {
			$flags |= self::FLAG_VALUES;
			$remainder .= self::valuesBinary($prepareData, $values);
		}
	
		if (isset($serialConsistency)) {
			$flags |= self::FLAG_WITH_SERIAL_CONSISTENCY;
			$remainder .= pack('n', $serialConsistency);
		}
	
		$binary .= pack('C', $flags) . $remainder;
	
		return $binary;
	}
}
