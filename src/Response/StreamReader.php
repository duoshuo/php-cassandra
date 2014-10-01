<?php
namespace Cassandra\Response;

use Cassandra\Type;

trait StreamReader {

	/**
	 * @var string
	 */
	protected $data;
	
	/**
	 * @var int
	 */
	protected $offset = 0;

	/**
	 * Read data from stream.
	 *
	 * @param int $length
	 * @return string
	 */
	protected function read($length) {
		$output = substr($this->data, $this->offset, $length);
		$this->offset += $length;
		return $output;
	}
	
	public function reset(){
		$this->offset = 0;
	}

	/**
	 * Read single character.
	 *
	 * @return int
	 */
	public function readChar() {
		return unpack('C', $this->read(1))[1];
	}

	/**
	 * Read unsigned short.
	 *
	 * @return int
	 */
	public function readShort() {
		return unpack('n', $this->read(2))[1];
	}

	/**
	 * Read unsigned int.
	 *
	 * @return int
	 */
	public function readInt() {
		return unpack('N', $this->read(4))[1];
	}

	/**
	 * Read string.
	 *
	 * @return string
	 */
	public function readString() {
		$length = unpack('n', $this->read(2))[1];
		return $this->read($length);
	}

	/**
	 * Read long string.
	 *
	 * @return string
	 */
	public function readLongString() {
		$length = unpack('N', $this->read(4))[1];
		return $this->read($length);
	}

	/**
	 * Read bytes.
	 *
	 * @return string
	 */
	public function readBytes() {
		$length = unpack('N', $this->read(4))[1];
		if ($length == 4294967295)
			return null;
		return $this->read($length);
	}

	/**
	 * Read uuid.
	 *
	 * @return string
	 */
	public function readUuid() {
		$uuid = '';
		$data = unpack('n8', $this->read(16));

		for ($i = 1; $i <= 8; ++$i) {
			if ($i == 3 || $i == 4 || $i == 5 || $i == 6) {
				$uuid .= '-';
			}
			$uuid .= str_pad(dechex($data[$i]), 4, '0', STR_PAD_LEFT);
		}

		return $uuid;
	}

	/**
	 * Read list.
	 *
	 * @param $valueType
	 * @return array
	 */
	public function readList($valueType) {
		$list = [];
		$count = $this->readInt();
		for ($i = 0; $i < $count; ++$i) {
			$list[] = $this->readBytesAndConvertToType($valueType);
		}
		return $list;
	}

	/**
	 * Read map.
	 *
	 * @param $keyType
	 * @param $valueType
	 * @return array
	 */
	public function readMap($keyType, $valueType) {
		$map = [];
		$count = $this->readInt();
		for ($i = 0; $i < $count; ++$i) {
			$map[$this->readBytesAndConvertToType($keyType)] = $this->readBytesAndConvertToType($valueType);
		}
		return $map;
	}

	/**
	 * Read float.
	 *
	 * @return float
	 */
	public function readFloat() {
		return unpack('f', strrev($this->read(4)))[1];
	}

	/**
	 * Read double.
	 *
	 * @return double
	 */
	public function readDouble() {
		return unpack('d', strrev($this->read(8)))[1];
	}

	/**
	 * Read boolean.
	 *
	 * @return bool
	 */
	public function readBoolean() {
		return (bool)$this->readChar();
	}

	/**
	 * Read inet.
	 *
	 * @return string
	 */
	public function readInet() {
		return inet_ntop($this->data);
	}

	/**
	 * Read variable length integer.
	 *
	 * @return string
	 */
	public function readVarint() {
		list($higher, $lower) = array_values(unpack('N2', $this->data));
		return $higher << 32 | $lower;
	}

	/**
	 * Read variable length decimal.
	 *
	 * @return string
	 */
	public function readDecimal() {
		$scale = $this->readInt();
		$value = $this->readVarint();
		$len = strlen($value);
		return substr($value, 0, $len - $scale) . '.' . substr($value, $len - $scale);
	}
	
	public function readStringMultimap(){
		$map = [];
		$count = $this->readShort();
		for($i = 0; $i < $count; $i++){
			$key = $this->readString();
				
			$listLength = $this->readShort();
			$list = [];
			for($j = 0; $j < $listLength; $j++)
				$list[] = $this->readString();
					
			$map[$key] = $list;
		}
		return $map;
	}

