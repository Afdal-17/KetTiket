<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$users = App\Models\User::all(['email','name','role','status'])->toArray();
print_r($users);
?>
