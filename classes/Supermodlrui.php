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

            // get existing file contents
            $file_contents_hash = md5($file_contents);

            // get db contents (remove all newlines to ignore system differences)
            $db_contents = preg_replace('/\r|\n/','',$object->generate_class_file_contents());

            // generate file contents from db version of object
            $db_contents_hash = md5($db_contents);

            // if file does not match entry in the db
            if ($file_contents_hash !== $db_contents_hash)
            {

                // get the new object to save to the db
                $new_db_contents = self::class_to_db_object($object_class, $class_path);

                // loop through each prop
                foreach ($new_db_contents as $prop => $val) 
                {
                    // set the new value on the object
                    $object->set($prop,$val);
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

        $Class = new ReflectionClass($class_name);
        if ($is_trait)
        {
            $temp_class_name = "Class_".$class_name;
            eval("class ".$temp_class_name." { use ".$class_name.";}");
            $Object = new $temp_class_name();
        }
        else
        {
            $Object = new $class_name();
        }
        

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
     

        //get name, if not set
        if (!isset($json['name']) || !is_string($json['name']) || empty($json['name']))
        {
            if ($is_model)
            {
                $json['name'] = Model_Model::get_name_from_class($class_name);
            }
            else if ($is_field)
            {
                $json['name'] = Model_Field::get_name_from_class($class_name);
            }   
            else if ($is_trait)
            {
                $json['name'] = Model_Trait::get_name_from_class($class_name);
            }               
        }

        //set extends, if not set
        if (!isset($json['extends']))
        {       
            $extends = get_parent_class($class_name);

            //extends is null if it is supposed to point to core
            if (strtolower($extends) === 'supermodlr' || strtolower($extends) === 'field')
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
            preg_match('/FileDescription:\s(.*)\r\n?/',implode("",$Class_Source),$matches);

            //if a description was found
            if (isset($matches[1]))
            {
                $json['description'] = trim($matches[1]);
            }
            else
            {
                $json['description'] = '';
            }
        }

        $field_keys = NULL;
        //get fields
        if ($is_model && isset($Object::$__scfg[$json['name'].'.field_keys']))
        {
            //get all field keys
            $field_keys = $Object::$__scfg[$json['name'].'.field_keys'];
        }
        else if ($is_model && isset($Object::$__scfg['field_keys']))
        {
            //get all field keys
            $field_keys = $Object::$__scfg['field_keys'];
        } 

        if ($field_keys !== NULL)
        {
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

