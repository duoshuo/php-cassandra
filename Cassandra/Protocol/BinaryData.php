<?php
namespace Cassandra\Protocol;
use Cassandra\Enum\DataTypeEnum;

class BinaryData {

	/**
	 * @var int
	 */
	private $type;

	/**
	 * @var mixed
	 */
	private $value;

	/**
	 * @var array
	 */
	private $keyType;

	/**
	 * @var array
	 */
	private $valueType;

	/**
	 * @param array $dataType
	 * @param mixed $value
	 */
	public function __construct(array $dataType, $value) {
		$this->type = $dataType['type'];
		$this->value = $value;
		if (isset($dataType['key'])) $this->keyType = $dataType['key'];
		if (isset($dataType['value'])) $this->valueType = $dataType['value'];
	}

	/**
	 * @return string
	 */
	public function __toString() {
		switch($this->type) {
			case DataTypeEnum::CUSTOM:
			case DataTypeEnum::BLOB:
				return $this->getBlob();

			case DataTypeEnum::COUNTER:
			case DataTypeEnum::TIMESTAMP:
			case DataTypeEnum::BIGINT:
			case DataTypeEnum::VARINT:
				return $this->getBigInt();

			case DataTypeEnum::BOOLEAN:
				return $this->getBoolean();

			case DataTypeEnum::COLLECTION_SET:
			case DataTypeEnum::COLLECTION_LIST:
				return $this->getList();

			case DataTypeEnum::COLLECTION_MAP:
				return $this->getMap();

			case DataTypeEnum::DECIMAL:
				return $this->getDecimal();

			case DataTypeEnum::DOUBLE:
				return $this->getDouble();

			case DataTypeEnum::FLOAT:
				return $this->getFloat();

			case DataTypeEnum::INET:
				return $this->getInet();

			case DataTypeEnum::INT:
				return $this->getInt();

			case DataTypeEnum::ASCII:
			case DataTypeEnum::VARCHAR:
			case DataTypeEnum::TEXT:
				return $this->getText();

			case DataTypeEnum::TIMEUUID:
			case DataTypeEnum::UUID:
				return $this->getUUID();

			default:
				trigger_error('Unknown type.');
		}

		return '';
	}

	/**
	 * Return binary uuid
	 * @return string
	 */
	private function getUUID() {
		return pack('H*', str_replace('-', '', $this->value));
	}

	/**
	 * @return string
	 */
	private function getList() {
		$data = pack('n', count($this->value));
		foreach($this->value as $item) {
			$itemPacked = new BinaryData($this->valueType, $item);
			$data .= pack('n', strlen($itemPacked)) . $itemPacked;
		}

		return $data;
	}

	/**
	 * @return string
	 */
	private function getMap() {
		$data = pack('n', count($this->value));
		foreach($this->value as $key => $item) {
			$keyPacked = new BinaryData($this->keyType, $key);
			$data .= pack('n', strlen($keyPacked)) . $keyPacked;
			$itemPacked = new BinaryData($this->valueType, $item);
			$data .= pack('n', strlen($itemPacked)) . $itemPacked;
		}
		return $data;
	}

	/**
	 * @return string
	 */
	private function getText() {
		return (string)$this->value;
	}

	/**
	 * @return string
	 */
	private function getBigInt() {
		$value = $this->value;
		$highMap = 0xffffffff00000000;
		$lowMap = 0x00000000ffffffff;
		$higher = ($value & $highMap) >>32;
		$lower = $value & $lowMap;
		return pack('NN', $higher, $lower);
	}

	/**
	 * @return string
	 */
	private function getBlob() {
		return pack('N', strlen($this->value)) . $this->value;
	}

	/**
	 * @return string
	 */
	private function getBoolean() {
		return $this->value ? chr(1) : chr(0);
	}

	/**
	 * @return string
	 */
	private function getDecimal() {
		$scaleLen = strlen(strstr($this->value, ','));
		if ($scaleLen) {
			$scaleLen -= 1;
			$this->value = str_replace(',', '', $this->value);
		}
		$highMap = 0xffffffff00000000;
		$lowMap = 0x00000000ffffffff;
		$higher = ($this->value & $highMap) >>32;
		$lower = $this->value & $lowMap;
		return pack('NNN', $scaleLen, $higher, $lower);
	}

	/**
	 * @return string
	 */
	private function getDouble() {
		return strrev(pack('d', $this->value));
	}

	/**
	 * @return string
	 */
	private function getFloat() {
		return strrev(pack('f', $this->value));
	}

	/**
	 * @return string
	 */
	private function getInet() {
		return inet_pton($this->value);
	}

	/**
	 * @return string
	 */
	private function getInt() {
		return pack('N', $this->value);
	}
}