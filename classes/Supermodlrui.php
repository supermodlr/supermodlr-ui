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
        $class_name = get_class($params['this']);
        if (in_array($class_name, array('Model_Model','Model_Field','Model_Trait')))
        {
            $object = $params['this'];
            $object_class = $object->_id;

            // get reflection instance of this class
            $rClass = new ReflectionClass($object_class);
            
            // get path to file
            $class_path = $rClass->getFileName();        

            // get file contents (remove all newlines to ignore system differences)
            $file_contents = preg_replace('/\r|\n/','',file_get_contents($class_path));

            $file_contents = preg_replace('/\s+/',' ',$file_contents);
            $file_contents = preg_replace('/\t+/',' ',$file_contents);

            $file_contents = trim(iconv(mb_detect_encoding($file_contents, mb_detect_order(), true), "UTF-8", $file_contents));

            // get existing file contents
            $file_contents_hash = md5($file_contents);

            // get db contents (remove all newlines to ignore system differences)
            $db_contents = preg_replace('/\r|\n/','',$object->generate_class_file_contents());

            $db_contents = preg_replace('/\s+/',' ',$db_contents);
            $db_contents = preg_replace('/\t+/',' ',$db_contents);

            $db_contents = trim(iconv(mb_detect_encoding($db_contents, mb_detect_order(), true), "UTF-8", $db_contents));

            // generate file contents from db version of object
            $db_contents_hash = md5($db_contents);

            // if file does not match entry in the db
            if ($file_contents_hash !== $db_contents_hash)
            {

                fbl('rewriting class: '.$class_name);

                // get the new object to save to the db
                $new_db_contents = self::class_to_db_object($object_class, $class_path);

                // loop through each prop
                foreach ($new_db_contents as $prop => $val) 
                {
                    try {
                        // set the new value on the object
                        $object->set($prop,$val);
                    }
                    catch (Exception $E)
                    {
                        unset($object->$prop);
                    }
                }

                $object->cfg('create_file',FALSE);

                // Re save this object
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
            //read the file
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

        //set class name as _id
        $json['_id'] = $class_name;

        //set all non static properties
        foreach ($Class->getProperties() as $property) 
        {
            //skip static and inherited properties
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
     

        //get model name/label/desc fields
        if ($is_model)
        {
            $name = $Object->get_name();
            $json['name'] = $Object::$__scfg['name'];
            $json['label'] = $Object::$__scfg['label'];
            $json['description'] = $Object::$__scfg['description'];            
        }
        //get name from field
        else if ($is_field)
        {
            $name = $Object->name;

        }   
        //get trait name/label/desc fields
        else if ($is_trait)
        {

          foreach ($Class->getProperties() as $property) 
          {
                //skip static and inherited properties
                if ($property->isStatic() && preg_match("/^__([^_]+)__scfg/",$property->name,$matches))
                {
                    // extract the name from the scfg property
                    $name = $matches[1];

                    //setup the scfg static property name to be accessed
                    $prop = "__{$name}__scfg";
                    //get the value of the static property
                    $trait_scfg = $Object::$$prop;
                    //extract the field values
                    $json['name'] = $trait_scfg['traits__'.$name.'__name'];
                    $json['label'] = $trait_scfg['traits__'.$name.'__label'];
                    $json['description'] = $trait_scfg['traits__'.$name.'__description'];  
                    break ;
                }
            }
            
        }               

        //set extends, if not set
        if (!isset($json['extends']) && !$is_trait)
        {       
            $extends = get_parent_class($class_name);

            //extends is null if it is supposed to point to core
            if (strtolower($extends) === 'supermodlr' || strtolower($extends) === 'field')
            {
                // $json['extends'] = NULL;
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

        //get fields
        if ($is_model && isset($Object::$__scfg[$name.'.field_keys']))
        {
            //get all field keys
            $field_keys = $Object::$__scfg[$name.'.field_keys'];
        }
        else if ($is_model && isset($Object::$__scfg['field_keys']))
        {
            //get all field keys
            $field_keys = $Object::$__scfg['field_keys'];
        } 
        else if ($is_trait && isset($trait_scfg) && $trait_scfg['field_keys'])
        {
            $field_keys = $trait_scfg['field_keys'];
        }


        if ($field_keys !== NULL)
        {
            //loop through all set field keys
            foreach ($field_keys as $key)
            {
                //skip pk which is auto-set and not stored in fields list
                if ($is_model && strtolower($key) == strtolower($Object->cfg('pk_name'))) continue;

                //construct proper field rel value and add it to the json object
                $json['fields'][] = array('model'=> 'field', '_id'=> 'Field_'.Supermodlr::get_name_case($name).'_'.Supermodlr::get_name_case($key));
            }

        }

        // look for traits
        if ($is_model || $is_trait)
        {
            $traits = class_uses($class_name);  // only returns traits on the sent class, not inherited from parents or others included

            foreach ($traits as $trait)
            {
                $json['traits'][] = array('model'=> 'trait', '_id'=> $trait);
            }

            if (empty($traits) && isset($json['traits']))
            {
                unset($json['traits']);
            }
        }

        //loop through all methods
        foreach ($Class->getMethods() as $method) 
        {

            //if this method is not inherited from a parent class
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
                preg_match("/\@\@ .+[.]php ([0-9]+) - ([0-9]+)/i", $method->__toString(),$matches);
                list($line,$start,$end) = $matches;
                $source = array_slice($Class_Source, $start-1, ($end-$start)+1);
                
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

function mb_str_split( $string ) { 
    # Split at all position not after the start: ^ 
    # and not before the end: $ 
    return preg_split('/(?<!^)(?!$)/u', $string ); 
} 

function mb_ord($string)
{
    if (extension_loaded('mbstring') === true)
    {
        mb_language('Neutral');
        mb_internal_encoding('UTF-8');
        mb_detect_order(array('UTF-8', 'ISO-8859-15', 'ISO-8859-1', 'ASCII'));

        $result = unpack('N', mb_convert_encoding($string, 'UCS-4BE', 'UTF-8'));

        if (is_array($result) === true)
        {
            return $result[1];
        }
    }

    return ord($string);
}
