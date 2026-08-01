<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$id = 6;
$blockAdmin = \App\Models\User::where('role', 'block_admin')->find($id);

if (!$blockAdmin) {
    echo "Block Admin not found.\n";
    exit;
}

echo "Before update:\n";
echo "name: " . $blockAdmin->name . "\n";
echo "full_name: " . $blockAdmin->full_name . "\n";

$blockAdmin->full_name = "Rahul V Updated";
$blockAdmin->name = "Rahul V Updated";

echo "\nAfter setting properties, before save:\n";
echo "name: " . $blockAdmin->name . "\n";
echo "full_name: " . $blockAdmin->full_name . "\n";

$result = $blockAdmin->save();
echo "Save result: " . ($result ? "true" : "false") . "\n";

$blockAdmin->refresh();

echo "\nAfter refresh from DB:\n";
echo "name: " . $blockAdmin->name . "\n";
echo "full_name: " . $blockAdmin->full_name . "\n";
