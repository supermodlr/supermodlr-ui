<?php

class Supermodlrui {

	/**
	 * model_loaded only when supermodlr ui is loaded/enabled
	 * on model db load (if model, field, or trait)
	 *    - get class file path
	 *    - get json object of class
	 *    - compare to loaded object
	 *    - if different, then assume file is correct 
	 *        - write json object generated from file to loaded model
	 *        - save model
	 * 
	 * @param mixed $params Description.
	 *
	 * @access public
	 * @static
	 *
	 * @return mixed Value.
	 */
	public static function model_loaded($params)
	{
		$debug = FALSE;
		$class_name = get_class($params['this']);
		if (in_array($class_name, array('Model_Model','Model_Field','Model_Trait')))
		{
			$object = $params['this'];
			$object_class = $object->_id;

			// Get reflection instance of this class
			$rClass = new ReflectionClass($object_class);

			// Get path to file
			$class_path = $rClass->getFileName();        

			// Get file contents (remove all newlines to ignore system differences)
			$file_contents = preg_replace('/\r|\n/','',file_get_contents($class_path));

			$file_contents = preg_replace('/\s+/',' ',$file_contents);
			$file_contents = preg_replace('/\t+/',' ',$file_contents);

			$file_contents = trim(iconv(mb_detect_encoding($file_contents, mb_detect_order(), TRUE), "UTF-8", $file_contents));

			// Get existing file contents
			$file_contents_hash = md5($file_contents);

			// Get db contents (remove all newlines to ignore system differences)
			$db_contents = preg_replace('/\r|\n/','',$object->generate_class_file_contents());

			$db_contents = preg_replace('/\s+/',' ',$db_contents);
			$db_contents = preg_replace('/\t+/',' ',$db_contents);

			$db_contents = trim(iconv(mb_detect_encoding($db_contents, mb_detect_order(), TRUE), "UTF-8", $db_contents));

			// Generate file contents from db version of object
			$db_contents_hash = md5($db_contents);

			// if file does not match entry in the db
			if ($file_contents_hash !== $db_contents_hash)
			{

				fbl('Rewriting class: '.$class_name);

				// Get the new object to save to the db
				$new_db_contents = self::class_to_db_object($object_class, $class_path);

				// Loop through each prop
				foreach ($new_db_contents as $prop => $val) 
				{
					try {
						// Set the new value on the object
						$object->set($prop,$val);
					}
					catch (Exception $E)
					{
						unset($object->$prop);
					}
				}

				$object->cfg('create_file',FALSE);

				// Re-save this object
				$object->save();

				$object->cfg('create_file',TRUE);
			}

		}

	}

