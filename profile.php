<?php
// profile.php
session_start();
require 'includes/db.php';
require 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';

// --- AÇÕES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $filetype = $_FILES['avatar']['type'];
        $filesize = $_FILES['avatar']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $msg = "❌ Formato errado! Usa JPG, PNG ou WEBP.";
            $msg_type = 'error';
        } elseif ($filesize > 5 * 1024 * 1024) { 
            $msg = "❌ Imagem muito pesada! Máximo 5MB.";
            $msg_type = 'error';
        } else {
            // Nota: O caminho é relativo ao ficheiro PHP
            $new_name = "user_" . $user_id . "_" . time() . "." . $ext;
            $destination = "uploads/" . $new_name;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
                $stmt->execute([$destination, $user_id]);
                $msg = "✅ Foto de perfil atualizada!";
                $msg_type = 'success';
            } else {
                $msg = "❌ Erro ao mover ficheiro. (Permissões?)";
                $msg_type = 'error';
            }
        }
    } else {
        $msg = "❌ Erro no upload. Código: " . $_FILES['avatar']['error'];
        $msg_type = 'error';
    }
}

// MUDAR DADOS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_info') {
    $new_email = trim($_POST['email']);
    $new_goal = $_POST['personal_goal'];

    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $msg = "❌ Email inválido.";
        $msg_type = 'error';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$new_email, $user_id]);
        if ($stmt->rowCount() > 0) {
            $msg = "❌ Esse email já está em uso.";
            $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET email = ?, personal_goal = ? WHERE id = ?");
            $stmt->execute([$new_email, $new_goal, $user_id]);
            $msg = "✅ Dados atualizados!";
            $msg_type = 'success';
        }
    }
}

// MUDAR PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!password_verify($current_pass, $user['password_hash'])) {
        $msg = "❌ A password atual está errada.";
        $msg_type = 'error';
    } elseif ($new_pass !== $confirm_pass) {
        $msg = "❌ As novas passwords não coincidem.";
        $msg_type = 'error';
    } elseif (strlen($new_pass) < 6) {
        $msg = "❌ A password nova é muito curta.";
        $msg_type = 'error';
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);
        $msg = "✅ Password alterada com sucesso!";
        $msg_type = 'success';
    }
}

// BUSCAR DADOS
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Definições - K-Dream</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#f8fafc">
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen pb-20">
    
    <nav class="bg-white border-b border-slate-200 p-4 sticky top-0 z-40 shadow-sm mb-6">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <h1 class="font-black text-xl text-slate-800">
                K-DREAM <span class="text-blue-600">🇰🇷</span>
            </h1>
            <div class="flex items-center gap-3">
                <a href="index.php" class="text-xs font-bold bg-slate-100 text-slate-600 px-3 py-2 rounded-lg hover:bg-slate-200 transition">⬅ Voltar</a>
                <a href="logout.php" class="text-xs bg-red-50 text-red-500 px-3 py-2 rounded-lg hover:bg-red-100 transition font-bold">
                    Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 space-y-8">
        <div class="mb-2">
            <h2 class="text-3xl font-bold text-slate-800">⚙️ Definições de Conta</h2>
            <p class="text-slate-500">Afina o motor da tua conta.</p>
        </div>

        <?php if($msg): ?>
            <div class="p-4 rounded-xl border text-center font-medium shadow-sm animate-bounce <?= $msg_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <div class="md:col-span-1 space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm text-center">
                    <div class="w-32 h-32 mx-auto rounded-full overflow-hidden bg-slate-100 border-4 border-slate-50 mb-4 shadow-lg relative">
                        <?php if($me['avatar_url']): ?>
                            <img src="<?= htmlspecialchars($me['avatar_url']) ?>?t=<?= time() ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-4xl font-bold text-slate-300">
                                <?= strtoupper(substr($me['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="space-y-3">
                        <input type="hidden" name="action" value="upload_avatar">
                        <label class="block">
                            <span class="sr-only">Escolher foto</span>
                            <input type="file" name="avatar" accept="image/*" required
                                class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100 cursor-pointer"/>
                        </label>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white py-2 rounded-xl font-bold text-sm transition shadow-md shadow-blue-200">Carregar Foto</button>
                    </form>
                </div>
            </div>

            <div class="md:col-span-2 space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <h3 class="font-bold text-lg mb-4 text-blue-600 flex items-center gap-2">📝 Dados Pessoais</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_info">
                        <div>
                            <label class="block text-sm text-slate-500 mb-1 font-medium">Username</label>
                            <input type="text" value="<?= htmlspecialchars($me['username']) ?>" disabled class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-slate-400 font-medium cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-sm text-slate-500 mb-1 font-medium">Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($me['email']) ?>" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-slate-800 focus:border-blue-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm text-slate-500 mb-1 font-medium">Meta Pessoal (€)</label>
                            <input type="number" name="personal_goal" value="<?= $me['personal_goal'] ?>" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-slate-800 focus:border-blue-500 outline-none transition font-bold">
                        </div>
                        <div class="text-right">
                            <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-white px-6 py-2 rounded-xl font-bold transition shadow-lg">Salvar Dados</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-red-100 shadow-sm">
                    <h3 class="font-bold text-lg mb-4 text-red-500 flex items-center gap-2">🔐 Segurança</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                        <div>
                            <label class="block text-sm text-slate-500 mb-1 font-medium">Password Atual</label>
                            <input type="password" name="current_password" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-slate-800 focus:border-red-400 outline-none transition">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-slate-500 mb-1 font-medium">Nova Password</label>
                                <input type="password" name="new_password" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-slate-800 focus:border-red-400 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-500 mb-1 font-medium">Confirmar</label>
                                <input type="password" name="confirm_password" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-slate-800 focus:border-red-400 outline-none transition">
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-xl font-bold transition shadow-md shadow-red-200">Alterar Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>