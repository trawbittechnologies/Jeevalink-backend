<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->instance('middleware.disable', true);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::create(
        '/api/v1/super-admin/block-admins/6',
        'PUT',
        [
            'full_name' => 'Rahul V',
            'email' => 'test@gmail.com',
            'mobile' => '9889876566',
            'district' => 'Kasaragod',
            'city' => 'test'
        ]
    )
);

echo $response->getContent();
