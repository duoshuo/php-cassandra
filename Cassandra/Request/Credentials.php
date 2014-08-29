<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;

class Credentials extends Request{
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
	 */
	public function __construct($user, $password) {
		$body = pack('n', 2);
		$body .= pack('n', 8) . 'username';
		$body .= pack('n', strlen($user)) . $user;
		$body .= pack('n', 8) . 'password';
		$body .= pack('n', strlen($password)) . $password;
		
		parent::__construct(Frame::OPCODE_CREDENTIALS, $body);
	}
	
}