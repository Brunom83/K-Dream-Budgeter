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
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-900 text-white min-h-screen pb-20">
    <nav class="bg-gray-800 border-b border-gray-700 p-4">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <h1 class="font-black text-xl text-blue-500"><a href="./">K-DREAM 🇰🇷</h1>
            <a href="index.php" class="text-gray-400 hover:text-white flex items-center gap-2 transition">
                <span>⬅</span> Voltar
            </a>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto p-6 space-y-8">
        <div class="mb-6">
            <h2 class="text-3xl font-bold">⚙️ Definições de Conta</h2>
        </div>

        <?php if($msg): ?>
            <div class="p-4 rounded border <?= $msg_type === 'success' ? 'bg-green-500/20 border-green-500 text-green-300' : 'bg-red-500/20 border-red-500 text-red-300' ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-1 space-y-6">
                <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 text-center">
                    <div class="w-32 h-32 mx-auto rounded-full overflow-hidden bg-gray-700 border-4 border-gray-600 mb-4 shadow-xl">
                        <?php if($me['avatar_url']): ?>
                            <img src="<?= htmlspecialchars($me['avatar_url']) ?>?t=<?= time() ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-4xl font-bold text-gray-500">
                                <?= strtoupper(substr($me['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="space-y-3">
                        <input type="hidden" name="action" value="upload_avatar">
                        <label class="block">
                            <span class="sr-only">Escolher foto</span>
                            <input type="file" name="avatar" accept="image/*" required
                                class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-500 cursor-pointer"/>
                        </label>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white py-2 rounded font-bold text-sm transition">Carregar Foto</button>
                    </form>
                </div>
            </div>

            <div class="md:col-span-2 space-y-6">
                <div class="bg-gray-800 p-6 rounded-xl border border-gray-700">
                    <h3 class="font-bold text-lg mb-4 text-blue-400">📝 Dados Pessoais</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_info">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Username</label>
                            <input type="text" value="<?= htmlspecialchars($me['username']) ?>" disabled class="w-full bg-gray-900/50 border border-gray-600 rounded px-4 py-2 text-gray-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($me['email']) ?>" required class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Meta Pessoal (€)</label>
                            <input type="number" name="personal_goal" value="<?= $me['personal_goal'] ?>" required class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-white">
                        </div>
                        <div class="text-right">
                            <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white px-6 py-2 rounded font-bold transition">Salvar</button>
                        </div>
                    </form>
                </div>

                <div class="bg-gray-800 p-6 rounded-xl border border-red-900/30">
                    <h3 class="font-bold text-lg mb-4 text-red-400">🔐 Segurança</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Password Atual</label>
                            <input type="password" name="current_password" required class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-white">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Nova Password</label>
                                <input type="password" name="new_password" required class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-white">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Confirmar</label>
                                <input type="password" name="confirm_password" required class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-white">
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-6 py-2 rounded font-bold transition">Alterar Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>