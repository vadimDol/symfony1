<?php

	class isoGenerateEscapersTask extends isoBaseTask
	{
		protected function configure()
		{
			$this->addOptions(array(
				new sfCommandOption('db_name', 'db', sfCommandOption::PARAMETER_REQUIRED, 'Name of database'),
				new sfCommandOption('model',   'm',  sfCommandOption::PARAMETER_REQUIRED, 'Name of model for which escapers would be generated')
			));
			
			$this->namespace        = 'propel';
			$this->name             = 'generate-escaper';
			$this->briefDescription = 'Generates escaper for specified model.';
			$this->detailedDescription = 'Generates escaper for specified model';
		}
		
		protected function executeTask($arguments = array(), $options = array())
		{
			$dbName    = $options['db_name'];
			$modelName = $options['model'];
			
			if (!$modelName)
			{
				die("Please specify model");
			}
			
			$schema = $this->getSchema($dbName);
			$models = $schema[$dbName];
			
			if ( !isset($models[$modelName]) )
			{
				die("Model '$modelName' doesn't exists!\n");
			}
			
			$modelInfo = $models[$modelName];

			//TODO: add checking for existing attributes
			$modelAttrs = $modelInfo['_attributes'];
				
			$modelClass   = $modelAttrs['phpName'];
			$modelPackage = $modelAttrs['package'];
				
			$this->addEscaperFor($modelClass, $modelPackage);
		}
		
		/**
		 * Adds escaper for specified model.
		 * 
		 * @param string $modelClass
		 * @param string $modelPackage
		 */
		protected function addEscaperFor($modelClass, $modelPackage)
		{
			$escaperClasses = $this->generateModelEscaper($modelClass);			
			
			$classNames      = array_keys($escaperClasses);
			$baseEscaperName = $classNames[0];
			$escaperName     = $classNames[1];
			
			
			$modelDir = sfConfig::get('sf_root_dir') . "/" . str_replace(".", "/", $modelPackage);
			$baseModelDir = $modelDir . "/om";
			
			$baseEscaperPath = $baseModelDir . "/$baseEscaperName.class.php";
			file_put_contents($baseEscaperPath, 
				"<?php\n\n" . $escaperClasses[$baseEscaperName] . "\n\n?>" 
			);
			
			$escaperPath = $modelDir . "/$escaperName.class.php";
			if ( !file_exists($escaperPath) )
			{
				file_put_contents($escaperPath, 
					"<?php\n\n" . $escaperClasses[$escaperName] . "\n\n?>" 
				);
			}
		}
		
		/**
		 * Generates escaper class for specified model.
		 * Returns array with base and regural escaper classes.
		 * 
		 * @param string $modelClass
		 * @return array (className => classContent)
		 */
		protected function generateModelEscaper($modelClass)
		{
			$tableMap = call_user_func( array($modelClass . 'Peer', 'getTableMap') );
			
			$escapingMethods = array();
			
			$columns = $tableMap->getColumns();
			foreach ($columns as $column)
			{
				$escapingMethods[] = $this->generateColumnEscaper($column);
			}
			
			$baseEscaperClass = "
				class Base{$modelClass}Escaper extends isoOutputEscaperObjectDecorator
				{
				" . implode("\n", $escapingMethods) . "
				}";
			
			$escaperClass = "class {$modelClass}Escaper extends Base{$modelClass}Escaper\n{\n}";
						
			return array(
				"Base{$modelClass}Escaper" => $baseEscaperClass,
				"{$modelClass}Escaper"     => $escaperClass
			);
		}
		
		/**
		 * Generates escaping method for specified model's column.
		 * 
		 * @param ColumnMap $column
		 * @return string
		 */
		protected function generateColumnEscaper(ColumnMap $column)
		{
			$needEscaping = $column->isText();
			$getterFunc   = "get" . $column->getPhpName();
			
			$returnValue  = "\$this->value->$getterFunc()";
			if ($needEscaping)
			{
				$returnValue = "isoOutputEscaper::escape(\$this->escapingMethod, $returnValue)";
			}

			return "
				function $getterFunc()
				{
					return $returnValue;
				}";
		}
		
		/**
		 * Returns parsed database schema.
		 * 
		 * @param string $dbName
		 * @return array
		 */
		protected function getSchema($dbName)
		{
			$schemaLocation = sfConfig::get('sf_root_dir') . '/config/' . $dbName . '.schema.yml';
			$parser = new sfYamlParser();
			return $parser->parse( file_get_contents($schemaLocation) );
		}
	}

?>