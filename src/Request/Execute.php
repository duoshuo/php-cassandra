<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;

class Execute extends Request{
	
	protected $opcode = Frame::OPCODE_EXECUTE;
	
	/**
	 * 
	 * @var int
	 */
	protected $_queryId;
	
	/**
	 * 
	 * @var int
	 */
	protected $_consistency;
	
	/**
	 * 
	 * @var array
	 */
	protected $_values;
	
	/**
	 * 
	 * @var array
	 */
	protected $_options;
	
	/**
	 * EXECUTE
	 *
	 * Executes a prepared query. The body of the message must be:
	 * <id><n><value_1>....<value_n><consistency>
	 * where:
	 * - <id> is the prepared query ID. It's the [short bytes] returned as a
	 * response to a PREPARE message.
	 * - <n> is a [short] indicating the number of following values.
	 * - <value_1>...<value_n> are the [bytes] to use for bound variables in the
	 * prepared query.
	 * - <consistency> is the [consistency] level for the operation.
	 * Note that the consistency is ignored by some (prepared) queries (USE, CREATE,
	 * ALTER, TRUNCATE, ...).
	 * The response from the server will be a RESULT message.
	 *
	 * @param int $queryId
	 * @param array $values
	 * @param int $consistency
	 * @param array $options
	 */
	public function __construct($queryId, array $values, $consistency = null, $options = []) {
		$this->_queryId = $queryId;
		$this->_values = $values;
		 
		$this->_consistency = $consistency === null ? Request::CONSISTENCY_ONE : $consistency;
		$this->_options = $options;
	}
	
	public function getBody(){
		$body = pack('n', strlen($this->_queryId)) . $this->_queryId;
		
		$body .= Request::queryParameters($this->_consistency, $this->_values, $this->_options);
		
		return $body;
	}
}
