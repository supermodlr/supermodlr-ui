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
			'Field'=> array(
				APPPATH.'classes'.DIRECTORY_SEPARATOR.'Field'.DIRECTORY_SEPARATOR,
				MODPATH.'supermodlr'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'Field'.DIRECTORY_SEPARATOR,			
			),
			'Model'=> array(
				APPPATH.'classes'.DIRECTORY_SEPARATOR.'Model'.DIRECTORY_SEPARATOR,
				MODPATH.'supermodlr'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'Model'.DIRECTORY_SEPARATOR,
			),			
		);

		$Model = new Model_Model();
		$Drivers = $Model->cfg('drivers');
		$DBDriver = $Drivers[0];

		/*$Field = new Model_Field();
		$Field_Drivers = $Field->cfg('drivers');
		$Field_Driver = $Field_Drivers[0];*/

		//loop through class types to look for
		foreach ($paths as $type => $type_arry) 
		{
			//loop through all paths set for each class type
			foreach ($type_arry as $path)
			{
				//ensure the dir exists
				if (!is_dir($path)) continue;
				
				//open all classes for this type
				$classes = scandir($path);
				foreach ($classes as $filename) 
				{
					if (substr($filename,0,1) == '.') continue;

					//@todo make this recursive
					if (is_dir($path.$filename))
					{
						$sub_classes = scandir($path.$filename);
						$sub_path = $path.$filename.DIRECTORY_SEPARATOR;
						foreach ($sub_classes as $sub_filename) 
						{
							if (substr($sub_filename,0,1) == '.') continue;

							//get file info
							$info = pathinfo($sub_path.$sub_filename);

							//@todo fix this hack for the _id field
							if ($info['filename'] == 'Id')
							{
								$info['filename'] = '_Id';
							}
							//get class name
							$class_name = $type.'_'.$filename.'_'.$info['filename'];

							//if this file hasn't already been included (and do not allow autoload)
							if (!isset($loaded[$class_name]))
							{

								$obj = $this->class_to_db_object($class_name, $sub_path.$sub_filename);
								$loaded[$class_name] = TRUE;

								//store it in the local db
								$collection = strtolower($type);

								//get the current obj from the db, if it exists
								$entry = $DBDriver->read(array(
									'from'=> $collection,
									'where'=> array('_id'=> $obj['_id'])
								));

								//if already set, update
								if ($entry)
								{
									unset($obj['_id']);
									$saved = $DBDriver->update(array(
										'into' => $collection,
										'where'=> array('_id'=> $class_name),
										'set'  => $obj,
									));
								}
								//not yet set, create
								else
								{
									$saved = $DBDriver->create(array(
										'into' => $collection,
										'set'  => $obj,
									));
								}
								echo $class_name.' Saved: '.var_export($saved,TRUE).'<br/><br/>';
												
							}
						}
						continue;
					}

					//get file info
					$info = pathinfo($path.$filename);
					$class_name = $type.'_'.$info['filename'];

					//if this file hasn't already been included (and do not allow autoload)
					if (!isset($loaded[$class_name]))
					{

						$obj = $this->class_to_db_object($class_name, $path.$filename);
						$loaded[$class_name] = TRUE;

						//store it in the local db
						$collection = strtolower($type);

						//get the current obj from the db, if it exists
						$entry = $DBDriver->read(array(
							'from'=> $collection,
							'where'=> array('_id'=> $obj['_id'])
						));

						//if already set, update
						if ($entry)
						{
							unset($obj['_id']);
							$saved = $DBDriver->update(array(
								'into' => $collection,
								'where'=> array('_id'=> $class_name),
								'set'  => $obj,
							));
						}
						//not yet set, create
						else
						{
							$saved = $DBDriver->create(array(
								'into' => $collection,
								'set'  => $obj,
							));
						}

						echo $class_name.' Saved: '.var_export($saved,TRUE).'<br/><br/>';			
					}

				}
			}
		
		}
	}


	public function class_to_db_object($class_name, $path_to_file) 
	{
		$json = array();

		if (!class_exists($class_name,FALSE)) 
		{
			//read the file
			include $path_to_file;
		}

		$Class = new ReflectionClass($class_name);
		$Object = new $class_name();

		$Class_Source = file($path_to_file);

		//set class name as _id
		$json['_id'] = $class_name;

		//set all non static properties
		foreach ($Class->getProperties() as $property) 
		{
			//skip static and inheirited properties
			if ($property->isStatic() || $property->class != $class_name) continue;

			//get the default property value
			$json[$property->name] = $property->getValue($Object);
		}

		$is_model = FALSE;
		$is_field = FALSE;
		if (substr($class_name,0,5) === 'Model')
		{
			$is_model = TRUE;
		}
		else if (substr($class_name,0,5) === 'Field')
		{			
			$is_field = TRUE;
		}
		//get name, if not set
		if (!isset($json['name']))
		{
			if ($is_model)
			{
				$json['name'] = Model_Model::get_name_from_class($class_name);
			}
			else if ($is_field)
			{
				$json['name'] = Model_Field::get_name_from_class($class_name);
			}	
		}

		//set extends, if not set
		if (!isset($json['extends']))
		{		
			$extends = get_parent_class($class_name);

			//extends is null if it is supposed to point to core
			if (strtolower($extends) === 'supermodlr')
			{
				$json['extends'] = NULL;
			}
			else
			{
				$extends_rel = NULL;
				if ($is_model)
				{
					$extends_rel = array('model'=> 'model', '_id'=> $extends);
				}
				else if ($is_field)
				{
					$extends_rel = array('model'=> 'field', '_id'=> $extends);
				}
				
				$json['extends'] = $extends_rel;
			}
		}

		//get description, if any
		if (!isset($json['description']))
		{
			//get description, if any
			preg_match('/\/\*\*.*FileDescription:(.*)\*\//s',implode("",$Class_Source),$matches);

			//if a description was found
			if (isset($matches[1]))
			{
				$json['description'] = $matches[1];
			}
			else
			{
				$json['description'] = '';
			}
		}


		//get fields
		if ($is_model && isset($Object::$__scfg[$json['name'].'.field_keys']))
		{
			//get all field keys
			$field_keys = $Object::$__scfg[$json['name'].'.field_keys'];

			//loop through all set field keys
			foreach ($field_keys as $key)
			{
				//skip pk which is auto-set and not stored in fields list
				if (strtolower($key) == strtolower($Object->cfg('pk_name'))) continue;

				//construct proper field rel value and add it to the json object
				$json['fields'][] = array('model'=> 'field', '_id'=> 'Field_'.ucfirst($json['name']).'_'.ucfirst($key));
			}
			

		}

		//loop through all methods
		foreach ($Class->getMethods() as $method) 
		{

			//if this method is not inheireted from a parent class
			if (preg_match('/(.*)Method \[ <user>/s',$method->__toString(),$matches)) 
			{
				//get comments, if any
				if (isset($matches[1]))
				{
					$comment = $matches[1];
				}
				else
				{
					$comment = NULL;
				}

				//if this is an event method
				if (preg_match('/^event__/i',$method->name))
				{
					$parts = explode('__',$method->name);
					$event_name = array_pop($parts);
				}
				else
				{
					$event_name = NULL;
				}

				//get method contents from php file
				//@@ C:\Users\Justin\www\mmgi\application\classes\Model\Websiteuser.php 12 - 16
				preg_match("/\@\@ .+[.]php ([0-9]+) - ([0-9]+)/i", $method->__toString(),$matches);
				list($line,$start,$end) = $matches;
				$source = array_slice($Class_Source, $start-1, ($end-$start));
				
				$json['methods'][] = array(
					'name' => $method->name,
					'comment' => $comment,
					'source' => implode("",$source),
					'event' => $event_name,
				);
			}
		}

		return $json;
	}

}