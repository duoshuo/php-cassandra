Cassandra client library for PHP 
================================

<a href="https://codeclimate.com/github/duoshuo/php-cassandra/"><img src="https://codeclimate.com/github/duoshuo/php-cassandra.png" /></a>
<a href="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/"><img src="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/badges/quality-score.png?b=master" /></a>
<a href="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/"><img src="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/badges/build.png?b=master" /></a>

Cassandra client library for PHP, which support Protocol v3 and asynchronous request 

## Features
* Using Protocol v3
* Support asynchronous and synchronous request
* Support for logged, unlogged and counter batches
* The ability to specify the consistency, "serial consistency" and all flags defined in the protocol
* Support Query preparation and execute
* Support all data types convertion and binding
* Support conditional update/insert
* 4 fetch methods (fetchAll, fetchRow, fetchCol, fetchOne)
* 800% performance improvement(async mode) than other php cassandra client libraries

## Installation

PHP 5.4+ is required. There is no need for additional libraries.

Append dependency into composer.json

```
	...
	"require": {
		...
		"duoshuo/php-cassandra": "dev-master"
	}
	...
```

## Basic Using

```php
<?php

$nodes = [
	'127.0.0.1',		// simple way, hostname only
	'192.168.0.2:9160',	// simple way, hostname with port 
	[				// advanced way, array including username, password and socket option
		'host'		=> '10.205.48.70',
		'port'		=> 9042,
		'username'	=> 'admin',
		'password'	=> 'pass',
		'socket'	=> [
			SO_RCVTIMEO => ["sec" => 10, "usec" => 0],
		],
	],
];

// Create a connection.
$connection = new Cassandra\Connection($nodes, 'my_keyspace');

// Run query synchronously.
$response = $connection->querySync('SELECT * FROM "users" WHERE "id" = ?', [new Cassandra\Type\Uuid('c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc')]);
```

## Fetch Data

```php
// Return a SplFixedArray containing all of the result set.
$rows = $response->fetchAll();		// SplFixedArray

// Return a SplFixedArray containing a specified index column from the result set.
$col = $response->fetchCol();		// SplFixedArray

// Return the first row of the result set.
$row = $response->fetchRow();		// ArrayObject

// Return the first column of the first row of the result set.
$value = $response->fetchOne();		// mixed
```

## Query Asynchronously

```php
// Return a statement immediately
$statement = $connection->queryAsync($cql);

// Wait until received the response
$response = $statement->getResponse();

$rows = $response->fetchAll();
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
$batchRequest->appendQueryId($preparedData['id'], $strictValues)
	->appendQuery(
		'INSERT INTO "students" ("id", "name", "age") VALUES (:id, :name, :age)',
		[
			'id' => new Cassandra\Type\Uuid('c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc'),
			'name' => new Cassandra\Type\Varchar('Mark'),
			'age' => 20,
		]
	);

$response = $connection->syncRequest($batch);
$rows = $response->fetchAll();
```

## Supported datatypes

All types are supported.

* **Cassandra\Type\Ascii**
```php
    new Cassandra\Type\Ascii('string')
```
* **Cassandra\Type\Bigint**
```php
    new Cassandra\Type\Bigint(10000000000)
```
* **Cassandra\Type\Blob**
```php
    new Cassandra\Type\Blob('string')
```
* **Cassandra\Type\Boolean**
```php
    new Cassandra\Type\Boolean(true)
```
* **Cassandra\Type\Counter**
```php
    new Cassandra\Type\Counter(1000)
```
* **Cassandra\Type\Decimal**
```php
    new Cassandra\Type\Decimal('2.718281828459')
```
* **Cassandra\Type\Double**
```php
    new Cassandra\Type\Double(2.718281828459)
```
* **Cassandra\Type\Float**
```php
    new Cassandra\Type\Float(2.718)
```
* **Cassandra\Type\Inet**
```php
    new Cassandra\Type\Inet('127.0.0.1')
```
* **Cassandra\Type\Int**
```php
    new Cassandra\Type\Int(1)
```
* **Cassandra\Type\CollectionList**
```php
    new Cassandra\Type\CollectionList([1, 1, 1], Cassandra\Type\Base::INT)
```
* **Cassandra\Type\CollectionMap**
```php
    new Cassandra\Type\CollectionMap(['a' => 1, 'b' => 2], Cassandra\Type\Base::ASCII, Cassandra\Type\Base::INT)
```
* **Cassandra\Type\CollectionSet**
```php
    new Cassandra\Type\CollectionSet([1, 2, 3], Cassandra\Type\Base::INT)
```
* **Cassandra\Type\Timestamp**
```php
    new Cassandra\Type\Timestamp(1409830696263)
```
* **Cassandra\Type\Uuid**
```php
    new Cassandra\Type\Uuid('62c36092-82a1-3a00-93d1-46196ee77204')
```
* **Cassandra\Type\Timeuuid**
```php
    new Cassandra\Type\Timeuuid('2dc65ebe-300b-11e4-a23b-ab416c39d509')
```
* **Cassandra\Type\Varchar**
```php
    new Cassandra\Type\Varchar('string')
```
* **Cassandra\Type\Varint**
```php
    new Cassandra\Type\Varint(10000000000)
```
* **Cassandra\Type\Custom**
```php
    new Cassandra\Type\Custom('string')
```
