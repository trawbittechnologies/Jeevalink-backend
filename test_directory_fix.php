<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$district = 'Kasaragod';
$meghala  = 'cheemeni';

echo "=== Scenario: district=Kasaragod, meghala=cheemeni (exact match only) ===\n";
$users = User::whereIn('role', ['block_admin', 'volunteer', 'unit_squad'])
    ->where('status', 'Active')
    ->where('district', $district)
    ->whereRaw('LOWER(city) = ?', [strtolower($meghala)])
    ->select(['id', 'primary_name', 'role', 'city', 'mobile'])
    ->get();
echo "Count: " . $users->count() . "\n";
foreach ($users as $u) {
    echo "  - {$u->primary_name} | role={$u->role} | city={$u->city}\n";
}

echo "\n=== Scenario: district=Kasaragod, NO meghala (All Meghala Units) ===\n";
$all = User::whereIn('role', ['block_admin', 'volunteer', 'unit_squad'])
    ->where('status', 'Active')
    ->where('district', $district)
    ->select(['id', 'primary_name', 'role', 'city', 'mobile'])
    ->get();
echo "Count: " . $all->count() . "\n";
foreach ($all as $u) {
    echo "  - {$u->primary_name} | role={$u->role} | city={$u->city}\n";
}

echo "\n=== Scenario: district=Kasaragod, meghala=cheemeni east (exact match) ===\n";
$east = User::whereIn('role', ['block_admin', 'volunteer', 'unit_squad'])
    ->where('status', 'Active')
    ->where('district', $district)
    ->whereRaw('LOWER(city) = ?', ['cheemeni east'])
    ->select(['id', 'primary_name', 'role', 'city', 'mobile'])
    ->get();
echo "Count: " . $east->count() . "\n";
foreach ($east as $u) {
    echo "  - {$u->primary_name} | role={$u->role} | city={$u->city}\n";
}
echo "\n";
