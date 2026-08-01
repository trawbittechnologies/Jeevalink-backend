<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$users = \App\Models\User::all(['id', 'primary_name', 'email', 'role', 'district', 'city', 'address', 'remarks', 'organization_name']);
echo "TOTAL USERS: " . count($users) . "\n";
foreach ($users as $u) {
    echo "ID: {$u->id} | Name: {$u->primary_name} | Role: {$u->role} | District: '{$u->district}' | City: '{$u->city}' | Address: '{$u->address}' | Remarks: '{$u->remarks}'\n";
}
