<?php

$loader = require __DIR__.'/vendor/autoload.php';

require_once __DIR__.'/app/parsers/markdown-parser.inc.php';

Symfony\Component\Debug\Debug::enable();
Symfony\Component\HttpFoundation\Request::enableHttpMethodParameterOverride();
$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();

// start the app
$app = new Stacey\Application('dev', true);
$response = $app->handle($request);
$response->send();
$app->terminate($request, $response);
