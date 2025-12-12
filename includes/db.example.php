<?php
// includes/db.example.php
// Renomeia este ficheiro para db.php e mete as tuas credenciais reais
$host = 'localhost';
$db   = 'nome_da_tua_base_de_dados_aqui';
$user = 'teu_user_aqui';
$pass = 'tua_password_aqui';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>