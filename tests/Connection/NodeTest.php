<?php namespace Cassandra\Connection;

use Cassandra\Stubs\ListeningServerStub;
use Cassandra\TestCase;

class NodeTest extends TestCase {

    /**
     * @var ListeningServerStub
     */
    protected static $server;

    public static function setUpBeforeClass()
    {
        self::$server = new ListeningServerStub();
        self::$server->listen(19042);
    }

    public static function tearDownAfterClass()
    {
        self::$server->shutdown();
    }

    public function testNewInstance()
    {
        $node = new Node("localhost:19042");

        $this->assertInstanceOf('Cassandra\Connection\Node', $node);

        return $node;
    }

    /**
     * @param Node $node
     * @depends testNewInstance
     */
    public function testIsConnected(Node $node)
    {
        $this->assertFalse($node->isConnected());

        $node->connect();

        $this->assertTrue($node->isConnected());
    }


    /**
     * @depends testNewInstance
     * @param Node $node
     */
    public function testGetOptions(Node $node)
    {
        $this->assertArrayHasKey("host", $node->getOptions());
        $this->assertArrayHasKey("port", $node->getOptions());
    }

    /**
     * @depends testNewInstance
     * @param Node $node
     */
    public function testGetConnectionReturnsResource(Node $node)
    {
        $connection = $node->getConnection();

        $this->assertTrue(is_resource($connection));
    }

    /**
     * @throws Exception
     */
    public function testGetConnectionException()
    {
        $node = new Node("invalidhost:9042");

        $this->setExpectedException('Cassandra\Connection\Exception', "invalidhost:9042");

        $node->getConnection();
        $node->disconnect();
    }

    public function testInvalidOptions()
    {
        $node = new Node([
            'host' => 'localhost',
            'port' => 19042,
            'socket' => [
                SO_RCVTIMEO => -1,
            ]
        ]);

        $this->setExpectedException('Cassandra\Connection\Exception', "Unable to set socket option");

        $node->connect();
    }

    /**
     *
     * @depends testNewInstance
     * @param Node $node
     */
    public function testDisconnect(Node $node)
    {
        $node->disconnect();

        $this->assertFalse($node->isConnected());
    }
}
