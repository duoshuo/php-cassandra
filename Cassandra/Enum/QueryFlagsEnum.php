<?php
namespace Cassandra\Enum;

class QueryFlagsEnum {
	const VALUES = 0x01;
	const SKIP_METADATA = 0x02;
	const PAGE_SIZE = 0x04;
	const WITH_PAGING_STATE = 0x08;
	const WITH_SERIAL_CONSISTENCY = 0x10;
	const WITH_DEFAULT_TIMESTAMP = 0x20;
	const WITH_NAME_FOR_VALUES = 0x40;
}
