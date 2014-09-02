PHP library for Cassandra
=========================

<a href="https://codeclimate.com/github/evseevnn/php-cassandra-binary"><img src="https://codeclimate.com/github/evseevnn/php-cassandra-binary.png" /></a>
<a href="https://scrutinizer-ci.com/g/evseevnn/php-cassandra-binary/"><img src="https://scrutinizer-ci.com/g/evseevnn/php-cassandra-binary/badges/quality-score.png?b=master" /></a>
<a href="https://scrutinizer-ci.com/g/evseevnn/php-cassandra-binary/"><img src="https://scrutinizer-ci.com/g/evseevnn/php-cassandra-binary/badges/build.png?b=master" /></a>

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
		"evseevnn/php-cassandra-binary": "dev-master"
	}
	...
```

## Base Using

```php
<?php

$nodes = [
	'127.0.0.1',
	'192.168.0.2:8882' => [
		'username' => 'admin',
		'password' => 'pass'
	]
];

// Create a connection.
$connection = new Cassandra\Connection($nodes, 'my_keyspace');

// Run query synchronously.
$response = $connection->querySync('SELECT * FROM "users" WHERE "id" = :id', ['id' => 'c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc']);

// Fetch data methods
$response->fetchAll();
$response->fetchCol();
$response->fetchRow();
$response->fetchOne();

// Specify the consistency and optionas.
$response = $database->query(
	'SELECT * FROM "users" WHERE "id" = :id',
	['id' => 'c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc'],
	Cassandra\Request\Request::CONSISTENCY_QUORUM,
	[
		'page_size' => 100,
		'names_for_values' => true,
		'default_timestamp' => 1409670248663725,
	]
);

// Run query asynchronously.
$statement = $connection->queryAsync('SELECT * FROM "users"');
$statement->getResponse();

// Keyspace can be changed at runtime
$connection->setKeyspace('my_other_keyspace');

```

## Using Preparation

```php
$preparedData = $connection->prepare('INSERT INTO "users" ("id", "name", "email") VALUES (:id, :name, :email)');

$strictValues = Cassandra\Request\Request::strictTypeValues(
	[
		'id' => 'c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc',
		'name' => 'Mark',
		'email' => 'mark@facebook.com',
	],
	$preparedData['metadata']
);

$connection->executeSync($preparedData['id'], $strictValues);
```

## Using Batch

```php
$batch = new Cassandra\Result\Batch();

$batch->appendQueryId($preparedData['id'], $strictValues);

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
