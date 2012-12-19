<?php

/**
  * this file will read all model and field classes from application and core (/modules/supermodlr/classes) and pull them into the configured db if they don't exist yet
  *
  */
class Controller_Install extends Controller {

	public function action_index() 
	{
		//@todo support looping through all other modules as well
		$paths = array(
			APPPATH.'classes'.DIRECTORY_SEPARATOR,
			MODPATH.'supermodlr'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR,
		);

		foreach ($paths as $path) 
		{
			$model_path = $path.'Model'.DIRECTORY_SEPARATOR;

			if (!is_dir($model_path)) continue;
			
			//open all models
			$models = scandir($model_path);
			foreach ($models as $filename) 
			{
				if (substr($filename,0,1) == '.') continue;

				//get file info
				$info = pathinfo($model_path.$filename);
				$class_name = 'Model_'.$info['filename'];
echo $class_name.'<br/>';
				//if this file hasn't already been included (and do not allow autoload)
				if (!class_exists($class_name,FALSE)) 
				{
					//read the file
					include $model_path.$filename;

					$refclass = new ReflectionClass($class_name);

					$extends = get_parent_class($class_name);

					echo $class_name.' extends '.$extends.'<br/>';

					foreach ($refclass->getProperties() as $property) 
					{
						echo $property.'<br/>';
					}

					//store it in the local db					
				}

			}
			



			//open all fields

				//read the file

				//store it in the local db

		}

ob_end_flush();

	}


	public function class_to_json_object($class_name) 
	{
		$json = array();
		$Class = new ReflectionClass($class_name);

		foreach ($refclass->getProperties() as $property) 
		{

		}


	}


	//@todo use reflection export to get the source code for each method

}