<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$blockAdmins = User::where('role', 'block_admin')->get();
echo "Block Admins:\n";
foreach ($blockAdmins as $b) {
    echo "  - ID: {$b->id} | Name: {$b->primary_name} | City (Block): {$b->city} | District: {$b->district}\n";
}

$volunteers = User::whereIn('role', ['volunteer', 'unit_squad'])->get();
echo "\nVolunteers & Unit Squads:\n";
foreach ($volunteers as $v) {
    echo "  - ID: {$v->id} | Role: {$v->role} | Name: {$v->primary_name} | City (Meghala): {$v->city} | District: {$v->district}\n";
}
