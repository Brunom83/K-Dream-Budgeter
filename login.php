<?php
// Configurações de Sessão (Devem estar antes do session_start)
// Se o utilizador pediu "Manter Logado" num pedido anterior, o cookie já trata disso.
// Aqui garantimos apenas definições base de segurança.
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

session_start();
require 'includes/db.php';

// Se já está logado, manda para a dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input']); // Pode ser email ou username
    $password = $_POST['password'];
    $remember = isset($_POST['remember']); // Checkbox

    if (!empty($login_input) && !empty($password)) {
        // 1. Procurar por Email OU Username
        // Usamos a mesma variável $login_input para os dois parâmetros
        $stmt = $pdo->prepare("SELECT id, username, password_hash, group_id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$login_input, $login_input]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login Sucesso! ✅
            
            // Lógica do "Manter Logado"
            if ($remember) {
                // Estende a sessão por 30 dias
                $params = session_get_cookie_params();
                setcookie(session_name(), session_id(), time() + (30 * 24 * 60 * 60), $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['username'];
            
            // Log de atividade se tiver grupo
            if ($user['group_id']) {
                require_once 'includes/functions.php'; // Só para o log
                // logActivity($pdo, $user['group_id'], $user['id'], "Iniciou sessão.", "info");
                // (Comentei o log para não encher a base de dados de "Entrou/Saiu")
            }

            header('Location: index.php');
            exit;
        } else {
            $msg = "❌ Credenciais incorretas (ou piloto errado).";
        }
    } else {
        $msg = "⚠️ Preenche todos os campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Login - K-Dream</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#f8fafc">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-200 rounded-full blur-[100px] opacity-60"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-purple-200 rounded-full blur-[100px] opacity-60"></div>
    </div>

    <div class="bg-white/80 backdrop-blur-md p-8 rounded-2xl border border-white/50 shadow-[0_8px_30px_rgb(0,0,0,0.04)] w-full max-w-md relative z-10">
        
        <div class="text-center mb-8">
            <h1 class="font-extrabold text-4xl tracking-tight text-slate-800 mb-2">
                K-DREAM <span class="text-blue-500">🇰🇷</span>
            </h1>
            <p class="text-slate-500 text-sm font-medium">Gere o teu futuro com clareza.</p>
        </div>

        <?php if($msg): ?>
            <div class="bg-red-50 border border-red-100 text-red-500 text-sm p-3 rounded-lg mb-6 text-center font-medium">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Username ou Email</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-slate-400">👤</span>
                    <input type="text" name="login_input" required placeholder="" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-10 pr-4 text-slate-700 placeholder-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition font-medium">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Password</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-slate-400">🔒</span>
                    <input type="password" name="password" required placeholder="••••••••" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-10 pr-4 text-slate-700 placeholder-slate-400 focus:border-purple-400 focus:ring-2 focus:ring-purple-100 outline-none transition font-medium">
                </div>
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-blue-500 focus:ring-blue-200">
                    <span class="text-slate-500 group-hover:text-slate-700 transition">Manter sessão iniciada</span>
                </label>
            </div>

            <button type="submit" class="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-slate-300/50 transform transition hover:scale-[1.01] active:scale-95">
                Entrar
            </button>
        </form>

        <div class="mt-8 text-center border-t border-slate-100 pt-6">
            <p class="text-slate-400 text-sm">Ainda não tens equipa?</p>
            <a href="register.php" class="text-blue-500 font-bold hover:text-blue-600 transition">Cria a tua conta aqui</a>
        </div>
    </div>

</body>
</html>