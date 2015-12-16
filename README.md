Cassandra client library for PHP 
================================

<a href="https://codeclimate.com/github/duoshuo/php-cassandra/"><img src="https://codeclimate.com/github/duoshuo/php-cassandra.png" /></a>
<a href="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/"><img src="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/badges/quality-score.png?b=master" /></a>
<a href="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/"><img src="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/badges/build.png?b=master" /></a>

Cassandra client library for PHP, which support Protocol v3 (Cassandra 2.1) and asynchronous request 

## Features
* Using Protocol v3 (Cassandra 2.1)
* Support ssl/tls with stream transport layer
* Support asynchronous and synchronous request
* Support for logged, unlogged and counter batches
* The ability to specify the consistency, "serial consistency" and all flags defined in the protocol
* Support Query preparation and execute
* Support all data types conversion and binding, including collection types, tuple and UDT
* Support conditional update/insert
* 5 fetch methods (fetchAll, fetchRow, fetchPairs, fetchCol, fetchOne)
* Two transport layers - socket and stream.
* Using exceptions to report errors
* 800% performance improvement(async mode) than other php cassandra client libraries

## Installation

PHP 5.4+ is required. There is no need for additional libraries.

If you want to use Bigint or Timestamp type, 64-bit system is required.

Append dependency into composer.json

```
	...
	"require": {
		...
		"duoshuo/php-cassandra": "dev-master"
	}
	...
```

Also you can just fetch project from Github and include in your code:
```
require 'php-cassandra-folder-on-your-computer/php-cassandra.php';
```

## Basic Using

```php
<?php

$nodes = [
	'127.0.0.1',		// simple way, hostname only
	'192.168.0.2:9042',	// simple way, hostname with port 
	[				// advanced way, array including username, password and socket options
		'host'		=> '10.205.48.70',
		'port'		=> 9042, //default 9042
		'username'	=> 'admin',
		'password'	=> 'pass',
		'socket'	=> [SO_RCVTIMEO => ["sec" => 10, "usec" => 0], //socket transport only
		],
	],
	[				// advanced way, using Connection\Stream, persistent connection
		'host'		=> '10.205.48.70',
		'port'		=> 9042,
		'username'	=> 'admin',
		'password'	=> 'pass',
		'class'		=> 'Cassandra\Connection\Stream',//use stream instead of socket, default socket. Stream may not work in some environment
		'connectTimeout' => 10, // connection timeout, default 5,  stream transport only
		'timeout'	=> 30, // write/recv timeout, default 30, stream transport only
		'persistent'	=> true, // use persistent PHP connection, default false,  stream transport only  
	],
	[				// advanced way, using SSL
		'class'		=> 'Cassandra\Connection\Stream', // "class" must be defined as "Cassandra\Connection\Stream" for ssl or tls
		'host'		=> 'ssl://10.205.48.70',// or 'tls://10.205.48.70'
		'port'		=> 9042,
		'username'	=> 'admin',
		'password'	=> 'pass',
	],
];

// Create a connection.
$connection = new Cassandra\Connection($nodes, 'my_keyspace');

//Connect
try
{
	$connection->connect();
}
catch (Cassandra\Exception $e)
{
	echo 'Caught exception: ',  $e->getMessage(), "\n";
	exit;//if connect failed it may be good idea not to continue
}


// Set consistency level for farther requests (default is CONSISTENCY_ONE)
$connection->setConsistency(Request::CONSISTENCY_QUORUM);

// Run query synchronously.
try
{
	$response = $connection->querySync('SELECT * FROM "users" WHERE "id" = ?', [new Cassandra\Type\Uuid('c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc')]);
}
catch (Cassandra\Exception $e)
{
}
```

## Fetch Data

```php
// Return a SplFixedArray containing all of the result set.
$rows = $response->fetchAll();		// SplFixedArray

// Return a SplFixedArray containing a specified index column from the result set.
$col = $response->fetchCol();		// SplFixedArray

// Return a assoc array with key-value pairs, the key is the first column, the value is the second column. 
$col = $response->fetchPairs();		// assoc array

// Return the first row of the result set.
$row = $response->fetchRow();		// ArrayObject

// Return the first column of the first row of the result set.
$value = $response->fetchOne();		// mixed
```

## Query Asynchronously

```php
// Return a statement immediately
try
{
	$statement1 = $connection->queryAsync($cql1);
	$statement2 = $connection->queryAsync($cql2);

	// Wait until received the response, can be reversed order
	$response2 = $statement2->getResponse();
	$response1 = $statement1->getResponse();


	$rows1 = $response1->fetchAll();
	$rows2 = $response2->fetchAll();
}
catch (Cassandra\Exception $e)
{
}
```

## Using preparation and data binding

```php
$preparedData = $connection->prepare('SELECT * FROM "users" WHERE "id" = :id');

$strictValues = Cassandra\Request\Request::strictTypeValues(
	[
		'id' => 'c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc',
	],
	$preparedData['metadata']['columns']
);

$response = $connection->executeSync(
	$preparedData['id'],
	$strictValues,
	Cassandra\Request\Request::CONSISTENCY_QUORUM,
	[
		'page_size' => 100,
		'names_for_values' => true,
		'skip_metadata' => true,
	]
);

$response->setMetadata($preparedData['result_metadata']);
$rows = $response->fetchAll();
```

