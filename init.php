<?php
Route::set('supermodlrui/supermodlrcore/import','supermodlrui/supermodlrcore/import')
	->defaults(array(
		'controller' => 'Supermodlrui',
		'action'     => 'import',
	));

Route::set('supermodlrui', 'supermodlrui/<model>(/<action>(/<id>))', array('model' => '[a-zA-Z0-9_]+', 'action' => '[a-zA-Z0-9_]+', 'id' => '[a-zA-Z0-9_]+'))
	->defaults(array(
		'controller' => 'Supermodlrui',
		'action'     => 'index',
	));

