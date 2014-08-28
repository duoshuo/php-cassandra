<?php
namespace Cassandra\Protocol;
use Cassandra\Enum\DataTypeEnum;
use Cassandra\Enum\ErrorCodesEnum;
use Cassandra\Enum\OpcodeEnum;
use Cassandra\Enum\ResultTypeEnum;
use Cassandra\Exception\ResponseException;
use Cassandra\Protocol\Response\DataStream;
use Cassandra\Protocol\Response\DataStream\TypeReader;
use Cassandra\Protocol\Response\Rows;

class Response {

	/**
	 * @var int
	 */
	private $type;

	/**
	 * @var string
	 */
	private $binary;

	/**
	 * @var Response\DataStream
	 */
	private $dataStream;

	/**
	 * @param int $type OpcodeEnum::* constants
	 * @param string $binary
	 */
	public function __construct($type, $binary) {
		$this->type = $type;
		$this->binary = $binary;
		$this->dataStream = new DataStream($binary);
	}

	/**
	 * Return response type
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	public function getData() {
		switch($this->type) {
			case OpcodeEnum::ERROR:
				return $this->getErrorData();

			case OpcodeEnum::READY:
				/**
				 * Indicates that the server is ready to process queries. This message will be
				 * sent by the server either after a STARTUP message if no authentication is
				 * required, or after a successful CREDENTIALS message.
				 */
				return null;

			case OpcodeEnum::AUTHENTICATE:
				return unpack('n', $this->binary)[1];

			case OpcodeEnum::SUPPORTED:
				/**
				 * TODO Check it!
				 * Indicates which startup options are supported by the server. This message
				 * comes as a response to an OPTIONS message.
				 *
				 * The body of a SUPPORTED message is a [string multimap]. This multimap gives
				 * for each of the supported STARTUP options, the list of supported values.
				 */
				return $this->dataStream->readByType(['type' => DataTypeEnum::COLLECTION_MAP]);

			case OpcodeEnum::RESULT:
				return $this->getResultData();

			case OpcodeEnum::EVENT:
				// TODO
				return '';