## Using Batch

```php
$batchRequest = new Cassandra\Request\Batch();

// Append a prepared query
$preparedData = $connection->prepare('UPDATE "students" SET "age" = :age WHERE "id" = :id');
$values = [
	'age' => 21,
	'id' => 'c5419d81-499e-4c9c-ac0c-fa6ba3ebc2bc',
];
$batchRequest->appendQueryId($preparedData['id'], Cassandra\Request\Request::strictTypeValues($values, $preparedData['metadata']['columns']));

// Append a query string
$batchRequest->appendQuery(
	'INSERT INTO "students" ("id", "name", "age") VALUES (:id, :name, :age)',
	[
		'id' => new Cassandra\Type\Uuid('c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc'),
		'name' => new Cassandra\Type\Varchar('Mark'),
		'age' => 20,
	]
);

$response = $connection->syncRequest($batchRequest);
$rows = $response->fetchAll();
```

## Supported datatypes

All types are supported.

```php
//  Ascii
    new Cassandra\Type\Ascii('string');

//  Bigint
    new Cassandra\Type\Bigint(10000000000);

//  Blob
    new Cassandra\Type\Blob('string');

//  Boolean
    new Cassandra\Type\Boolean(true);

//  Counter
    new Cassandra\Type\Counter(1000);

//  Decimal
    new Cassandra\Type\Decimal('0.0123');

//  Double
    new Cassandra\Type\Double(2.718281828459);

//  Float
    new Cassandra\Type\PhpFloat(2.718);

//  Inet
    new Cassandra\Type\Inet('127.0.0.1');

//  Int
    new Cassandra\Type\PhpInt(1);

//  CollectionList
    new Cassandra\Type\CollectionList([1, 1, 1], [Cassandra\Type\Base::INT]);

//  CollectionMap
    new Cassandra\Type\CollectionMap(['a' => 1, 'b' => 2], [Cassandra\Type\Base::ASCII, Cassandra\Type\Base::INT]);

//  CollectionSet
    new Cassandra\Type\CollectionSet([1, 2, 3], [Cassandra\Type\Base::INT]);

//  Timestamp (unit: millisecond)
    new Cassandra\Type\Timestamp((int) (microtime(true) * 1000));
    new Cassandra\Type\Timestamp(1409830696263);

//  Uuid
    new Cassandra\Type\Uuid('62c36092-82a1-3a00-93d1-46196ee77204');

//  Timeuuid
    new Cassandra\Type\Timeuuid('2dc65ebe-300b-11e4-a23b-ab416c39d509');

//  Varchar
    new Cassandra\Type\Varchar('string');

//  Varint
    new Cassandra\Type\Varint(10000000000);

//  Custom
    new Cassandra\Type\Custom('string', 'var_name');

//  Tuple
    new Cassandra\Type\Tuple([1, '2'], [Cassandra\Type\Base::INT, Cassandra\Type\Base::VARCHAR]);

//  UDT
    new Cassandra\Type\UDT(['intField' => 1, 'textField' => '2'], ['intField' => Cassandra\Type\Base::INT, 'textField' => Cassandra\Type\Base::VARCHAR]); 	// in the order defined by the type
```

## Using nested datatypes

```php
// CollectionSet<UDT>, where UDT contains: Int, Text, Boolean, CollectionList<Text>, CollectionList<UDT>
new Cassandra\Type\CollectionSet([
	[
		'id' => 1,
		'name' => 'string',
		'active' => true,
		'friends' => ['string1', 'string2', 'string3'],
		'drinks' => [['qty' => 5, 'brand' => 'Pepsi'], ['qty' => 3, 'brand' => 'Coke']]
	],[
		'id' => 2,
		'name' => 'string',
		'active' => false,
		'friends' => ['string4', 'string5', 'string6'],
		'drinks' => []
	]
], [
	[
	'type' => Cassandra\Type\Base::UDT,
	'definition' => [
		'id' => Cassandra\Type\Base::INT,
		'name' => Cassandra\Type\Base::VARCHAR,
		'active' => Cassandra\Type\Base::BOOLEAN,
		'friends' => [
			'type' => Cassandra\Type\Base::COLLECTION_LIST,
			'value' => Cassandra\Type\Base::VARCHAR
		],
		'drinks' => [
			'type' => Cassandra\Type\Base::COLLECTION_LIST,
			'value' => [
				'type' => Cassandra\Type\Base::UDT,
				'typeMap' => [
					'qty' => Cassandra\Type\Base::INT,
					'brand' => Cassandra\Type\Base::VARCHAR
				]
			]
		]
	]
]
]);
```

## Recommend Libraries
* [shen2/fluent-cql](https://github.com/shen2/FluentCQL): write CQL in fluent interface
* [duoshuo/uuid](https://github.com/duoshuo/uuid): generate UUID and TimeUUID
* [shen2/crest](https://github.com/shen2/crest): Restful web API for Cassandra
* [shen2/cadmin](https://github.com/shen2/cadmin): Web admin panel for Cassandra, like phpmyadmin

## Inspired by
* [mauritsl/php-cassandra](https://github.com/mauritsl/php-cassandra)
* [evseevnn/php-cassandra-binary](https://github.com/evseevnn/php-cassandra-binary)
