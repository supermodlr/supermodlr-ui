<?php
$driver_config = array(
    'primary'=> array(
            'name'     => 'coremongo',
            'driver'   => 'Supermodlr_Mongodb',
            'host'     => '127.0.0.1',
            'port'     => '27017',
            'user'     => '',
            'pass'     => '',
            'dbname'   => 'supermodlr',
            'replset'  => FALSE,
            'safe'     => TRUE,
            'fsync'    => FALSE,
    )
);
return array(
    'drivers_config' => $driver_config,
    'field.drivers_config' => $driver_config,
    'model.drivers_config' => $driver_config,   
);