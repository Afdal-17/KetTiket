<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Http\Request;

$controller = new AuthController();

function testRegularLogin() {
    global $controller;
    echo "Test 1: Regular login (admin)\n";
    $request = Request::create('/login', 'POST', [
        'email' => 'admin@kettiket.com',
        'password' => 'password123'
    ]);

    $response = $controller->login($request);
    echo "Status: " . $response->getStatusCode() . "\n";

    if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getContent(), true);
        echo "Login successful: " . $data['message'] . "\n";
        echo "Role: " . (isset($data['role']) ? $data['role'] : 'NOT FOUND') . "\n";
        return true;
    } else {
        echo "Login failed: " . $response->getContent() . "\n";
        return false;
    }
}

function testSocialLogin($provider, $email, $name, $role) {
    global $controller;
    echo "Test Social login (" . $provider . ", " . $role . ")\n";
    $request = Request::create('/login/social', 'POST', [
        'provider' => $provider,
        'email' => $email,
        'name' => $name,
        'role' => $role
    ]);

    $response = $controller->socialLogin($request);
    echo "Status: " . $response->getStatusCode() . "\n";

    if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getContent(), true);
        echo "Login successful: " . $data['message'] . "\n";
        echo "Role: " . (isset($data['role']) ? $data['role'] : 'NOT FOUND') . "\n";
        return true;
    } else {
        echo "Login failed: " . $response->getContent() . "\n";
        return false;
    }
}

function checkSocialUsersCreated() {
    $found_users = 0;
    $users = User::all();

    foreach ($users as $user) {
        if (strpos($user->email, 'google_user@example.com') !== false || 
            strpos($user->email, 'tiktok_user@example.com') !== false || 
            strpos($user->email, 'phone_user@example.com') !== false || 
            strpos($user->email, 'organizer_social@example.com') !== false) {
            $found_users++;
        }
    }

    if ($found_users >= 4) {
        echo "\n✓ Social users created successfully (found " . $found_users . " social users)\n";
        return true;
    } else {
        echo "\n✗ FAILED to create social users (only found " . $found_users . " social users)\n";
        return false;
    }
}

function logout() {
    global $controller;
    $controller->logout(Request::create('/logout', 'POST'));
}

$success = true;
$success = testRegularLogin() && $success;
$success = testSocialLogin('google', 'google_user@example.com', 'Google User', 'customer') && $success;
$success = testSocialLogin('tiktok', 'tiktok_user@example.com', 'TikTok User', 'customer') && $success;
$success = testSocialLogin('phone', 'phone_user@example.com', 'Phone User', 'customer') && $success;
$success = testSocialLogin('google', 'organizer_social@example.com', 'Social Organizer', 'organizer') && $success;
$success = checkSocialUsersCreated() && $success;
logout();

if ($success) {
    echo "\n✓ All tests passed!\n";
} else {
    echo "\n✗ Some tests failed!\n";
}
?>
