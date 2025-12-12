<?php
// register.php
session_start();
require 'includes/db.php'; // Vai buscar a ligação à base de dados

$message = '';

// Se o formulário foi submetido (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validações básicas (Big Bro a proteger-te de erros parvos)
    if (empty($username) || empty($email) || empty($password)) {
        $message = '❌ Preenche todos os campos, mano!';
    } elseif ($password !== $confirm_password) {
        $message = '❌ As passwords não coincidem.';
    } else {
        // Verificar se o email ou user já existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        
        if ($stmt->rowCount() > 0) {
            $message = '❌ Esse utilizador ou email já existe.';
        } else {
            // TUDO CERTO? Vamos encriptar a password e guardar!
            // O password_hash cria um "sal" aleatório. É super seguro.
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$username, $email, $password_hash])) {
                // Sucesso! Redireciona para o login
                header('Location: login.php?status=registered');
                exit;
            } else {
                $message = '❌ Erro ao criar conta. Tenta de novo.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registo - K-Dream Budgeter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-900 text-white h-screen flex items-center justify-center p-4">

    <div class="bg-gray-800 p-8 rounded-xl shadow-2xl w-full max-w-md border border-gray-700">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-blue-500 mb-2">🚀 K-Dream</h1>
            <p class="text-gray-400">Cria a tua conta e começa a poupar para a Coreia.</p>
        </div>

        <?php if($message): ?>
            <div class="bg-red-500/20 text-red-300 p-3 rounded mb-4 text-sm text-center border border-red-500/50">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                <input type="text" name="username" required 
                    class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                <input type="email" name="email" required 
                    class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                <input type="password" name="password" required 
                    class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Confirmar Password</label>
                <input type="password" name="confirm_password" required 
                    class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
            </div>

            <button type="submit" 
                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded transition duration-200 transform hover:scale-[1.02]">
                Criar Conta
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-gray-400">
            Já tens conta? <a href="login.php" class="text-blue-400 hover:underline">Entra aqui</a>.
        </p>
    </div>

</body>
</html>