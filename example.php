<?php

require 'vendor/autoload.php';


$livr = [ 
    'name' => [ [ 'max_length' => 10 ] ]
];

$validator = new Validator\LIVR($livr);
$validator->prepare();

$rules = $validator->validate( ["name" => "1234567890"] );

var_dump($rules);