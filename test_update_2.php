<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::create(
        '/api/v1/test-update/6',
        'PUT',
        [
            'full_name' => 'Rahul API PUT Test',
            'email' => 'test@gmail.com',
            'mobile' => '9889876566',
            'district' => 'Kasaragod',
            'city' => 'test'
        ]
    )
);

echo $response->getContent();
