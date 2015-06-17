<?php
namespace Cassandra\Response;

use Cassandra\Type;

class StreamReader {

    /**
     */
    protected $source;

    /**
     * @var string
     */
    protected $data = '';
    
    /**
     * @var int
     */
    protected $offset = 0;

    protected $dataLength = 0;

    protected $totalLength = 0;

    public function __construct($totalLength){
        $this->totalLength = $totalLength;
    }

    public function setSource($source){
        $this->source = $source;
    }

    public static function createFromData($data){
        $length = strlen($data);
        $stream = new self($length);
        $stream->data = $data;
        $stream->dataLength = $length;
        return $stream;
    }

    /**
     * Read data from stream.
     * 
     * NOTICE When $this->offset == strlen($this->data), substr() will return false.  You'd better avoid call read() when $length == 0.
     *
     * @param int $length  $length should be > 0.
     * @return string
     */
    protected function read($length) {
        while($this->dataLength < $this->offset + $length){
            $this->data .= $received = $this->source->readOnce($this->totalLength - $this->dataLength);
            $this->dataLength += strlen($received);
        }

        $output = substr($this->data, $this->offset, $length);
        $this->offset += $length;
        return $output;
    }

    public function offset($offset){
        $this->offset = $offset;
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
        return $length === 0 ? '' : $this->read($length);
    }

    /**
     * Read long string.
     *
     * @return string
     */
    public function readLongString() {
        $length = unpack('N', $this->read(4))[1];
        return $length === 0 ? '' : $this->read($length);
    }

    /**
     * Read bytes.
     *
     * @return string
     */
    public function readBytes() {
        $binaryLength = $this->read(4);
        if ($binaryLength === "\xff\xff\xff\xff")
            return null;

        $length = unpack('N', $binaryLength)[1];
        return $length === 0 ? '' : $this->read($length);
    }

    /**
     * Read uuid.
     *
     * @return string
     */
    public function readUuid() {
        $data = unpack('n8', $this->read(16));

        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8]);
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

    public function readTuple($types) {
        $tuple = [];
        $dataLength = strlen($this->data);
        foreach ($types as $key => $type) {
            if ($this->offset < $dataLength)
                $tuple[$key] = $this->readBytesAndConvertToType($type);
            else
                $tuple[$key] = null;
        }
        return $tuple;
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
     * read a [bytes] and read by type
     *
     * @param int|array $type
     * @return mixed
     */
    public function readBytesAndConvertToType($type){
        $binaryLength = substr($this->data, $this->offset, 4);
        $this->offset += 4;

        if ($binaryLength === "\xff\xff\xff\xff")
            return null;

        $length = unpack('N', $binaryLength)[1];

        // do not use $this->read() for performance
        // substr() returns FALSE when OFFSET is equal to the length of data
        $data = ($length == 0) ? '' : substr($this->data, $this->offset, $length);
        $this->offset += $length;
        if(!is_array($type)){
            $class = Type\Base::$typeClassMap[$type];
            $valueObject = new $class();
            $valueObject->setBinary($data);
            return $valueObject->getValue();
        }
        else{
            $class = Type\Base::$typeClassMap[$type['type']];
            switch($type['type']){
                case Type\Base::COLLECTION_LIST:
                case Type\Base::COLLECTION_SET:
                    return (new $class(null, $type['value']))->setBinary($data)->getValue();
                case Type\Base::COLLECTION_MAP:
                    return (new $class(null, $type['key'], $type['value']))->setBinary($data)->getValue();
                case Type\Base::UDT:
                    return (new $class(null, $type['typeMap']))->setBinary($data)->getValue();
                case Type\Base::TUPLE:
                    return (new $class(null, $type['typeList']))->setBinary($data)->getValue();
                case Type\Base::CUSTOM:
                default:
                    $length = unpack('N', substr($data, 0, 4))[1];
                    return substr($data, 4, $length);
            }

            throw new Type\Exception('Unknown type ' . var_export($type, true));
        }
    }

    /**
     * @return int|array
     */
    public function readType(){
        $type = $this->readShort();
        switch ($type) {
            case Type\Base::CUSTOM:
                return [
                    'type'    => $type,
                    'name'    => $this->readString(),
                ];
            case Type\Base::COLLECTION_LIST:
            case Type\Base::COLLECTION_SET:
                return [
                    'type'    => $type,
                    'value'    => self::readType(),
                ];
            case Type\Base::COLLECTION_MAP:
                return [
                    'type'    => $type,
                    'key'    => self::readType(),
                    'value'    => self::readType(),
                ];
            case Type\Base::UDT:
                $data = [
                    'type'        => $type,
                    'keyspace'    => $this->readString(),
                    'name'        => $this->readString(),
                    'typeMap'    => [],
                ];
                $length = $this->readShort();
                for($i = 0; $i < $length; ++$i){
                    $key = $this->readString();
                    $data['typeMap'][$key] = self::readType();
                }
                return $data;
            case Type\Base::TUPLE:
                $data = [
                    'type'    => $type,
                    'typeList'    =>    [],
                ];
                $length = $this->readShort();
                for($i = 0; $i < $length; ++$i){
                    $data['typeList'][] = self::readType();
                }
                return $data;
            default:
                return $type;
        }
    }
}
