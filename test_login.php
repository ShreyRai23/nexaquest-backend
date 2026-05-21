<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$req = Illuminate\Http\Request::create('/api/auth/login', 'POST', ['email' => 'peter@gmail.com', 'password' => 'password123']);
$res = app()->handle($req);
echo $res->getContent();
