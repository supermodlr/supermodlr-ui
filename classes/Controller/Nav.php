<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Nav extends Controller_Block {
	
	public function action_index()
	{
		$parts = explode('/',$this->request->query('referer'));
		if (isset($parts[1]) && $parts[1] != '')
		{
			$model_name = $parts[1];
			$this->bind('model_name',$model_name);
		}
		
	}
	
}
