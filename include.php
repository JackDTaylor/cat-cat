<?php
error_reporting(E_ALL ^ E_DEPRECATED ^ E_STRICT);
define('APPROOT', __DIR__);

$loader = require 'vendor/autoload.php';
$loader->add('CatCat', APPROOT . '/app/');
$loader->add('CatFetcher', APPROOT . '/app/');
$loader->add('CatFrontend', APPROOT . '/app/');
$loader->add('DiDom', APPROOT . '/vendor/imangazaliev/didom/src'); // Composer autoload not working for some reason