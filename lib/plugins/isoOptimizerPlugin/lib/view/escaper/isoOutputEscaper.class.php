<?php

	class isoOutputEscaper
	{
		const ESCAPING_ARRAY_MAX_LENGTH = 30;
		
		public static function escape($escapingMethod, $value)
		{
			if ( is_null($value) )
			{
				return $value;
			}
			
			if ( is_scalar($value) )
			{
				if ( is_string($value) )
				{
					return call_user_func($escapingMethod, $value);
				}
				return $value;
			}
			
			if ( is_array($value) )
			{
				$length = count($value);
				if ( $length <= self::ESCAPING_ARRAY_MAX_LENGTH )
				{
					$escapedArray = array();
					foreach ($value as $key => $val)
					{
						$escapedArray[$key] = self::escape($escapingMethod, $val);
					}
					
					return $escapedArray;
				}
				else
				{
					return new isoOutputEscaperArrayDecorator($escapingMethod, $value);
				}
			}
			
			if ($value instanceof sfOutputEscaper)
			{
				return clone $value;
			}
			
			if ($value instanceof sfOutputEscaperSafe)
			{
				return $value->getValue();
			}
			
			$valueClass   = get_class($value);
			$valueEscaper = $valueClass . "Escaper";
			if ( class_exists($valueEscaper, false) )
			{
				return new $valueEscaper($escapingMethod, $value);
			}
			
			if ($value instanceof Traversable)
			{
				return new isoOutputEscaperIteratorDecorator($escapingMethod, $value);
			}
			
			return new isoOutputEscaperObjectDecorator($escapingMethod, $value);
		}
	}

?>