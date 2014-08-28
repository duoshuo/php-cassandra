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

// Connect to database.
$database = new Cassandra\Database($nodes, 'my_keyspace');
$database->connect();

// Run query.
$users = $database->query('SELECT * FROM "users" WHERE "id" = :id', ['id' => 'c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc']);
//Specify the consistency
$users = $database->query(
	'SELECT * FROM "users" WHERE "id" = :id',
	['id' => 'c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc'],
	Cassandra\Enum\ConsistencyEnum::CONSISTENCY_ONE
);

var_dump($users);
/*
	result:
		array(
			[0] => array(
				'id' => 'c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc',
				'name' => 'userName',
				'email' => 'user@email.com'
			)
		)
*/

//Run conditional insert and optionally specify the serial consistency
$database->query(
	'INSERT INTO users (id, name, email) VALUES (:id, :name, :email) IF NOT EXISTS',
	array(
		'id' => 'c5420d81-499e-4c9c-ac0c-fa6ba3ebc2bc',
		'name' => 'userName',
		'email' => 'user@email.com',
	),
	Cassandra\Enum\ConsistencyEnum::CONSISTENCY_ONE,
	Cassandra\Enum\ConsistencyEnum::CONSISTENCY_LOCAL_SERIAL
);

// Keyspace can be changed at runtime
$database->setKeyspace('my_other_keyspace');
// Get from other keyspace
$urlsFromFacebook = $database->query('SELECT * FROM "urls" WHERE "host" = :host', ['host' => 'facebook.com']);

```

## Using Batch

```php
    //optionally specify batch type
	$database->beginBatch(Cassandra\Enum\BatchTypeEnum::UNLOGGED);
	// all INSERT, UPDATE, DELETE query append into batch query stack for execution after applyBatch
	$uuid = $database->query('SELECT uuid() as "uuid" FROM system.schema_keyspaces LIMIT 1;')[0]['uuid'];
	$database->query(
			'INSERT INTO "users" ("id", "name", "email") VALUES (:id, :name, :email);',
			[
				'id' => $uuid,
				'name' => 'Mark',
				'email' => 'mark@facebook.com'
			]
		);

	$database->query(
			'DELETE FROM "users" WHERE "email" = :email;',
			[
				'email' => 'durov@vk.com'
			]
		);
    //optionally specify the consistency
	$result = $database->applyBatch(Cassandra\Enum\ConsistencyEnum::CONSISTENCY_QUORUM);
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
