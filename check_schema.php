<?php
$db = new PDO('sqlite:database/database.sqlite');
$stmt = $db->query('PRAGMA table_info(users)');
foreach($stmt as $col) {
    echo $col['name'] . ' | notnull=' . $col['notnull'] . ' | dflt=' . $col['dflt_value'] . PHP_EOL;
}

echo PHP_EOL . "--- Last 3 block_admin rows ---" . PHP_EOL;
$stmt2 = $db->query("SELECT id, name, full_name, email, city FROM users WHERE role='block_admin' ORDER BY id DESC LIMIT 3");
foreach($stmt2 as $row) {
    echo "id={$row['id']} | name={$row['name']} | full_name={$row['full_name']} | email={$row['email']} | city={$row['city']}" . PHP_EOL;
}
