<?php
namespace Cassandra\Protocol;
use Cassandra\Enum\OpcodeEnum;
use Cassandra\Enum\QueryFlagsEnum;

final class RequestFactory {

	/**
	 * STARTUP
	 *
	 * Initialize the connection. The server will respond by either a READY message
	 * (in which case the connection is ready for queries) or an AUTHENTICATE message
	 * (in which case credentials will need to be provided using CREDENTIALS).
	 *
	 * This must be the first message of the connection, except for OPTIONS that can
	 * be sent before to find out the options supported by the server. Once the
	 * connection has been initialized, a client should not send any more STARTUP
	 * message.
	 *
	 * Possible options are:
	 * - "CQL_VERSION": the version of CQL to use. This option is mandatory and
	 * currenty, the only version supported is "3.0.0". Note that this is
	 * different from the protocol version.
	 * - "COMPRESSION": the compression algorithm to use for frames (See section 5).
	 * This is optional, if not specified no compression will be used.
	 *
	 * @param array $option
	 * @return \Cassandra\Protocol\Request
	 */
	public static function startup(array $option = []) {
		$body = pack('n', count($option));
		foreach ($option as $name => $value) {
			$body .= pack('n', strlen($name)) . $name;
			$body .= pack('n', strlen($value)) . $value;
		}

		return new Request(OpcodeEnum::STARTUP, $body);
	}

	/**
	 * CREDENTIALS
	 *
	 * Provides credentials information for the purpose of identification. This
	 * message comes as a response to an AUTHENTICATE message from the server, but
	 * can be use later in the communication to change the authentication
	 * information.
	 *
	 * The body is a list of key/value informations. It is a [short] n, followed by n
	 * pair of [string]. These key/value pairs are passed as is to the Cassandra
	 * IAuthenticator and thus the detail of which informations is needed depends on
	 * that authenticator.
	 *
	 * The response to a CREDENTIALS is a READY message (or an ERROR message).
	 *
	 * @param string $user
	 * @param string $password
	 * @return \Cassandra\Protocol\Request
	 */
	public static function credentials($user, $password) {
		$body = pack('n', 2);
		$body .= pack('n', 8) . 'username';
		$body .= pack('n', strlen($user)) . $user;
		$body .= pack('n', 8) . 'password';
		$body .= pack('n', strlen($password)) . $password;

		return new Request(OpcodeEnum::CREDENTIALS, $body);
	}

	/**
	 * OPTIONS
	 *
	 * Asks the server to return what STARTUP options are supported. The body of an
	 * OPTIONS message should be empty and the server will respond with a SUPPORTED
	 * message.
	 */
	public function options() {
		return new Request(OpcodeEnum::OPTIONS);
	}

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
	 * @return \Cassandra\Protocol\Request
	 */
	public static function query($cql, $consistency, $serialConsistency) {
		$body = pack('N', strlen($cql)) . $cql;
		$body .= self::queryParameters($consistency, $serialConsistency);
		return new Request(OpcodeEnum::QUERY, $body);
	}

	/**
	 * PREPARE
	 *
	 * Prepare a query for later execution (through EXECUTE). The body consists of
	 * the CQL query to prepare as a [long string].
	 *
	 * The server will respond with a RESULT message with a `prepared` kind (0x0004,
	 * see Section 4.2.5).
	 *
	 * @param string $cql
	 * @return \Cassandra\Protocol\Request
	 */
	public static function prepare($cql) {
		$body = pack('N', strlen($cql)) . $cql;
		return new Request(OpcodeEnum::PREPARE, $body);
	}

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
	 * @param array $prepareData
	 * @param array $values
	 * @param int $consistency
	 * @return \Cassandra\Protocol\Request
	 */
	public static function execute(array $prepareData, array $values, $consistency, $serialConsistency) {
		$body = pack('n', strlen($prepareData['id'])) . $prepareData['id'];

		$body .= self::queryParameters($consistency, $serialConsistency, $prepareData, $values);

		return new Request(OpcodeEnum::EXECUTE, $body);
	}

	/**
	 * REGISTER
	 *
	 * Register this connection to receive some type of events. The body of the
	 * message is a [string list] representing the event types to register to. See
	 * section 4.2.6 for the list of valid event types.
	 *
	 * The response to a REGISTER message will be a READY message.
	 *
	 * Please note that if a client driver maintains multiple connections to a
	 * Cassandra node and/or connections to multiple nodes, it is advised to
	 * dedicate a handful of connections to receive events, but to *not* register
	 * for events on all connections, as this would only result in receiving
	 * multiple times the same event messages, wasting bandwidth.
	 *
	 * @param array $events
	 */
	public function register(array $events) {
		// TODO
	}

	public static function batch($body) {
		return new Request(OpcodeEnum::BATCH, $body);
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
			$flags |= QueryFlagsEnum::VALUES;
			$remainder .= self::valuesBinary($prepareData, $values);
		}

		if (isset($serialConsistency)) {
			$flags |= QueryFlagsEnum::WITH_SERIAL_CONSISTENCY;
			$remainder .= pack('n', $serialConsistency);
		}

		$binary .= pack('C', $flags) . $remainder;

		return $binary;
	}
}
