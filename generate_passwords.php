<?php
$passwords = [
    'SuperSecure2025!',
    'MedicalSecure2025!',
    'DentalSecure2025!'
];
foreach ($passwords as $password) {
    echo $password . ': ' . password_hash($password, PASSWORD_DEFAULT) . "\n";
}
?>