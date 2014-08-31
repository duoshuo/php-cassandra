<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;

class Credentials extends Request{
	
	protected $opcode = Frame::OPCODE_CREDENTIALS;
	
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
	 * @param string $username
	 * @param string $password
	 */
	public function __construct($username, $password) {
		$this->_username = $username;
		$this->_password = $password;
	}
	
	public function getBody(){
		$body = pack('n', 2);
		$body .= pack('n', 8) . 'username';
		$body .= pack('n', strlen($this->_username)) . $this->_username;
		$body .= pack('n', 8) . 'password';
		$body .= pack('n', strlen($this->_password)) . $this->_password;
		
		return $body;
	}
	
}