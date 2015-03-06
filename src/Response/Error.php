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
	 * @return array
	 */
	public function getData() {
		$this->offset = 0;
		$data = [];
		$data['code'] = $this->readInt();

		switch($data['code']){
			case self::SERVER_ERROR:
				$data['message'] = "Server error: " . $this->readString();
				break;

			case self::PROTOCOL_ERROR:
				$data['message'] = "Protocol error: " . $this->readString();
				break;

			case self::BAD_CREDENTIALS:
				$data['message'] = "Bad credentials: " . $this->readString();
				break;

			case self::UNAVAILABLE_EXCEPTION:
				$data['message'] = "Unavailable exception. Error data: " . var_export([
						'consistency' => $this->readInt(),
						'node' => $this->readInt(),
						'replica' => $this->readInt()
					], true);
				break;

			case self::OVERLOADED:
				$data['message'] = "Overloaded: " . $this->readString();
				break;

			case self::IS_BOOTSTRAPPING:
				$data['message'] = "Is_bootstrapping: " . $this->readString();
				break;

			case self::TRUNCATE_ERROR:
				$data['message'] = "Truncate_error: " . $this->readString();
				break;

			case self::WRITE_TIMEOUT:
				$data['message'] = "Write_timeout. Error data: " . var_export([
						'consistency' => $this->readInt(),
						'node' => $this->readInt(),
						'replica' => $this->readInt(),
						'writeType' => $this->readString()
					], true);
				break;

			case self::READ_TIMEOUT:
				$data['message'] = "Read_timeout. Error data: " . var_export([
						'consistency' => $this->readInt(),
						'node' => $this->readInt(),
						'replica' => $this->readInt(),
						'dataPresent' => $this->readChar()
					], true);
				break;

			case self::SYNTAX_ERROR:
				$data['message'] = "Syntax_error: " . $this->readString();
				break;

			case self::UNAUTHORIZED:
				$data['message'] = "Unauthorized: " . $this->readString();
				break;

			case self::INVALID:
				$data['message'] = "Invalid: " . $this->readString();
				break;

			case self::CONFIG_ERROR:
				$data['message'] = "Config_error: " . $this->readString();
				break;

			case self::ALREADY_EXIST:
				$data['message'] = "Already_exists: " . $this->readString();
				break;

			case self::UNPREPARED:
				$data['message'] = "Unprepared: " . $this->readShort();
				break;

			default:
				$data['message'] = 'Unknown error';
		}
		
		return $data;
	}
	
	/**
	 * 
	 * @return Exception
	 */
	public function getException(){
		$data = $this->getData();
		return new Exception($data['message'], $data['code']);
	}
}
