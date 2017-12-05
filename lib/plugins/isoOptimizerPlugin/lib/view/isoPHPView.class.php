<?php

	class isoPHPView extends sfPHPView
	{
		protected $addedDefaultsVars = false;
				
		protected function initializeAttributeHolder($attributes = array())
		{
			return new isoViewParameterHolder($this->dispatcher, $attributes, array(
      			'escaping_method'   => sfConfig::get('sf_escaping_method'),
      			'escaping_strategy' => sfConfig::get('sf_escaping_strategy'),
    		));
		}
	}

?>