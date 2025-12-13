<?php
// register.php
session_start();
require 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($email) || empty($password)) {
        $msg = "⚠️ Preenche tudo, mano!";
    } elseif ($password !== $confirm_password) {
        $msg = "❌ As passwords não batem certo.";
    } elseif (strlen($password) < 6) {
        $msg = "❌ Password muito fraca (min 6 chars).";
    } else {
        // Verificar duplicados
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->rowCount() > 0) {
            $msg = "❌ Esse Username ou Email já existe.";
        } else {
            // Criar conta
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            if ($stmt->execute([$username, $email, $hash])) {
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_name'] = $username;
                header('Location: index.php');
                exit;
            } else {
                $msg = "❌ Erro ao registar.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Registar - K-Dream</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#f8fafc">
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
        <div class="absolute top-[-10%] right-[-10%] w-96 h-96 bg-emerald-200 rounded-full blur-[100px] opacity-60"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-96 h-96 bg-blue-200 rounded-full blur-[100px] opacity-60"></div>
    </div>

    <div class="bg-white/80 backdrop-blur-md p-8 rounded-2xl border border-white/50 shadow-[0_8px_30px_rgb(0,0,0,0.04)] w-full max-w-md relative z-10">
        
        <div class="text-center mb-6">
            <h1 class="font-extrabold text-3xl tracking-tight text-slate-800 mb-1">
                Junta-te à Crew 🚀
            </h1>
            <p class="text-slate-500 text-sm font-medium">Começa a planear a tua viagem.</p>
        </div>

        <?php if($msg): ?>
            <div class="bg-red-50 border border-red-100 text-red-500 text-sm p-3 rounded-lg mb-6 text-center font-medium">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Username</label>
                <input type="text" name="username" required placeholder="" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-700 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Email</label>
                <input type="email" name="email" required placeholder="tu@exemplo.com" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-700 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition font-medium">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Password</label>
                    <input type="password" name="password" required placeholder="******" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-700 focus:border-emerald-400 outline-none transition font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Confirmar</label>
                    <input type="password" name="confirm_password" required placeholder="******" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-700 focus:border-emerald-400 outline-none transition font-medium">
                </div>
            </div>

            <button type="submit" class="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-slate-300/50 transform transition hover:scale-[1.01] active:scale-95 mt-2">
                Criar Conta
            </button>
        </form>

        <div class="mt-6 text-center border-t border-slate-100 pt-4">
            <p class="text-slate-400 text-sm">Já tens conta?</p>
            <a href="login.php" class="text-emerald-500 font-bold hover:text-emerald-600 transition">Entra aqui</a>
        </div>
    </div>
</body>
</html>
