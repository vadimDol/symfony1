<?php

	class isoOutputEscaperObjectDecorator extends sfOutputEscaperObjectDecorator
	{
		public function __call($method, $args)
		{
			$needEscaping = true;
			
			$countArgs = count($args);
			if ($countArgs > 0)
			{
				$lastArg = $args[$countArgs - 1];
				if ($lastArg === ESC_RAW)
				{
					array_pop($args);
					$needEscaping = false;
				}
			}
			
			$value = call_user_func_array( array($this->value, $method), $args );
			if ($needEscaping)
			{			
				$value = isoOutputEscaper::escape($this->escapingMethod, $value);
			}
			
			return $value;
		}
		
		public function __toString()
		{
			return isoOutputEscaper::escape($this->escapingMethod, $this->value->__toString());
		}
	}

?>