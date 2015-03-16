<?php
namespace Cassandra\Type;

abstract class Base{
	
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
	const TEXT = 0x000A;		// deprecated in Protocol v3
	const TIMESTAMP = 0x000B;
	const UUID = 0x000C;
	const VARCHAR = 0x000D;
	const VARINT = 0x000E;
	const TIMEUUID = 0x000F;
	const INET = 0x0010;
	const COLLECTION_LIST = 0x0020;
	const COLLECTION_MAP = 0x0021;
	const COLLECTION_SET = 0x0022;
	const UDT = 0x0030;
	const TUPLE = 0x0031;
	
	/**
	 * 
	 * @var mixed
	 */
	protected $_value;
	
	/**
	 * 
	 * @param mixed $value
	 */
	public function __construct($value){
		$this->_value = $value;
	}
	
	/**
	 * @return string
	 */
	abstract public function getBinary();
	
	/**
	 * @return string
	 */
	public function getValue(){
		return $this->_value;
	}
	
	/**
	 * @return string
	 */
	public function __toString(){
		return (string) $this->_value;
	}
	
	/**
	 * 
	 * @param int|array $dataType
	 * @param mixed $value
	 * @throws Exception
	 * @return Base|null
	 */
	public static function getTypeObject($dataType, $value) {
		if ($value === null)
			return null;
		
		switch($dataType) {
			case self::BLOB:
				return new Blob($value);
			case self::TIMESTAMP:
				return new Timestamp($value);
			case self::COUNTER:
				return new Counter($value);
			case self::BIGINT:
				return new Bigint($value);
			case self::VARINT:
				return new Varint($value);
	
			case self::BOOLEAN:
				return new Boolean($value);
	
			case self::DECIMAL:
				return is_array($value) ? new Decimal($value[0], $value[1]) : new Decimal($value);

			case self::DOUBLE:
				return new Double($value);
	
			case self::FLOAT:
				return new Float($value);
	
			case self::INET:
				return new Inet($value);
	
			case self::INT:
				return new Int($value);
	
			case self::ASCII:
				return new Ascii($value);
			case self::VARCHAR:
				return new Varchar($value);
			case self::TEXT:	//	deprecated in Protocol v3
				return new Varchar($value);
	
			case self::TIMEUUID:
				return new Timeuuid($value);
			case self::UUID:
				return new Uuid($value);

			default:
				if (is_array($dataType)){
					switch($dataType['type']){
						case self::CUSTOM:
							return new Custom($value, $dataType['name']);
						case self::COLLECTION_SET:
							return new CollectionSet($value, $dataType['value']);
						case self::COLLECTION_LIST:
							return new CollectionList($value, $dataType['value']);
						case self::COLLECTION_MAP:
							return new CollectionMap($value, $dataType['key'], $dataType['value']);
						case self::UDT:
							return new UDT($value, $dataType['typeMap']);
						case self::TUPLE:
							return new Tuple($value, $dataType['typeList']);
						default:
							return new Blob($value);
					}
				}
				trigger_error('Unknown type.');
		}
	
		return '';
	}
}
