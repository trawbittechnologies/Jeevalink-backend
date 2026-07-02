<?php
// Direct HTTP test of the login endpoint
$url = 'http://localhost:8000/api/v1/auth/login';
$data = json_encode(['credential' => 'admin@jeevalink.org', 'password' => 'Admin@2026']);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Content-Length: ' . strlen($data)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
if ($error) echo "cURL Error: $error\n";
echo "Response: " . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n";
