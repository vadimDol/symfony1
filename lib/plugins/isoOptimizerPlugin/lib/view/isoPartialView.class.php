<?php

	class isoPartialView extends sfPartialView
	{
		public function initialize($context, $moduleName, $actionName, $viewName)
		{
			$this->moduleName = $moduleName;
			$this->actionName = $actionName;
			$this->viewName   = $viewName;
			
			$this->context    = $context;
			$this->dispatcher = $context->getEventDispatcher();
			
			$this->attributeHolder = $this->initializeAttributeHolder();
			$this->parameterHolder = new sfParameterHolder();
			
			$this->configure();
		}
		
		public function configure()
		{
			$this->setDecorator(false);
			
			$this->template = $this->actionName . ".php";
			if ('global' == $this->moduleName)
			{
				$this->directory = sfConfig::get('sf_app_template_dir');
			}
			else
			{
				$this->directory = $this->getModuleTemplateDir( $this->moduleName );
			}
		}
		
		/**
		 * Returns template directory for specified module.
		 * 
		 * @param string $moduleName
		 * @return string
		 */
		protected function getModuleTemplateDir($moduleName)
		{
			return sfConfig::get('sf_app_module_dir') . '/' . $moduleName . '/templates';
		}
		
		public function setPartialVars(array $partialVars)
		{
			$this->partialVars = $partialVars;
		}
		
		public function getCache()
		{
			return null;
		}
		
		public function render()
		{
			return $this->renderFile( $this->getDirectory() . '/' . $this->getTemplate() );
		}
		
		protected function getVars()
		{
			$context = sfContext::getInstance();
			$vars    = $this->partialVars;
			
			$escapingMethod = sfConfig::get('sf_escaping_method');
						
			//add default variables
			$defaultVars = $context->filterTemplateParameters( new sfEvent($this, ""), array() );
			$defaultVars = array_merge( $defaultVars, $this->getAttributeHolder()->getAll() );
			
			foreach ($defaultVars as $varName => $varValue)
			{
				$vars[$varName] = isoOutputEscaper::escape( $escapingMethod, $varValue );
			}
			
			return $vars;
		}
		
		protected function renderFile($file)
		{
			$this->loadCoreAndStandardHelpers();
			
			$vars = $this->getVars();
			extract($vars);
			
			ob_start();
    		ob_implicit_flush(0);
    		try
			{
				require($file);
			}
			catch (Exception $e)
			{
				ob_end_clean();
				throw $e;
			}

			return ob_get_clean();
		}
		
		protected function initializeAttributeHolder($attributes = array())
		{
			$holder = new sfParameterHolder();
			$holder->add( $attributes );
			
			return $holder;
		}
	}

?>