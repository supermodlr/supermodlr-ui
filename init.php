<?php

if (Kohana::$environment !== Kohana::PRODUCTION)
{
    // Register import controller
    Route::set('supermodlrui/supermodlrcore/import','supermodlrui/supermodlrcore/import')
        ->defaults(array(
            'controller' => 'Supermodlrui',
            'action'     => 'import',
        ));

    // Register UI controller
    Route::set('supermodlrui', 'supermodlrui/<model>(/<action>(/<id>))', array('model' => '[a-zA-Z0-9_]+', 'action' => '[a-zA-Z0-9_]+', 'id' => '[a-zA-Z0-9_]+'))
        ->defaults(array(
            'controller' => 'Supermodlrui',
            'action'     => 'index',
        ));

    // Register Model "onload" event to sync with the generated class file
    Event::register('Supermodlr.loaded',array('Supermodlrui','model_loaded'));
}
