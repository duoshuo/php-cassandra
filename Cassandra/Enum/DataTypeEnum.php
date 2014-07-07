<?php
namespace Cassandra\Enum;

class DataTypeEnum {
	const CUSTOM = 0x0000;
	const ASCII = 0x0001;
	const BIGINT = 0x0002;
	const BLOB = 0x0003;
	const BOOLEAN = 0x0004;
	const COUNTER = 0x0005;
	const DECIMAL = 0x0006;
	const DOUBLE = 0x0007;
	const FLOAT = 0x0008;
	const INT = 0x0009;
	const TEXT = 0x000A;
	const TIMESTAMP = 0x000B;
	const UUID = 0x000C;
	const VARCHAR = 0x000D;
	const VARINT = 0x000E;
	const TIMEUUID = 0x000F;
	const INET = 0x0010;
	const COLLECTION_LIST = 0x0020;
	const COLLECTION_MAP = 0x0021;
	const COLLECTION_SET = 0x0022;
}