<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;

class AuthResponse extends Request{
	
	protected $opcode = Frame::OPCODE_AUTH_RESPONSE;
	
	/**
	 * 
	 * @var string
	 */
	protected $_username;
	
	/**
	 *
	 * @var string
	 */
	protected $_password;
	
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
		$body = pack('C', 0);
		$body .= $this->_username;
		$body .= pack('C', 0);
		$body .= $this->_password;
		
		return pack('N', strlen($body)) . $body;
	}
	
}
