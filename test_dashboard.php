<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('email', 'peter@gmail.com')->first();
$token = Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
$req = Illuminate\Http\Request::create('/api/dashboard', 'GET');
$req->headers->set('Authorization', 'Bearer ' . $token);
$res = app()->handle($req);
echo $res->getContent();
