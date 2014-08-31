<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;
use Cassandra\Protocol\DataType;

class Request implements Frame{

	const CONSISTENCY_ANY = 0x0000;
	const CONSISTENCY_ONE = 0x0001;
	const CONSISTENCY_TWO = 0x0002;
	const CONSISTENCY_THREE = 0x0003;
	const CONSISTENCY_QUORUM = 0x0004;
	const CONSISTENCY_ALL = 0x0005;
	const CONSISTENCY_LOCAL_QUORUM = 0x0006;
	const CONSISTENCY_EACH_QUORUM = 0x0007;
	const CONSISTENCY_SERIAL = 0x0008;
	const CONSISTENCY_LOCAL_SERIAL = 0x0009;
	const CONSISTENCY_LOCAL_ONE = 0x000A;
	
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
	
	public function setStream($stream){
		$this->stream = $stream;
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
