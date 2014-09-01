<?php
namespace Cassandra\Response;
use Cassandra\Protocol\Frame;

class DataStream {
	
	use DataReader;

	/**
	 * @param string $binary
	 */
	public function __construct($binary) {
		$this->data = $binary;
	}
}
