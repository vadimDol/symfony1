<?php

	class isoViewParameterHolder extends sfViewParameterHolder
	{
		public function toArray()
		{
			$event = $this->dispatcher->filter( new sfEvent($this, 'template.filter_parameters'), $this->getAll() );
			$vars  = $event->getReturnValue();
			
			if ( $this->isEscaped() )
			{
				$escapingMethod = $this->getEscapingMethod();
				foreach ($vars as $name => $value)
				{
					$vars[$name] = isoOutputEscaper::escape($escapingMethod, $value);
				}
			}
			
			return $vars;
		}
	}

?>