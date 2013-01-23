<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Supermodlrui extends Controller_Page {

    public function before()
    {
        if (Kohana::$environment === Kohana::PRODUCTION)
        {
            //404 if ui is accessed on production.
            throw new HTTP_Exception_404('The requested URL :uri was not found on this server.',
                                                    array(':uri' => $this->request->uri()));
        }

        parent::before();
        $this->init_req_model();
    }

    public function action_index()
    {
        //if no model param
        if ($this->model_name === NULL)
        {
            //list all models that can be modified and link to their pages
            $this->template = 'list';
            
            $models = Supermodlr::get_models();
            $this->bind('models',$models);
        }
        
        //if model was sent in url
        else
        {
            $this->template = 'view';
            
            //provide link to create new entry
            $this->bind('model_name',$this->model_name);

            //show create form
            $model_class = $this->model_class;
            $model = new $model_class();

            //bind the data model
            $this->bind('model',$model);

            //bind all the fields
            $fields = $model->get_fields();
            $this->bind('fields',$fields);

            //read list of 10 entries with links to read, update, and delete
            $model_rows = $model_class::query(array(
                'limit'=> 20,
                'array'=> TRUE,
            ));
            $this->bind('model_rows',$model_rows);  
        }
    }
    
    public function action_create()
    {
        //bind model name
        $this->bind('model_name',$this->model_name);
        
        //show create form
        $model_class = $this->model_class;
        $model = new $model_class();

        //bind the data model
        $this->bind('model',$model);

        //get form      
        $View = $this->model_view($model,'input','create');
        $form = $View->render();
        $this->bind('form',$form);  
        $form_id = $View->form_id;
        $this->bind('form_id',$form_id);    
            


    }
    
    public function action_read()
    {
        //bind model name
        $this->bind('model_name',$this->model_name);
        
        //load model by id
        $id = $this->request->param('id');
        if ($id === NULL)
        {
            //404 if model doesn't exist
            throw new HTTP_Exception_404('The requested URL :uri was not found on this server.',
                                                    array(':uri' => $this->request->uri()));
        }
        
        $model_class = $this->model_class;
        $model = new $model_class($id);
        if ($model->loaded() === FALSE)
        {
            //404 if model doesn't exist
            throw new HTTP_Exception_404('The requested URL :uri was not found on this server.',
                                                    array(':uri' => $this->request->uri()));
        }

        //show object data
        $View = $this->model_view($model,'display','read');
        $display = $View->render();
        $this->bind('display',$display);    
        $form_id = $View->form_id;
        $this->bind('form_id',$form_id);            

        //bind the data model
        $this->bind('model',$model);    

    
    }   
    
    public function action_update()
    {
        //bind model name
        $this->bind('model_name',$this->model_name);        
        
        //load model by id
        $id = $this->request->param('id');
        if ($id === NULL)
        {
            //404 if model doesn't exist
            throw new HTTP_Exception_404('The requested URL :uri was not found on this server.',
                                                    array(':uri' => $this->request->uri()));
        }
        
        $model_class = $this->model_class;
        $model = new $model_class($id);

        if ($model->loaded() === FALSE)
        {
            //404 if model doesn't exist
            throw new HTTP_Exception_404('The requested URL :uri was not found on this server.',
                                                    array(':uri' => $this->request->uri()));
        }

        //show update form
        $View = $this->model_view($model,'input','update');
        $form = $View->render();
        $this->bind('form',$form);  
        $form_id = $View->form_id;
        $this->bind('form_id',$form_id);

        //bind the data model
        $this->bind('model',$model);

    
    }   
    
    public function action_delete()
    {
        //bind model name
        $this->bind('model_name',$this->model_name);
        
        //load model by id
        $id = $this->request->param('id');
        if ($id === NULL)
        {
            //404 if model doesn't exist
            throw new HTTP_Exception_404('The requested URL :uri was not found on this server.',
                                                    array(':uri' => $this->request->uri()));
        }
        
        $model_class = $this->model_class;
        $model = new $model_class($id);
        if ($model->loaded() === FALSE)
        {
            //404 if model doesn't exist
            throw new HTTP_Exception_404('The requested URL :uri was not found on this server.',
                                                    array(':uri' => $this->request->uri()));
        }

        //show delete form
        $View = $this->model_view($model,'input', 'delete', 'default','delete');
        $form = $View->render();
        $this->bind('form',$form);  
        $form_id = $View->form_id;
        $this->bind('form_id',$form_id);
        //bind the data model
        $this->bind('model',$model);        


    }           

    public function action_import()
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

        //skip core model and field models
        $loaded['Model_Model'] = TRUE;
        $loaded['Model_Field'] = TRUE;

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
            preg_match('/FileDescription:\s(.*)/',implode("",$Class_Source),$matches);

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

} // End Welcome
