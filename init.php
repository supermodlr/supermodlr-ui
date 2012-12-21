<?php
Route::set('supermodlr/import','supermodlr/import')
	->defaults(array(
		'controller' => 'Supermodlr',
		'action'     => 'import',
	));

Route::set('supermodlr', 'supermodlr/<model>(/<action>(/<id>))', array('model' => '[a-zA-Z0-9_]+', 'action' => '[a-zA-Z0-9_]+', 'id' => '[a-zA-Z0-9_]+'))
	->defaults(array(
		'controller' => 'Supermodlr',
		'action'     => 'index',
	));

