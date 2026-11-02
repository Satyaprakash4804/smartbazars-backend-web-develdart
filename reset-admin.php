<?php
// SmartBazars: create/reset the admin login. Run: php reset-admin.php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = 'admin@smartbazars.in';
$password = 'Admin@123';

$user = App\Models\User::withTrashed()->where('email', $email)->first();
if ($user && $user->trashed()) {
    $user->restore();
}
if (!$user) {
    $user = new App\Models\User(['email' => $email, 'name' => 'SmartBazars Admin', 'mobile' => '9000000001']);
}
$user->password = $password;          // hashed automatically by the User model
$user->access_panel = 'admin';
$user->status = 'active';
$user->save();

if (!$user->hasRole('Super Admin')) {
    $user->assignRole('Super Admin');
}

echo "Admin ready: $email / $password\n";
echo "All admin accounts in this database:\n";
foreach (App\Models\User::where('access_panel', 'admin')->get() as $a) {
    echo " - {$a->email}\n";
}