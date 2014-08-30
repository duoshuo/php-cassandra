<?php
namespace Cassandra\Protocol;

/**
 * Converter into binary data type
 */
final class DataType {
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
	
	public static function getBinary(array $dataType, $value) {
		switch($dataType['type']) {
			case self::BLOB:
			case self::CUSTOM:
				return self::getCustom($value);
			case self::TIMESTAMP:
				if (is_double($value) && preg_match('~^\d{10}(.\d+)?$~', $value)) {
					$value = (int)str_pad(substr(str_replace('.', '', $value), 0, 13), 13, '0');
				} elseif (strlen($value) < 13) {
					throw new Exception('Value of timestamp must have 13 digits.');
				}
			case self::COUNTER:
			case self::BIGINT:
			case self::VARINT:
				return self::getBigInt($value);

			case self::BOOLEAN:
				return self::getBoolean($value);

			case self::COLLECTION_SET:
			case self::COLLECTION_LIST:
				return self::getList($value, $dataType['value']);

			case self::COLLECTION_MAP:
				return self::getMap($value, $dataType['key'], $dataType['value']);

			case self::DECIMAL:
				return self::getDecimal($value);

			case self::DOUBLE:
				return self::getDouble($value);

			case self::FLOAT:
				return self::getFloat($value);

			case self::INET:
				return self::getInet($value);

			case self::INT:
				return self::getInt($value);

			case self::ASCII:
			case self::VARCHAR:
			case self::TEXT:
				return self::getText($value);

			case self::TIMEUUID:
			case self::UUID:
				return self::getUUID($value);

			default:
				trigger_error('Unknown type.');
		}

		return '';
	}

	/**
	 * Return binary uuid
	 * @param string $value
	 * @throws Exception
	 * @return string
	 */
	public static function getUUID($value) {
		if (!is_string($value)) throw new Exception('Incoming value must be of type string.');
		return pack('H*', str_replace('-', '', $value));
	}

	/**
	 * @param array $values
	 * @param array $valueType
	 * @throws Exception
	 * @return string
	 */
	public static function getList($values, array $valueType) {
		if ((array)$values !== $values) throw new Exception('Incoming value must be of type array.');
		$data = pack('n', count($values));
		foreach($values as $value) {
			$itemPacked = self::getBinary($valueType, $value);
			$data .= pack('n', strlen($itemPacked)) . $itemPacked;
		}

		return $data;
	}

	/**
	 * @param array $values
	 * @param array $keyType
	 * @param array $valueType
	 * @throws Exception
	 * @return string
	 */
	public static function getMap($values, array $keyType, array $valueType) {
		if ((array)$values !== $values) throw new Exception('Incoming value must be of type array.');
		$data = pack('n', count($values));
		foreach($values as $key => $value) {
			$keyPacked = self::getBinary($keyType, $key);
			$data .= pack('n', strlen($keyPacked)) . $keyPacked;
			$valuePacked = self::getBinary($valueType, $value);
			$data .= pack('n', strlen($valuePacked)) . $valuePacked;
		}
		return $data;
	}

	/**
	 * @param string $value
	 * @throws Exception
	 * @return string
	 */
	public static function getText($value) {
		if (!is_string($value)) throw new Exception('Incoming value must be of type string.');
		return (string)$value;
	}

	/**
	 * @param int $value
	 * @throws Exception
	 * @return string
	 */
	public static function getBigInt($value) {
		if (!is_int($value)) throw new Exception('Incoming value must be of type integer.');
		$highMap = 0xffffffff00000000;
		$lowMap = 0x00000000ffffffff;
		$higher = ($value & $highMap) >>32;
		$lower = $value & $lowMap;
		return pack('NN', $higher, $lower);
	}

	/**
	 * @param string $value
	 * @throws Exception
	 * @return string
	 */
	public static function getCustom($value) {
		return pack('N', strlen($value)) . $value;
	}

	/**
	 * @param boolean $value
	 * @throws Exception
	 * @return string
	 */
	public static function getBoolean($value) {
		if (!is_bool($value)) throw new Exception('Incoming value must be of type boolean.');
		return $value ? chr(1) : chr(0);
	}

	/**
	 * @param number $value
	 * @throws Exception
	 * @return string
	 */
	public static function getDecimal($value) {
		if (!is_numeric($value)) throw new Exception('Incoming value must be numeric.');
		$scaleLen = strlen(strstr($value, ','));
		if ($scaleLen) {
			$scaleLen--;
			$value = str_replace(',', '', $value);
		}
		$highMap = 0xffffffff00000000;
		$lowMap = 0x00000000ffffffff;
		$higher = ($value & $highMap) >> 32;
		$lower = $value & $lowMap;
		return pack('NNN', $scaleLen, $higher, $lower);
	}

	/**
	 * @param double $value
	 * @throws Exception
	 * @return string
	 */
	public static function getDouble($value) {
		if (!is_double($value)) throw new Exception('Incoming value must be of type double.');
		return strrev(pack('d', $value));
	}

	/**
	 * @param float $value
	 * @throws Exception
	 * @return string
	 */
	public static function getFloat($value) {
		if (!is_float($value)) throw new Exception('Incoming value must be of type float.');
		return strrev(pack('f', $value));
	}

	/**
	 * @param string $value
	 * @throws Exception
	 * @return string
	 */
	public static function getInet($value) {
		if (!is_string($value)) throw new Exception('Incoming value must be of type string.');
		return inet_pton($value);
	}

	/**
	 * @param int $value
	 * @throws Exception
	 * @return string
	 */
	public static function getInt($value) {
		if (!is_int($value)) throw new Exception('Incoming value must be of type int.');
		return pack('N', $value);
	}
}