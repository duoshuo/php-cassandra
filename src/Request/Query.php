<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;

class Query extends Request{

	const FLAG_VALUES = 0x01;
	const FLAG_SKIP_METADATA = 0x02;
	const FLAG_PAGE_SIZE = 0x04;
	const FLAG_WITH_PAGING_STATE = 0x08;
	const FLAG_WITH_SERIAL_CONSISTENCY = 0x10;
	const FLAG_WITH_DEFAULT_TIMESTAMP = 0x20;
	const FLAG_WITH_NAMES_FOR_VALUES = 0x40;
	
	protected $opcode = Frame::OPCODE_QUERY;
	
	/**
	 * 
	 * @var string
	 */
	protected $_cql;
	
	/**
	 * 
	 * @var array
	 */
	protected $_values;
	
	/**
	 * 
	 * @var int
	 */
	protected $_consistency;
	
	/**
	 * 
	 * @var array
	 */
	protected $_options;
	
	/**
	 * QUERY
	 *
	 * Performs a CQL query. The body of the message consists of a CQL query as a [long
	 * string] followed by the [consistency] for the operation.
	 *
	 * Note that the consistency is ignored by some queries (USE, CREATE, ALTER,
	 * TRUNCATE, ...).
	 *
	 * The server will respond to a QUERY message with a RESULT message, the content
	 * of which depends on the query.
	 *
	 * @param string $cql
	 * @param array $values
	 * @param int $consistency
	 * @param array $options
	 */
	public function __construct($cql, $values = [], $consistency = null, $options = []) {
		$this->_cql = $cql;
		$this->_values = $values;
		$this->_consistency = $consistency === null ? Request::CONSISTENCY_ONE : $consistency;
		$this->_options = $options;
	}
	
	public function getBody(){
		$body = pack('N', strlen($this->_cql)) . $this->_cql;
		$body .= Request::queryParameters($this->_consistency, $this->_values, $this->_options);
		return $body;
	}
}
