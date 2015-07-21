<?php
namespace Cassandra\Response;

use Cassandra\Type;

class StreamReader {

    /**
     * @var string
     */
    protected $data;
    
    /**
     * @var int
     */
    protected $offset = 0;

    public function __construct($data){
        $this->data = $data;
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
     * @param array $definition [$valueType]
     * @return array
     */
    public function readList(array $definition) {
        list($valueType) = $definition;
        $list = [];
        $count = $this->readInt();
        for ($i = 0; $i < $count; ++$i) {
            $list[] = $this->readValue($valueType);
        }
        return $list;
    }

    /**
     * Read map.
     *
     * @param array $definition [$keyType, $valueType]
     * @return array
     */
    public function readMap(array $definition) {
        list($keyType, $valueType) = $definition;
        $map = [];
        $count = $this->readInt();
        for ($i = 0; $i < $count; ++$i) {
            $map[$this->readValue($keyType)] = $this->readValue($valueType);
        }
        return $map;
    }

    /**
     * 
     * @param array $definition ['key1'=>$valueType1, 'key2'=>$valueType2, ...]
     * @return array
     */
    public function readTuple(array $definition) {
        $tuple = [];
        $dataLength = strlen($this->data);
        foreach ($definition as $key => $type) {
            if ($this->offset < $dataLength)
                $tuple[$key] = $this->readValue($type);
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
     * alias of readValue()
     * @deprecated
     * 
     * @param int|array $type
     * @return mixed
     */
    public function readBytesAndConvertToType($type){
        return $this->readValue($type);
    }
    
    /**
     * read a [bytes] and read by type
     *
     * @param int|array $type
     * @return mixed
     */
    public function readValue($type){
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
            return $class::parse($data);
        }
        else{
            if (!isset(Type\Base::$typeClassMap[$type['type']]))
                throw new Type\Exception('Unknown type ' . var_export($type, true));
            $class = Type\Base::$typeClassMap[$type['type']];
            return $class::parse($data, $type['definition']);
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
                    'definition'=> [$this->readString()],
                ];
            case Type\Base::COLLECTION_LIST:
            case Type\Base::COLLECTION_SET:
                return [
                    'type'    => $type,
                    'definition'    => [$this->readType()],
                ];
            case Type\Base::COLLECTION_MAP:
                return [
                    'type'    => $type,
                    'definition'=> [$this->readType(), $this->readType()],
                ];
            case Type\Base::UDT:
                $data = [
                    'type'        => $type,
                    'keyspace'    => $this->readString(),
                    'name'        => $this->readString(),
                    'definition'    => [],
                ];
                $length = $this->readShort();
                for($i = 0; $i < $length; ++$i){
                    $key = $this->readString();
                    $data['definition'][$key] = $this->readType();
                }
                return $data;
            case Type\Base::TUPLE:
                $data = [
                    'type'    => $type,
                    'definition'    =>    [],
                ];
                $length = $this->readShort();
                for($i = 0; $i < $length; ++$i){
                    $data['definition'][] = $this->readType();
                }
                return $data;
            default:
                return $type;
        }
    }
}
