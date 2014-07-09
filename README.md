Cassablanka
===========

Cassandra client library for PHP, using the native binary protocol.

## Installation

PHP 5.4+ is required. There is no need for additional libraries.

Add repository to composer.json

```
	...
	"repositories": [
		{
			"type": "vcs",
			"url": "https://github.com/evseevnn/php-cassandra-binary"
		}
	],
	...
```

Append dependency

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

```

## Using transaction

```php
	$database->beginBatch();
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
	$result = $database->applyBatch();
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
