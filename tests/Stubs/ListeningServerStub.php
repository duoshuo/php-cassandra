<?php  namespace Cassandra\Stubs;

class ListeningServerStub
{
    protected $sock;

    public function listen($port = 9042)
    {
        $this->sock = socket_create(AF_INET, SOCK_STREAM, 0);

        // Bind the socket to an address/port
        if(!socket_bind($this->sock, 'localhost', $port)) {
            throw new \RuntimeException('Could not bind to address');
        }

        socket_set_nonblock($this->sock);

        // Start listening for connections
        socket_listen($this->sock);
    }


    public function shutdown()
    {
        socket_shutdown($this->sock);
    }
}