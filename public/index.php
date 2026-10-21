<?php

declare(strict_types=1);

/* Front controller: the only PHP entry point under the web root. */
require dirname(__DIR__) . '/config/bootstrap.php';

use Lubie\Http\Request;
use Lubie\Support\Env;

$base = rtrim((string) Env::get('APP_BASE_PATH', ''), '/');
$container = lubie_container();
lubie_kernel($container)->handle(Request::fromGlobals($base))->send();
