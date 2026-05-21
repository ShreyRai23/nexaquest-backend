<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$users = App\Models\User::whereIn('email', ['ben@gmail.com', 'peter@gmail.com'])->get();
foreach($users as $user) {
    $user->password = 'password123';
    $user->save();
}
echo "Passwords reset successfully.";
