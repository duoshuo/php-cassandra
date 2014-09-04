<?php
namespace Cassandra\Response;
use Cassandra\Protocol\Frame;

class Error extends Response {
	const SERVER_ERROR = 0x0000;
	const PROTOCOL_ERROR = 0x000A;
	const BAD_CREDENTIALS = 0x0100;
	const UNAVAILABLE_EXCEPTION = 0x1000;
	const OVERLOADED = 0x1001;
	const IS_BOOTSTRAPPING = 0x1002;
	const TRUNCATE_ERROR = 0x1003;
	const WRITE_TIMEOUT = 0x1100;
	const READ_TIMEOUT = 0x1200;
	const SYNTAX_ERROR = 0x2000;
	const UNAUTHORIZED = 0x2100;
	const INVALID = 0x2200;
	const CONFIG_ERROR = 0x2300;
	const ALREADY_EXIST = 0x2400;
	const UNPREPARED = 0x2500;
	
	/**
	 * Indicates an error processing a request. The body of the message will be an
	 * error code ([int]) followed by a [string] error message. Then, depending on
	 * the exception, more content may follow. The error codes are defined in
	 * Section 7, along with their additional content if any.
	 *
	 * @return string
	 */
	public function getData() {
		$this->offset = 0;
		$errorCode = $this->readInt();

		switch($errorCode){
			case self::SERVER_ERROR:
				return "Server error: " . $this->readString();

			case self::PROTOCOL_ERROR:
				return "Protocol error: " . $this->readString();

			case self::BAD_CREDENTIALS:
				return "Bad credentials: " . $this->readString();

			case self::UNAVAILABLE_EXCEPTION:
				return "Unavailable exception. Error data: " . var_export([
						'consistency' => $this->readInt(),
						'node' => $this->readInt(),
						'replica' => $this->readInt()
					], true);

			case self::OVERLOADED:
				return "Overloaded: " . $this->readString();

			case self::IS_BOOTSTRAPPING:
				return "Is_bootstrapping: " . $this->readString();

			case self::TRUNCATE_ERROR:
				return "Truncate_error: " . $this->readString();

			case self::WRITE_TIMEOUT:
				return "Write_timeout. Error data: " . var_export([
						'consistency' => $this->readInt(),
						'node' => $this->readInt(),
						'replica' => $this->readInt(),
						'writeType' => $this->readString()
					], true);

			case self::READ_TIMEOUT:
				return "Read_timeout. Error data: " . var_export([
						'consistency' => $this->readInt(),
						'node' => $this->readInt(),
						'replica' => $this->readInt(),
						'dataPresent' => $this->readChar()
					], true);

			case self::SYNTAX_ERROR:
				return "Syntax_error: " . $this->readString();

			case self::UNAUTHORIZED:
				return "Unauthorized: " . $this->readString();

			case self::INVALID:
				return "Invalid: " . $this->readString();

			case self::CONFIG_ERROR:
				return "Config_error: " . $this->readString();

			case self::ALREADY_EXIST:
				return "Already_exists: " . $this->readString();

			case self::UNPREPARED:
				return "Unprepared: " . $this->readShort();

			default:
				return 'Unknown error';
		}
	}
}
