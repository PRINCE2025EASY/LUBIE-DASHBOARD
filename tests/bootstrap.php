<?php

declare(strict_types=1);

// Test bootstrap: same autoload and .env as the app. Integration tests use DB_TEST_NAME only.
require dirname(__DIR__) . '/config/bootstrap.php';
