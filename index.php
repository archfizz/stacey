<?php

$loader = require __DIR__.'/vendor/autoload.php';

require_once __DIR__.'/app/parsers/markdown-parser.inc.php';

$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();

// start the app
new Stacey\Application($request->query->all());