			default:
				throw new ResponseException('Unknown response');
		}
	}

	/**
	 * Indicates an error processing a request. The body of the message will be an
	 * error code ([int]) followed by a [string] error message. Then, depending on
	 * the exception, more content may follow. The error codes are defined in
	 * Section 7, along with their additional content if any.
	 *
	 * @return string
	 */
	private function getErrorData() {
		$stream = $this->dataStream;
		$error = $stream->readInt();

		$errorMessages = [
			ErrorCodesEnum::SERVER_ERROR => function(DataStream $stream) {
					return "Server error: {$stream->readString()}";
				},
			ErrorCodesEnum::PROTOCOL_ERROR => function(DataStream $stream) {
					return "Protocol error: {$stream->readString()}";
				},
			ErrorCodesEnum::BAD_CREDENTIALS => function(DataStream $stream) {
					return "Bad credentials: {$stream->readString()}";
				},
			ErrorCodesEnum::UNAVAILABLE_EXCEPTION => function(DataStream $stream) {
					$errorData = var_export([
						'consistency' => $stream->readInt(),
						'node' => $stream->readInt(),
						'replica' => $stream->readInt()
					], true);

					return "Unavailable exception. Error data: {$errorData}";
				},
			ErrorCodesEnum::OVERLOADED => function(DataStream $stream) {
					return "Overloaded: {$stream->readString()}";
				},
			ErrorCodesEnum::IS_BOOTSTRAPPING => function(DataStream $stream) {
					return "Is_bootstrapping: {$stream->readString()}";
				},
			ErrorCodesEnum::TRUNCATE_ERROR => function(DataStream $stream) {
					return "Truncate_error: {$stream->readString()}";
				},
			ErrorCodesEnum::WRITE_TIMEOUT => function(DataStream $stream) {
					$errorData = var_export([
						'consistency' => $stream->readInt(),
						'node' => $stream->readInt(),
						'replica' => $stream->readInt(),
						'writeType' => $stream->readString()
					], true);
					return "Write_timeout. Error data: {$errorData}";
				},
			ErrorCodesEnum::READ_TIMEOUT => function(DataStream $stream) {
					$errorData = var_export([
						'consistency' => $stream->readInt(),
						'node' => $stream->readInt(),
						'replica' => $stream->readInt(),
						'dataPresent' => $stream->readChar()
					], true);
					return "Read_timeout. Error data: {$errorData}";
				},
			ErrorCodesEnum::SYNTAX_ERROR => function(DataStream $stream) {
					return "Syntax_error: {$stream->readString()}";
				},
			ErrorCodesEnum::UNAUTHORIZED => function(DataStream $stream) {
					return "Unauthorized: {$stream->readString()}";
				},
			ErrorCodesEnum::INVALID => function(DataStream $stream) {
					return "Invalid: {$stream->readString()}";
				},
			ErrorCodesEnum::CONFIG_ERROR => function(DataStream $stream) {
					return "Config_error: {$stream->readString()}";
				},
			ErrorCodesEnum::ALREADY_EXIST => function(DataStream $stream) {
					return "Already_exists: {$stream->readString()}";
				},
			ErrorCodesEnum::UNPREPARED => function(DataStream $stream) {
					return "Unprepared: {$stream->readShort()}";
				}
		];

		return isset($errorMessages[$error]) ? $errorMessages[$error]($stream) : 'Unknown error';
	}

	/**
	 * @return Rows|string|array|null
	 */
	private function getResultData() {
		$kind = $this->dataStream->readInt();
		switch($kind) {
			case ResultTypeEnum::VOID:
				return null;

			case ResultTypeEnum::ROWS:
				return new Rows($this->dataStream, $this->getColumns());

			case ResultTypeEnum::SET_KEYSPACE:
				return $this->dataStream->readString();

			case ResultTypeEnum::PREPARED:
				return [
					'id' => $this->dataStream->readString(),
					'columns' => $this->getColumns()
				];

			case ResultTypeEnum::SCHEMA_CHANGE:
				return [
					'change' => $this->dataStream->readString(),
					'keyspace' => $this->dataStream->readString(),
					'table' => $this->dataStream->readString()
				];
		}

		return null;
	}

	/**
	 * Return metadata
	 * @return array
	 */
	private function getColumns() {
		$flags = $this->dataStream->readInt();
		$columnCount = $this->dataStream->readInt();
		$globalTableSpec = $flags & 0x0001;
		if ($globalTableSpec) {
			$keyspace = $this->dataStream->readString();
			$tableName = $this->dataStream->readString();
		}

		$columns = [];
		for ($i = 0; $i < $columnCount; ++$i) {
			if (isset($keyspace, $tableName)) {
				$columnData = [
					'keyspace' => $keyspace,
					'tableName' => $tableName,
					'name' => $this->dataStream->readString(),
					'type' => TypeReader::readFromStream($this->dataStream)
				];
			} else {
				$columnData = [
					'keyspace' => $this->dataStream->readString(),
					'tableName' => $this->dataStream->readString(),
					'name' => $this->dataStream->readString(),
					'type' => TypeReader::readFromStream($this->dataStream)
				];
			}

			$columns[] = $columnData;
		}

		return $columns;
	}
	
	/**
	 * 
	 * @param unknown $kind
	 * @throws ResponseException
	 * @return NULL|\Cassandra\Protocol\Response\Rows
	 */
	protected function _throwException($kind){
		switch($kind){
			case ResultTypeEnum::VOID:
				throw new ResponseException('Unexpected Response: VOID');
		
			case ResultTypeEnum::ROWS:
				$rows = new Rows($this->dataStream, $this->getColumns());
				throw new ResponseException('Unexpected Response: ROWS ' . $rows->count());
		
			case ResultTypeEnum::SET_KEYSPACE:
				throw new ResponseException('Unexpected Response: SET_KEYSPACE ' . $this->dataStream->readString());
		
			case ResultTypeEnum::PREPARED:
				throw new ResponseException('Unexpected Response: PREPARED id:' . $this->dataStream->readString() . ' columns:' . $this->getColumns());
		
			case ResultTypeEnum::SCHEMA_CHANGE:
				throw new ResponseException('Unexpected Response: SCHEMA_CHANGE change:' . $this->dataStream->readString() . ' keyspace:' . $this->dataStream->readString() . ' table:' . $this->dataStream->readString());
		
			default:
				throw new ResponseException('Unexpected Response: ' . $kind);
		}
	}
	
	/**
	 * 
	 * @throws ResponseException
	 * @return \Cassandra\Protocol\Response\Rows
	 */
	public function fetchAll(){
		$kind = $this->dataStream->readInt();
		
		if ($kind !== ResultTypeEnum::ROWS && $kind !== ResultTypeEnum::VOID){
			$this->_throwException($kind);
		}
		
		return new Rows($this->dataStream, $this->getColumns());
	}
	
	/**
	 * 
	 * @return \ArrayObject
	 */
	public function fetchRow(){
		$rows = $this->fetchAll();
		
		return isset($rows[0]) ? $rows[0] : null;
	}
	
	/**
	 * 
	 * @return mixed
	 */
	public function fetchOne(){
		$rows = $this->fetchAll();
			
		foreach($rows as $row)
			foreach($row as $value)
				return $value;
		
		return null;
	}
}