    /**
     * class_to_db_object
     * 
     * @param mixed $class_name   Description.
     * @param mixed $path_to_file Description.
     *
     * @access public
     *
     * @return mixed Value.
     */
	public static function class_to_db_object($class_name, $path_to_file) 
	{
		$json = array();

		if (!class_exists($class_name,FALSE)) 
		{
			// Read the file
			require_once $path_to_file;
		}

		$is_model = FALSE;
		$is_field = FALSE;
		$is_trait = FALSE;
		if (substr($class_name,0,5) === 'Model')
		{
			$is_model = TRUE;
		}
		else if (substr($class_name,0,5) === 'Field')
		{           
			$is_field = TRUE;
		}
		else if (substr($class_name,0,5) === 'Trait')
		{           
			$is_trait = TRUE;
		}


		if ($is_trait)
		{
			$temp_class_name = "Class_".$class_name;
			eval("class ".$temp_class_name." { use ".$class_name.";}");
			$Object = new $temp_class_name();
			$Object_Model = Model_Trait::factory();
			$Class = new ReflectionClass($temp_class_name);
		}
		else
		{
			$Object = $class_name::factory();

			if ($is_model)
			{
				$Object_Model = Model_Model::factory();
			}
			else if ($is_field)
			{
				$Object_Model = Model_Field::factory();
			}

			$Class = new ReflectionClass($class_name);
		}

		$fields = $Object_Model->get_fields();

		$Class_Source = file($path_to_file);

		// Set class name as _id
		$json['_id'] = $class_name;

		// Set all non static properties
		foreach ($Class->getProperties() as $property) 
		{
			// Skip static and inherited properties
			if ($property->isStatic() || $property->class != $class_name) continue;

			//get the default property value
			if (isset($fields[$property->name]))
			{
				$value = $fields[$property->name]->store_value($property->getValue($Object),'storage');
			}
			else
			{
				$value = $property->getValue($Object);
			}
			$json[$property->name] = $value;
		}


		// Get model name/label/desc fields
		if ($is_model)
		{
			$name = $Object->get_name();
			$json['name'] = $Object::$__scfg['name'];
			$json['label'] = $Object::$__scfg['label'];
			$json['description'] = $Object::$__scfg['description'];            
		}
		// Get name from field
		else if ($is_field)
		{
			$name = $Object->name;

		}   
		// Get trait name/label/desc fields
		else if ($is_trait)
		{

			foreach ($Class->getProperties() as $property) 
			{
				// Skip static and inherited properties
				if ($property->isStatic() && preg_match("/^__([^_]+)__scfg/",$property->name,$matches))
				{
					// Extract the name from the scfg property
					$name = $matches[1];

					// Setup the scfg static property name to be accessed
					$prop = "__{$name}__scfg";

					// Get the value of the static property
					$trait_scfg = $Object::$$prop;

					// Extract the field values
					$json['name'] = $trait_scfg['traits__'.$name.'__name'];
					$json['label'] = $trait_scfg['traits__'.$name.'__label'];
					$json['description'] = $trait_scfg['traits__'.$name.'__description'];  
					break ;
				}
			}
		}

		// Set extends, if not set
		if (!isset($json['extends']) && !$is_trait)
		{
			$extends = get_parent_class($class_name);

			// Extends is null if it is supposed to point to core
			if (strtolower($extends) === 'supermodlr' || strtolower($extends) === 'field')
			{
				//$json['extends'] = NULL;
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

		$field_keys = NULL;

		// Get fields
		if ($is_model && isset($Object::$__scfg[$name.'.field_keys']))
		{
			// Get all field keys
			$field_keys = $Object::$__scfg[$name.'.field_keys'];
		}
		else if ($is_model && isset($Object::$__scfg['field_keys']))
		{
			// Get all field keys
			$field_keys = $Object::$__scfg['field_keys'];
		} 
		else if ($is_trait && isset($trait_scfg) && $trait_scfg['field_keys'])
		{
			$field_keys = $trait_scfg['field_keys'];
		}

		// Add fields if present
		if ($field_keys !== NULL)
		{
			// Get the object fields if this is a model
			if ($is_model) $object_fields = $Object->get_fields();

			// Loop through all set field keys
			foreach ($field_keys as $key)
			{
				// Skip pk which is auto-set and not stored in fields list
				if ($is_model && strtolower($key) == strtolower($Object->cfg('pk_name'))) continue;

				// If this field is not assigned directly to this model (trait or extended), skip it
				if ($is_model)
				{
					if (!isset($object_fields[$key])) continue;
					$name_array = explode('_', get_class($object_fields[$key]));
					if ($name_array[1] != Supermodlr::get_name_case($name)) continue;
				}

				// Construct proper field rel value and add it to the json object
				$json['fields'][] = array('model'=> 'field', '_id'=> 'Field_'.Supermodlr::get_name_case($name).'_'.Supermodlr::get_name_case($key));
			}
		}


		// Look for traits
		if ($is_model || $is_trait)
		{
			// Only returns traits on the sent class, not inherited from parents or others included
			$traits = class_uses($class_name);

			foreach ($traits as $trait)
			{
				$json['traits'][] = array('model'=> 'trait', '_id'=> $trait);
			}

			if (empty($traits) && isset($json['traits']))
			{
				unset($json['traits']);
			}
		}

		// Loop through all methods
		foreach ($Class->getMethods() as $method) 
		{

			// If this method is not inherited from a parent class
			if (preg_match('/(.*)Method \[ <user>/s',$method->__toString(),$matches)) 
			{
				// Get comments, if any
				if (isset($matches[1]))
				{
					$comment = $matches[1];
				}
				else
				{
					$comment = NULL;
				}

				// If this is an event method
				if (preg_match('/^event__/i',$method->name))
				{
					$parts = explode('__',$method->name);
					$event_name = array_pop($parts);
				}
				else
				{
					$event_name = NULL;
				}

				// Get method contents from php file
				preg_match("/\@\@ .+[.]php ([0-9]+) - ([0-9]+)/i", $method->__toString(),$matches);
				list($line,$start,$end) = $matches;
				$source = array_slice($Class_Source, $start-1, ($end-$start)+1);

				$json['methods'][] = array(
					'name'    => $method->name,
					'comment' => $comment,
					'source'  => implode('',$source),
					'event'   => $event_name,
				);
			}
		}

		return $json;
	}    
}

function mb_str_split( $string ) { 
	// Split at all position not after the start: ^ 
	// and not before the end: $ 
	return preg_split('/(?<!^)(?!$)/u', $string ); 
} 

function mb_ord($string)
{
	if (extension_loaded('mbstring') === TRUE)
	{
		mb_language('Neutral');
		mb_internal_encoding('UTF-8');
		mb_detect_order(array('UTF-8', 'ISO-8859-15', 'ISO-8859-1', 'ASCII'));

		$result = unpack('N', mb_convert_encoding($string, 'UCS-4BE', 'UTF-8'));

		if (is_array($result) === TRUE)
		{
			return $result[1];
		}
	}

	return ord($string);
}
