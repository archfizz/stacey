<?php

error_reporting(E_ALL);
ini_set('xdebug.scream', 1);

# let people know if they are running an unsupported version of PHP
if ($phpVersion = phpversion() < 5.3) {

    $message = <<<HTML
<h3>Stacey requires PHP 5.3 or higher.<br>You are currently running PHP $phpVersion.</h3>
<p>You should contact your host to see if they can upgrade your version of PHP.</p>'
HTML;

    exit($message);

} else {

    $loader = require __DIR__.'/vendor/autoload.php';

    require_once __DIR__.'/app/parsers/markdown-parser.inc.php';

    $request = Symfony\Component\HttpFoundation\Request::createFromGlobals();

    // start the app
    new Stacey\Application($request->query->all());
}
