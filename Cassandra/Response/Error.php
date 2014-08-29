<?php
namespace Cassandra\Response;

class Error extends DataStream {
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
		$stream = $this;
		$error = $stream->readInt();

		$errorMessages = [
			self::SERVER_ERROR => function(DataStream $stream) {
					return "Server error: {$stream->readString()}";
				},
			self::PROTOCOL_ERROR => function(DataStream $stream) {
					return "Protocol error: {$stream->readString()}";
				},
			self::BAD_CREDENTIALS => function(DataStream $stream) {
					return "Bad credentials: {$stream->readString()}";
				},
			self::UNAVAILABLE_EXCEPTION => function(DataStream $stream) {
					$errorData = var_export([
						'consistency' => $stream->readInt(),
						'node' => $stream->readInt(),
						'replica' => $stream->readInt()
					], true);

					return "Unavailable exception. Error data: {$errorData}";
				},
			self::OVERLOADED => function(DataStream $stream) {
					return "Overloaded: {$stream->readString()}";
				},
			self::IS_BOOTSTRAPPING => function(DataStream $stream) {
					return "Is_bootstrapping: {$stream->readString()}";
				},
			self::TRUNCATE_ERROR => function(DataStream $stream) {
					return "Truncate_error: {$stream->readString()}";
				},
			self::WRITE_TIMEOUT => function(DataStream $stream) {
					$errorData = var_export([
						'consistency' => $stream->readInt(),
						'node' => $stream->readInt(),
						'replica' => $stream->readInt(),
						'writeType' => $stream->readString()
					], true);
					return "Write_timeout. Error data: {$errorData}";
				},
			self::READ_TIMEOUT => function(DataStream $stream) {
					$errorData = var_export([
						'consistency' => $stream->readInt(),
						'node' => $stream->readInt(),
						'replica' => $stream->readInt(),
						'dataPresent' => $stream->readChar()
					], true);
					return "Read_timeout. Error data: {$errorData}";
				},
			self::SYNTAX_ERROR => function(DataStream $stream) {
					return "Syntax_error: {$stream->readString()}";
				},
			self::UNAUTHORIZED => function(DataStream $stream) {
					return "Unauthorized: {$stream->readString()}";
				},
			self::INVALID => function(DataStream $stream) {
					return "Invalid: {$stream->readString()}";
				},
			self::CONFIG_ERROR => function(DataStream $stream) {
					return "Config_error: {$stream->readString()}";
				},
			self::ALREADY_EXIST => function(DataStream $stream) {
					return "Already_exists: {$stream->readString()}";
				},
			self::UNPREPARED => function(DataStream $stream) {
					return "Unprepared: {$stream->readShort()}";
				}
		];

		return isset($errorMessages[$error]) ? $errorMessages[$error]($stream) : 'Unknown error';
	}
}
