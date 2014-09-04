PHP library for Cassandra
=========================

<a href="https://codeclimate.com/github/duoshuo/php-cassandra/"><img src="https://codeclimate.com/github/duoshuo/php-cassandra.png" /></a>
<a href="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/"><img src="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/badges/quality-score.png?b=master" /></a>
<a href="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/"><img src="https://scrutinizer-ci.com/g/duoshuo/php-cassandra/badges/build.png?b=master" /></a>

Cassandra client library for PHP, using the native binary protocol.

## Features
* Using v3 protocol
* Support for logged, unlogged and counter batches
* The ability to specify the consistency and serial consistency
* Automatic query preparation
* Support for conditional update/insert

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

## Base Using

```php
<?php

$nodes = array(
	array (
		'host' => '10.205.48.70',
		'port' => 9042,
		'username' => 'admin',
		'password' => 'pass',
	),
	'127.0.0.1',
);

// Create a connection.
$connection = new Cassandra\Connection($nodes, 'my_keyspace');

// Run query synchronously.
$response = $connection->querySync(
	'SELECT * FROM "users" WHERE "id" = :id',
	['id' => new Cassandra\Type\Uuid('c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc')],
);
```

## Fetch Data

```php
// Return a SplFixedArray containing all of the result set rows.
$response->fetchAll();

// Return a single column from the next row of a result set.
$response->fetchCol();

// Fetche the next row from a result set.
$response->fetchRow();

// Retrieve the next row of a query result set and return a single sequence.
$response->fetchOne();
```

## Query Asynchronously

```php
$statements = array();

// Return a statement.
for ($i = 0; $i < 100; ++$i)
	$statements[$i] = $connection->queryAsync($cql);

for ($i = 0; $i < 100; ++$i)
	$statements[$i]->getResponse();
```

## Using Preparation

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
$response->fetchAll();
```

## Using Batch

```php
$batch = new Cassandra\Request\Batch();

$batch->appendQueryId($preparedData['id'], $strictValues);

$batch->appendQuery(
	'INSERT INTO "students" ("id", "name", "age") VALUES (:id, :name, :age)',
	[
		'id' => new Cassandra\Type\Uuid('c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc'),
		'name' => new Cassandra\Type\Varchar('Mark'),
		'age' => 20,
	]
);

$connection->syncRequest($batch);
```

## Supported datatypes

All types are supported.

* *ascii, varchar, text*
  Result will be a string.
* *bigint, counter, varint*
  Converted to strings using bcmath.
* *blob*
  Result will be a string.
* *boolean*
  Result will be a boolean as well.
* *decimal*
  Converted to strings using bcmath.
* *double, float, int*
  Result is using native PHP datatypes.
* *timestamp*
  Converted to integer. Milliseconds precision is lost.
* *uuid, timeuuid, inet*
  No native PHP datatype available. Converted to strings.
* *list, set*
  Converted to array (numeric keys).
* *map*
  Converted to keyed array.
