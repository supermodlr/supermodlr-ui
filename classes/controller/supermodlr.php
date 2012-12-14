<?php defined('SYSPATH') or die('No direct script access.');

class Controller_supermodlr extends Controller_Page {

	public function before()
	{
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
			
			$models = supermodlr_core::get_models();
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

} // End Welcome
