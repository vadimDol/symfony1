<?php

	class isoOutputEscaperIteratorDecorator extends sfOutputEscaperIteratorDecorator
	{
		protected $iterator;

		public function __construct($escapingMethod, Traversable $value)
		{
			parent::__construct($escapingMethod, $value);

			$this->iterator = new IteratorIterator($value);
		}

		public function current()
		{
			return isoOutputEscaper::escape($this->escapingMethod, $this->iterator->current());
		}
		
		public function offsetGet($offset)
		{
			return isoOutputEscaper::escape($this->escapingMethod, $this->value[$offset]);
 		}

		public function rewind()
		{
			return $this->iterator->rewind();
		}

		public function key()
		{
			return $this->iterator->key();
		}

		public function next()
		{
			return $this->iterator->next();
		}

		public function valid()
		{
			return $this->iterator->valid();
		}
	}

?>