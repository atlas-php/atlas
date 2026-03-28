<?php

declare(strict_types=1);

use Illuminate\Http\Request;

$app = require __DIR__.'/../bootstrap.php';

$app->handleRequest(Request::capture());
