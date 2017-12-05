<?php

	class isoOutputEscaperArrayDecorator extends sfOutputEscaperArrayDecorator
	{
		public function current()
		{
			return isoOutputEscaper::escape($this->escapingMethod, current($this->value));
		}
		
		public function offsetGet($offset)
		{
			return isoOutputEscaper::escape($this->escapingMethod, $this->value[$offset]);
		}
	}

?>