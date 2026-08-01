<?php
$pdo = new PDO('sqlite:database/database.sqlite');
$stmt = $pdo->query('PRAGMA table_info(users)');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
