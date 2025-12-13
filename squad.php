<?php
session_start();
require 'includes/db.php';
require 'includes/functions.php';

// Proteção de Login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$msg = '';

// --- ACTIONS (Lógica mantém-se igual) ---

// --- AÇÕES DO CHAT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $message = trim($_POST['message']);
    $group_id = $_POST['group_id'];
    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO squad_chat (group_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$group_id, $user_id, $message]);
        // Refresh para não reenviar form
        header("Location: squad.php");
        exit;
    }
}

// --- 1. CRIAR GRUPO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
    $group_name = trim($_POST['group_name']);
    $group_goal = $_POST['group_goal'];
    $invite_code = strtoupper(substr(md5(time()), 0, 6));

    if (!empty($group_name) && !empty($group_goal)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO `groups` (name, group_goal, invite_code, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$group_name, $group_goal, $invite_code, $user_id]);
            $new_group_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("UPDATE users SET group_id = ? WHERE id = ?");
            $stmt->execute([$new_group_id, $user_id]);
            logActivity($pdo, $new_group_id, $user_id, "Criou a Squad '$group_name'", "success");
            $pdo->commit();
            $msg = "✅ Squad criada!";
        } catch (Exception $e) { $pdo->rollBack(); $msg = "❌ Erro: " . $e->getMessage(); }
    }
}

// 2. ENTRAR GRUPO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_group') {
    $code = trim($_POST['invite_code']);
    $stmt = $pdo->prepare("SELECT id, name FROM `groups` WHERE invite_code = ?");
    $stmt->execute([$code]);
    $group = $stmt->fetch();
    if ($group) {
        $stmt = $pdo->prepare("UPDATE users SET group_id = ? WHERE id = ?");
        $stmt->execute([$group['id'], $user_id]);
        logActivity($pdo, $group['id'], $user_id, "Juntou-se à Squad!", "success");
        $msg = "✅ Entraste na Squad!";
    } else { $msg = "❌ Código inválido."; }
}

// 3. ATUALIZAR META
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_group_goal') {
    $new_goal = $_POST['group_goal'];
    $group_id = $_POST['group_id'];
    $stmt = $pdo->prepare("UPDATE `groups` SET group_goal = ? WHERE id = ?");
    if ($stmt->execute([$new_goal, $group_id])) {
        logActivity($pdo, $group_id, $user_id, "Alterou a Meta da Squad para " . number_format($new_goal, 0) . "€", "warning");
        $msg = "✅ Meta atualizada!";
    }
}

// 4. SAIR DO GRUPO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'leave_group') {
    $group_id = $_POST['group_id'];
    logActivity($pdo, $group_id, $user_id, "Saiu da Squad.", "danger");
    $stmt = $pdo->prepare("UPDATE users SET group_id = NULL WHERE id = ?");
    if ($stmt->execute([$user_id])) { $msg = "👋 Saíste."; header("Refresh:0"); exit; }
}

// 5. APAGAR GRUPO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_group') {
    $group_id = $_POST['group_id'];
    $stmt = $pdo->prepare("SELECT created_by FROM `groups` WHERE id = ?");
    $stmt->execute([$group_id]);
    $creator = $stmt->fetchColumn();
    if ($creator == $user_id) {
        $stmt = $pdo->prepare("DELETE FROM `groups` WHERE id = ?");
        $stmt->execute([$group_id]);
        $msg = "💥 Squad eliminada."; header("Refresh:0"); exit;
    } else { $msg = "❌ Só o Admin pode apagar."; }
}

