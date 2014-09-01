<?php
namespace Cassandra\Response;
use Cassandra\Protocol\Frame;
use Cassandra\Protocol\DataType;

class Supported extends Response {
	public function getData(){
		$this->offset = 0;
		/**
		 * TODO Check it!
		 * Indicates which startup options are supported by the server. This message
		 * comes as a response to an OPTIONS message.
		 *
		 * The body of a SUPPORTED message is a [string multimap]. This multimap gives
		 * for each of the supported STARTUP options, the list of supported values.
		 */
		return $this->readStringMultimap();
	}
}
