<?php

	/**
	 * Evaluates and returns common partial.
	 *
	 * @param string $partialName
	 * @param array $vars
	 * @return string
	 */
	function get_common_partial($partialName, array $vars = array())
	{
		$context = sfContext::getInstance();

		$view = new isoCommonPartialView($context, '_' . $partialName);
		$view->setPartialVars($vars);

		return $view->render();
	}

	/**
	 * Includes common partial to template.
	 *
	 * @param string $partialName
	 * @param array $vars
	 */
	function include_common_partial($partialName, array $vars = array())
	{
		echo get_common_partial($partialName, $vars);
	}

?>