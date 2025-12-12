<?php
// login.php
session_start(); // Inicia a sessão para guardarmos quem está logado
require 'includes/db.php';

$message = '';

// Verifica se já vieste redirecionado do registo com sucesso
if (isset($_GET['status']) && $_GET['status'] === 'registered') {
    $message = '✅ Conta criada! Agora faz login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $message = '❌ Preenche tudo, mano.';
    } else {
        // Vamos buscar o user pelo email
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // O Big Bro explica: password_verify compara o texto normal com o HASH na BD
        if ($user && password_verify($password, $user['password_hash'])) {
            // SUCESSO! Guardar dados na sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['username'];
            
            // Redireciona para a Dashboard
            header('Location: index.php');
            exit;
        } else {
            $message = '❌ Email ou password errados. Tenta outra vez.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - K-Dream Budgeter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-900 text-white h-screen flex items-center justify-center p-4">

    <div class="bg-gray-800 p-8 rounded-xl shadow-2xl w-full max-w-md border border-gray-700">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-blue-500 mb-2">🔑 Entrar</h1>
            <p class="text-gray-400">Bem-vindo de volta ao K-Dream.</p>
        </div>

        <?php if($message): ?>
            <div class="p-3 rounded mb-4 text-sm text-center border <?= strpos($message, '✅') !== false ? 'bg-green-500/20 text-green-300 border-green-500/50' : 'bg-red-500/20 text-red-300 border-red-500/50' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
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

            <button type="submit" 
                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded transition duration-200 transform hover:scale-[1.02]">
                Entrar na Dashboard
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-gray-400">
            Ainda não tens conta? <a href="register.php" class="text-blue-400 hover:underline">Regista-te</a>.
        </p>
    </div>

</body>
</html>