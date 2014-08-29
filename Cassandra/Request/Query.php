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
	const FLAG_WITH_NAME_FOR_VALUES = 0x40;
	
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
	 * @param int $consistency
	 */
	public function __construct($cql, $consistency, $serialConsistency) {
		$body = pack('N', strlen($cql)) . $cql;
		$body .= self::queryParameters($consistency, $serialConsistency);
		parent::__construct(Frame::OPCODE_QUERY, $body);
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