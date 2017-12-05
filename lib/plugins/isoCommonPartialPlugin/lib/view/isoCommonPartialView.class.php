<?php

	class isoCommonPartialView extends isoPartialView
	{
		public function __construct($context, $partialName)
		{
			parent::__construct($context, 'common_partial', $partialName, '');
		}
		
		public function configure()
		{
			$this->setDecorator(false);
			$this->setTemplate(sfConfig::get('app_common_partials_path') . '/' . $this->getActionName() . $this->getExtension());
		}
	}

?>