	/**
	 * @param int|array $type
	 * @param bool $isCollectionElement for collection element used other alg. a temporary solution
	 * @return mixed
	 */
	public function readByType($type, $isCollectionElement = false) {
		switch ($type) {
			case Type\Base::ASCII:
			case Type\Base::VARCHAR:
			case Type\Base::TEXT:
				return $isCollectionElement ? $this->readString() : $this->data;
			case Type\Base::BIGINT:
			case Type\Base::COUNTER:
			case Type\Base::VARINT:
			case Type\Base::TIMESTAMP:	//	use big int to present microseconds timestamp
				return $this->readVarint();
			case Type\Base::BLOB:
				return $this->readBytes();
			case Type\Base::BOOLEAN:
				return $this->readBoolean();
			case Type\Base::DECIMAL:
				return $this->readDecimal();
			case Type\Base::DOUBLE:
				return $this->readDouble();
			case Type\Base::FLOAT:
				return $this->readFloat();
			case Type\Base::INT:
				return $this->readInt();
			case Type\Base::UUID:
				return $this->readUuid();
			case Type\Base::TIMEUUID:
				return $this->readUuid();
			case Type\Base::INET:
				return $this->readInet();
			default:
				if (is_array($type)){
					switch($type['type']){
						case Type\Base::COLLECTION_LIST:
						case Type\Base::COLLECTION_SET:
							return $this->readList($type['value']);
						case Type\Base::COLLECTION_MAP:
							return $this->readMap($type['key'], $type['value']);
						case Type\Base::UDT:
							throw new Exception('Unsupported Type UDT.');
						case Type\Base::TUPLE:
							throw new Exception('Unsupported Type Tuple.');
						case Type\Base::CUSTOM:
						default:
							return $this->readBytes();
					}
				}
				trigger_error('Unknown type ' . var_export($type, true));
				return null;
		}
	}

	/**
	 * read a [bytes] and read by type
	 *
	 * @param int|array $type
	 * @return mixed
	 */
	public function readBytesAndConvertToType($type){
		$length = unpack('N', substr($this->data, $this->offset, 4))[1];
		$this->offset += 4;

		if ($length === 0xffffffff)
			return null;

		// do not use $this->read() for performance
		$data = substr($this->data, $this->offset, $length);
		$this->offset += $length;

		switch ($type) {
			case Type\Base::ASCII:
			case Type\Base::VARCHAR:
			case Type\Base::TEXT:
				return $data;
			case Type\Base::BIGINT:
			case Type\Base::COUNTER:
			case Type\Base::VARINT:
			case Type\Base::TIMESTAMP:	//	use big int to present microseconds timestamp
				$unpacked = unpack('N2', $data);
				return $unpacked[1] << 32 | $unpacked[2];
			case Type\Base::BLOB:
				$length = unpack('N', substr($data, 0, 4))[1];
				return substr($data, 4, $length);
			case Type\Base::BOOLEAN:
				return (bool) unpack('C', $data)[1];
			case Type\Base::DECIMAL:
				$unpacked = unpack('N3', $data);
				$value = $unpacked[2] << 32 | $unpacked[3];
				$len = strlen($value);
				return substr($value, 0, $len - $unpacked[1]) . '.' . substr($value, $len - $unpacked[1]);
			case Type\Base::DOUBLE:
				return unpack('d', strrev($data))[1];
			case Type\Base::FLOAT:
				return unpack('f', strrev($data))[1];
			case Type\Base::INT:
				return unpack('N', $data)[1];
			case Type\Base::UUID:
			case Type\Base::TIMEUUID:
				$uuid = '';
				$unpacked = unpack('n8', $data);

				for ($i = 1; $i <= 8; ++$i) {
					if ($i == 3 || $i == 4 || $i == 5 || $i == 6) {
						$uuid .= '-';
					}
					$uuid .= str_pad(dechex($unpacked[$i]), 4, '0', STR_PAD_LEFT);
				}
				return $uuid;
			case Type\Base::INET:
				return inet_ntop($data);
			default:
				if (is_array($type)){
					switch($type['type']){
						case Type\Base::COLLECTION_LIST:
						case Type\Base::COLLECTION_SET:
							$dataStream = new DataStream($data);
							return $dataStream->readList($type['value']);
						case Type\Base::COLLECTION_MAP:
							$dataStream = new DataStream($data);
							return $dataStream->readMap($type['key'], $type['value']);
						case Type\Base::UDT:
							throw new Exception('Unsupported Type UDT.');
						case Type\Base::TUPLE:
							throw new Exception('Unsupported Type Tuple.');
						case Type\Base::CUSTOM:
						default:
							$length = unpack('N', substr($data, 0, 4))[1];
							return substr($data, 4, $length);
					}
				}

				trigger_error('Unknown type ' . var_export($type, true));
				return null;
		}
	}
}
