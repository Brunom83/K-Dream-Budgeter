<?php
// db.php
// Configurações da Base de Dados
$host = 'localhost';
$db   = '';
$user = 'root'; // No XAMPP/WAMP costuma ser root
$pass = '';     // No XAMPP costuma ser vazio, no MAMP é 'root'
$charset = 'utf8mb4';

// Data Source Name (A string de conexão)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Opções do PDO (Para ele nos avisar se der erro)
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança erros se falhar
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devolve arrays associativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Segurança real
];

try {
    // Tenta ligar...
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Se não aparecer erro nenhum, estamos ligados! 
    // (Não precisas de imprimir nada aqui para não sujar o site)
} catch (\PDOException $e) {
    // Se der raia, mostra o erro (Em produção nunca mostres detalhes técnicos!)
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>