<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = \Illuminate\Support\Facades\DB::table('users')
    ->select('id', 'email', 'mobile', 'role', 'status', 'full_name')
    ->orderBy('id')
    ->get();

echo "Total users: " . count($users) . "\n\n";
foreach ($users as $u) {
    echo "ID: {$u->id} | Role: {$u->role} | Email: {$u->email} | Mobile: {$u->mobile} | Status: {$u->status} | Name: {$u->full_name}\n";
}