// --- DADOS ---
$stmt = $pdo->prepare("SELECT u.*, g.name as group_name, g.invite_code, g.group_goal, g.created_by as admin_id FROM users u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();

$members = []; $logs = []; $chat_messages = [];

if ($me['group_id']) {
    $stmt = $pdo->prepare("SELECT username, personal_goal, avatar_url FROM users WHERE group_id = ?");
    $stmt->execute([$me['group_id']]);
    $members = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.group_id = ? ORDER BY l.created_at DESC LIMIT 10");
    $stmt->execute([$me['group_id']]);
    $logs = $stmt->fetchAll();

    // BUSCAR MENSAGENS DO CHAT 💬
    $stmt = $pdo->prepare("SELECT c.*, u.username, u.avatar_url FROM squad_chat c JOIN users u ON c.user_id = u.id WHERE c.group_id = ? ORDER BY c.created_at ASC LIMIT 50");
    $stmt->execute([$me['group_id']]);
    $chat_messages = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Squad Hub - K-Dream</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#f8fafc">
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen pb-10">
    
    <nav class="bg-white border-b border-slate-200 p-4 sticky top-0 z-40 shadow-sm mb-6">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <h1 class="font-black text-xl text-slate-800">K-DREAM <span class="text-blue-600">🇰🇷</span></h1>
            <div class="flex items-center gap-3">
                <a href="index.php" class="text-xs font-bold bg-slate-100 text-slate-600 px-3 py-2 rounded-lg hover:bg-slate-200 transition">⬅ Dashboard</a>
                <a href="profile.php" class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden border border-slate-300">
                    <?php if(!empty($me['avatar_url'])): ?><img src="<?= htmlspecialchars($me['avatar_url']) ?>" class="w-full h-full object-cover"><?php else: ?><div class="w-full h-full flex items-center justify-center text-xs font-bold text-slate-500"><?= strtoupper(substr($user_name, 0, 1)) ?></div><?php endif; ?>
                </a>
                <a href="logout.php" class="text-xs bg-red-50 text-red-500 px-3 py-2 rounded-lg hover:bg-red-100 transition font-bold">Sair</a>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 space-y-8">
        <?php if($msg): ?> <div class="bg-blue-50 text-blue-700 p-4 rounded-xl border border-blue-200 text-center font-medium shadow-sm animate-bounce"><?= $msg ?></div> <?php endif; ?>

        <?php if (!$me['group_id']): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm">
                    <h3 class="font-bold text-xl mb-4 text-purple-600">Criar Nova Squad</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_group">
                        <input type="text" name="group_name" placeholder="Nome da Team" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                        <input type="number" name="group_goal" placeholder="Meta Total (€)" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                        <button type="submit" class="w-full bg-purple-600 text-white py-3 rounded-xl font-bold transition">Criar 👑</button>
                    </form>
                </div>
                <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm">
                    <h3 class="font-bold text-xl mb-4 text-blue-600">Entrar numa Squad</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="join_group">
                        <input type="text" name="invite_code" placeholder="#A1B2C3" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-center uppercase">
                        <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold transition">Entrar 🚀</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2 space-y-6">
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 p-6 rounded-2xl shadow-lg text-white">
                        <h2 class="text-3xl font-black italic"><?= htmlspecialchars($me['group_name']) ?></h2>
                        <p class="text-purple-100 text-sm font-bold mt-1">CODE: <span class="font-mono bg-white/20 px-2 rounded select-all"><?= $me['invite_code'] ?></span></p>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col h-[500px]">
                        <div class="p-4 border-b border-slate-100 font-bold text-slate-700 flex justify-between items-center">
                            <span>📻 Team Radio</span>
                            <span class="text-xs font-normal text-slate-400">Live Chat</span>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50" id="chatContainer">
                            <?php if(empty($chat_messages)): ?>
                                <p class="text-center text-slate-400 text-sm mt-10">Digam olá à equipa! 👋</p>
                            <?php else: ?>
                                <?php foreach($chat_messages as $c): ?>
                                    <?php $is_me = ($c['user_id'] == $user_id); ?>
                                    <div class="flex gap-2 <?= $is_me ? 'flex-row-reverse' : '' ?>">
                                        <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden flex-shrink-0">
                                             <?php if($c['avatar_url']): ?><img src="<?= $c['avatar_url'] ?>" class="w-full h-full object-cover"><?php else: ?><div class="w-full h-full flex items-center justify-center text-[10px] font-bold text-slate-500"><?= strtoupper(substr($c['username'], 0, 1)) ?></div><?php endif; ?>
                                        </div>
                                        <div class="max-w-[70%]">
                                            <div class="text-[10px] text-slate-400 mb-0.5 <?= $is_me ? 'text-right' : '' ?>"><?= htmlspecialchars($c['username']) ?> • <?= date('H:i', strtotime($c['created_at'])) ?></div>
                                            <div class="px-3 py-2 rounded-xl text-sm <?= $is_me ? 'bg-blue-600 text-white rounded-tr-none' : 'bg-white border border-slate-200 text-slate-700 rounded-tl-none' ?>">
                                                <?= htmlspecialchars($c['message']) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="p-3 bg-white border-t border-slate-100">
                            <form method="POST" class="flex gap-2">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="group_id" value="<?= $me['group_id'] ?>">
                                <input type="text" name="message" placeholder="Escreve aqui..." required autocomplete="off" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:outline-none focus:border-blue-500">
                                <button type="submit" class="bg-blue-600 text-white p-2 rounded-xl hover:bg-blue-500 transition">➤</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-1 space-y-6">
                    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                        <h3 class="text-slate-400 font-bold uppercase text-xs mb-4">Membros</h3>
                        <div class="space-y-3">
                            <?php foreach($members as $m): ?>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 overflow-hidden">
                                        <?php if($m['avatar_url']): ?><img src="<?= $m['avatar_url'] ?>" class="w-full h-full object-cover"><?php else: ?><div class="w-full h-full flex items-center justify-center text-xs font-bold text-slate-500"><?= strtoupper(substr($m['username'], 0, 1)) ?></div><?php endif; ?>
                                    </div>
                                    <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($m['username']) ?></span>
                                    <?php if($m['personal_goal'] >= 5000): ?>🏆<?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="border border-red-100 rounded-2xl p-4 bg-red-50 text-center">
                        <form method="POST" onsubmit="return confirm('Sair?');">
                            <input type="hidden" name="action" value="leave_group">
                            <input type="hidden" name="group_id" value="<?= $me['group_id'] ?>">
                            <button class="text-xs text-red-500 font-bold hover:underline">Sair da Squad</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script>
        // Scroll chat to bottom
        const chatBox = document.getElementById('chatContainer');
        if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    </script>
</body>
</html>