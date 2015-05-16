<?php
namespace Cassandra\Response;

class Supported extends Response {
	public function getData(){
		$this->_stream->offset(0);
		/**
		 * TODO Check it!
		 * Indicates which startup options are supported by the server. This message
		 * comes as a response to an OPTIONS message.
		 *
		 * The body of a SUPPORTED message is a [string multimap]. This multimap gives
		 * for each of the supported STARTUP options, the list of supported values.
		 */
		return $this->_stream->readStringMultimap();
	}
}
