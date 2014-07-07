<?php
namespace Cassandra\Protocol\Response;

class Rows implements \Iterator, \Countable {

	/**
	 * @var array
	 */
	private $columns = [];

	/**
	 * @var int
	 */
	private $columnCount;

	/**
	 * @var int
	 */
	private $rowCount;

	/**
	 * @var int
	 */
	private $current = 0;

	/**
	 * @var array
	 */
	private $rows = [];

	public function __construct(DataStream $stream, array $metadata) {
		$this->columns = $metadata['columns'];
		$this->columnCount = $metadata['columnCount'];
		$this->rowCount = $stream->readInt();
		for ($i = 0; $i < $this->rowCount; ++$i) {
			$row = array();
			for ($j = 0; $j < $this->columnCount; ++$j) {
				try {
					$row[$this->columns[$j]['name']] = $stream->readBytes();
				} catch (\Exception $e) {
					$row[$this->columns[$j]['name']] = null;
				}
			}
			$this->rows[] = $row;
		}
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the current element
	 * @link http://php.net/manual/en/iterator.current.php
	 * @throws \OutOfRangeException
	 * @return mixed Can return any type.
	 */
	public function current() {
		if (!isset($this->rows[$this->current])) {
			throw new \OutOfRangeException('Invalid index');
		}
		$row = $this->rows[$this->current];
		for ($i = 0; $i < $this->columnCount; ++$i) {
			try {
				$data = new DataStream($this->rows[$this->current][$this->columns[$i]['name']]);
				$row[$this->columns[$i]['name']] = $data->readByType($this->columns[$i]['type']);
			} catch (\Exception $e) {
				trigger_error($e->getMessage());
				$row[$this->columns[$i]['name']] = null;
			}
		}
		return $row;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Move forward to next element
	 * @link http://php.net/manual/en/iterator.next.php
	 * @return void Any returned value is ignored.
	 */
	public function next() {
		++$this->current;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the key of the current element
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return mixed scalar on success, or null on failure.
	 */
	public function key() {
		return $this->current;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Checks if current position is valid
	 * @link http://php.net/manual/en/iterator.valid.php
	 * @return boolean The return value will be casted to boolean and then evaluated.
	 * Returns true on success or false on failure.
	 */
	public function valid() {
		return $this->current < $this->rowCount;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Rewind the Iterator to the first element
	 * @link http://php.net/manual/en/iterator.rewind.php
	 * @return void Any returned value is ignored.
	 */
	public function rewind() {
		$this->current = 0;
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Count elements of an object
	 * @link http://php.net/manual/en/countable.count.php
	 * @return int The custom count as an integer.
	 * </p>
	 * <p>
	 * The return value is cast to an integer.
	 */
	public function count() {
		return $this->rowCount;
	}

	/**
	 * Return rows as array
	 * @return array
	 */
	public function asArray() {
		$items = [];
		foreach($this as $item) {
			$items[] = $item;
		}

		return $items;
	}
}