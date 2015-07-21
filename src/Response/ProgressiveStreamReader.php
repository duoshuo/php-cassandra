<?php
namespace Cassandra\Response;

class ProgressiveStreamReader extends StreamReader{
    
    /**
     */
    protected $source;

    protected $dataLength = 0;
     
    public function __construct($data = ''){
        $this->data = $data;
        $this->dataLength = strlen($data);
    }
    
    public function setSource($source){
        $this->source = $source;
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
            if ($this->source === null)
                throw new Exception('The response is incomplete, or types expectation mismatch.');
            
            $this->data .= $received = $this->source->readOnce($this->offset + $length - $this->dataLength);
            $this->dataLength += strlen($received);
        }
    
        $output = substr($this->data, $this->offset, $length);
        $this->offset += $length;
        return $output;
    }
}
