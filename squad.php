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

// --- ACTIONS ---

// 1. CRIAR GRUPO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
    $group_name = trim($_POST['group_name']);
    $group_goal = $_POST['group_goal'];
    $invite_code = strtoupper(substr(md5(time()), 0, 6));

    if (!empty($group_name) && !empty($group_goal)) {
        try {
            $pdo->beginTransaction();
            // CORREÇÃO AQUI: `groups`
            $stmt = $pdo->prepare("INSERT INTO `groups` (name, group_goal, invite_code, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$group_name, $group_goal, $invite_code, $user_id]);
            $new_group_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("UPDATE users SET group_id = ? WHERE id = ?");
            $stmt->execute([$new_group_id, $user_id]);
            
            logActivity($pdo, $new_group_id, $user_id, "Criou a Squad '$group_name'", "success");

            $pdo->commit();
            $msg = "✅ Squad criada!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "❌ Erro: " . $e->getMessage();
        }
    }
}

// 2. ENTRAR GRUPO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_group') {
    $code = trim($_POST['invite_code']);
    // CORREÇÃO AQUI: `groups`
    $stmt = $pdo->prepare("SELECT id, name FROM `groups` WHERE invite_code = ?");
    $stmt->execute([$code]);
    $group = $stmt->fetch();

    if ($group) {
        $stmt = $pdo->prepare("UPDATE users SET group_id = ? WHERE id = ?");
        $stmt->execute([$group['id'], $user_id]);
        
        logActivity($pdo, $group['id'], $user_id, "Juntou-se à Squad!", "success");
        $msg = "✅ Entraste na Squad!";
    } else {
        $msg = "❌ Código inválido.";
    }
}

// 3. ATUALIZAR META DO GRUPO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_group_goal') {
    $new_goal = $_POST['group_goal'];
    $group_id = $_POST['group_id'];
    
    // CORREÇÃO AQUI: `groups`
    $stmt = $pdo->prepare("UPDATE `groups` SET group_goal = ? WHERE id = ?");
    if ($stmt->execute([$new_goal, $group_id])) {
        logActivity($pdo, $group_id, $user_id, "Alterou a Meta da Squad para " . number_format($new_goal, 0) . "€", "warning");
        $msg = "✅ Meta da Squad atualizada!";
    }
}

// 4. SAIR DO GRUPO (LEAVE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'leave_group') {
    $group_id = $_POST['group_id'];
    
    logActivity($pdo, $group_id, $user_id, "Saiu da Squad.", "danger");

    $stmt = $pdo->prepare("UPDATE users SET group_id = NULL WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        $msg = "👋 Saíste da Squad.";
        header("Refresh:0");
        exit;
    }
}

// 5. APAGAR GRUPO (DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_group') {
    $group_id = $_POST['group_id'];
    
    // CORREÇÃO AQUI: `groups`
    $stmt = $pdo->prepare("SELECT created_by FROM `groups` WHERE id = ?");
    $stmt->execute([$group_id]);
    $creator = $stmt->fetchColumn();

    if ($creator == $user_id) {
        // CORREÇÃO AQUI: `groups`
        $stmt = $pdo->prepare("DELETE FROM `groups` WHERE id = ?");
        $stmt->execute([$group_id]);
        $msg = "💥 Squad eliminada.";
        header("Refresh:0");
        exit;
    } else {
        $msg = "❌ Só o Admin pode apagar a Squad!";
    }
}


// --- DADOS ---
// CORREÇÃO AQUI: LEFT JOIN `groups`
$stmt = $pdo->prepare("SELECT u.*, g.name as group_name, g.invite_code, g.group_goal, g.created_by as admin_id FROM users u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();

$members = [];
$logs = [];

if ($me['group_id']) {
    $stmt = $pdo->prepare("SELECT username, personal_goal, avatar_url FROM users WHERE group_id = ?");
    $stmt->execute([$me['group_id']]);
    $members = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT l.*, u.username 
        FROM activity_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        WHERE l.group_id = ? 
        ORDER BY l.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$me['group_id']]);
    $logs = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Squad Hub - K-Dream</title>
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-900 text-white min-h-screen p-6">
    
    <div class="max-w-4xl mx-auto space-y-8 pb-10">
        <div class="flex justify-between items-center border-b border-gray-700 pb-4">
            <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-600">
                💎 Squad Hub
            </h1>
            <a href="index" class="text-gray-400 hover:text-white flex items-center gap-2 transition">
                <span>⬅</span> Dashboard
            </a>
        </div>

        <?php if($msg): ?>
            <div class="bg-blue-600/20 text-blue-200 p-4 rounded border border-blue-500/50 text-center animate-pulse">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <?php if (!$me['group_id']): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gray-800 p-8 rounded-xl border border-gray-700 hover:border-purple-500 transition shadow-lg group">
                    <h3 class="font-bold text-xl mb-4 text-purple-400 group-hover:text-purple-300">Criar Nova Squad</h3>
                    <p class="text-gray-400 text-sm mb-4">Lidera a tua equipa rumo à Coreia.</p>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_group">
                        <input type="text" name="group_name" placeholder="Nome da Team (ex: K-Trip)" required class="w-full bg-gray-700 rounded px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <input type="number" name="group_goal" placeholder="Meta Total (€)" required class="w-full bg-gray-700 rounded px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 py-3 rounded font-bold shadow-lg transition transform hover:scale-[1.02]">Criar & Liderar 👑</button>
                    </form>
                </div>

                <div class="bg-gray-800 p-8 rounded-xl border border-gray-700 hover:border-blue-500 transition shadow-lg group">
                    <h3 class="font-bold text-xl mb-4 text-blue-400 group-hover:text-blue-300">Entrar numa Squad</h3>
                    <p class="text-gray-400 text-sm mb-4">Já tens código? Entra aqui.</p>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="join_group">
                        <input type="text" name="invite_code" placeholder="Código (#A1B2C3)" required class="w-full bg-gray-700 rounded px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-center tracking-widest uppercase">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 py-3 rounded font-bold shadow-lg transition transform hover:scale-[1.02]">Juntar-se 🚀</button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <div class="md:col-span-2 space-y-6">
                    <div class="bg-gradient-to-r from-purple-900 to-indigo-900 p-6 rounded-2xl border border-purple-500/30 shadow-2xl relative overflow-hidden">
                        <div class="flex justify-between items-start z-10 relative">
                            <div>
                                <h2 class="text-3xl font-black italic text-white drop-shadow-lg"><?= htmlspecialchars($me['group_name']) ?></h2>
                                <p class="text-purple-200 mt-1">Objetivo Comum</p>
                                
                                <form method="POST" class="mt-2 flex items-center gap-2">
                                    <input type="hidden" name="action" value="update_group_goal">
                                    <input type="hidden" name="group_id" value="<?= $me['group_id'] ?>">
                                    <input type="number" name="group_goal" value="<?= $me['group_goal'] ?>" 
                                           class="bg-black/30 text-white font-bold px-2 py-1 rounded w-32 border border-purple-400/50 outline-none">
                                    <button type="submit" class="text-xs bg-purple-500 px-2 py-1 rounded hover:bg-purple-400 transition">💾</button>
                                </form>
                            </div>
                            <div class="text-right bg-black/40 p-3 rounded-lg backdrop-blur-sm border border-white/10">
                                <span class="text-[10px] uppercase text-gray-400 block mb-1">Invite Code</span>
                                <div class="text-2xl font-mono font-bold text-yellow-400 select-all tracking-widest cursor-pointer hover:text-yellow-300">
                                    <?= $me['invite_code'] ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                        <h3 class="text-gray-400 font-bold uppercase text-xs mb-4">Membros da Squad</h3>
                        <div class="space-y-3">
                            <?php foreach($members as $member): ?>
                                <div class="flex items-center justify-between bg-gray-700/50 p-3 rounded-lg border border-gray-700/50">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center font-bold text-lg border-2 border-gray-800 overflow-hidden">
                                            <?php if(!empty($member['avatar_url'])): ?>
                                                <img src="<?= htmlspecialchars($member['avatar_url']) ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <?= strtoupper(substr($member['username'], 0, 1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-bold flex items-center gap-2">
                                                <?= htmlspecialchars($member['username']) ?>
                                                <?php if($me['admin_id'] == $user_id && $member['username'] == $user_name): ?>
                                                    <span class="text-[10px] bg-purple-500/20 text-purple-300 px-1 rounded border border-purple-500/30">LÍDER</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xs text-gray-400">Meta Pessoal: <?= number_format($member['personal_goal'], 0) ?>€</div>
                                        </div>
                                    </div>
                                    <?php if($member['personal_goal'] >= 5000): ?>
                                        <span class="text-lg" title="MVP">🏆</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="border border-red-900/50 rounded-xl p-6 bg-red-900/10">
                        <h3 class="text-red-500 font-bold uppercase text-xs mb-4 flex items-center gap-2">
                            ⚠️ Zona de Perigo
                        </h3>
                        <div class="flex flex-wrap gap-4 items-center justify-between">
                            <div class="text-sm text-gray-400">
                                Se saíres, tens de pedir o código para entrar de novo.
                            </div>
                            
                            <div class="flex gap-4">
                                <form method="POST" onsubmit="return confirm('Tens a certeza que queres abandonar a Squad?');">
                                    <input type="hidden" name="action" value="leave_group">
                                    <input type="hidden" name="group_id" value="<?= $me['group_id'] ?>">
                                    <button type="submit" class="px-4 py-2 border border-red-600 text-red-500 rounded hover:bg-red-600 hover:text-white transition text-sm font-bold">
                                        Sair da Squad
                                    </button>
                                </form>

                                <?php if($me['admin_id'] == $user_id): ?>
                                <form method="POST" onsubmit="return confirm('ATENÇÃO: Isto apaga o grupo para TODOS! Não há volta a dar. Confirmas?');">
                                    <input type="hidden" name="action" value="delete_group">
                                    <input type="hidden" name="group_id" value="<?= $me['group_id'] ?>">
                                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition text-sm font-bold shadow-lg">
                                        💣 Apagar Squad
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="md:col-span-1">
                    <div class="bg-gray-800 rounded-xl p-0 border border-gray-700 h-full overflow-hidden flex flex-col shadow-xl">
                        <div class="p-4 border-b border-gray-700 bg-gray-800/80 backdrop-blur sticky top-0">
                            <h3 class="font-bold text-gray-300 flex items-center gap-2">
                                🔔 Atividade Recente
                            </h3>
                        </div>
                        <div class="p-4 overflow-y-auto max-h-[600px] space-y-4 custom-scrollbar bg-gray-900/30 flex-1">
                            <?php if(empty($logs)): ?>
                                <div class="text-gray-500 text-center text-sm italic py-10 opacity-50">
                                    <div class="text-2xl mb-2">💤</div>
                                    Tudo calmo por aqui...
                                </div>
                            <?php else: ?>
                                <?php foreach($logs as $log): ?>
                                    <div class="flex gap-3 items-start relative pl-2">
                                        <div class="absolute left-0 top-2 bottom-0 w-[1px] bg-gray-800 -z-10"></div>
                                        <div class="mt-1.5 min-w-[8px] h-2 rounded-full ring-4 ring-gray-900 
                                            <?= $log['type'] === 'success' ? 'bg-green-500' : 
                                               ($log['type'] === 'warning' ? 'bg-yellow-500' : 
                                               ($log['type'] === 'danger' ? 'bg-red-500' : 'bg-blue-500')) ?>">
                                        </div>
                                        <div class="bg-gray-800/50 p-2 rounded w-full border border-gray-700/50 hover:border-gray-600 transition">
                                            <p class="text-sm text-gray-300 leading-tight">
                                                <span class="font-bold text-white"><?= htmlspecialchars($log['username'] ?? 'Alguém') ?></span> 
                                                <?= htmlspecialchars($log['message']) ?>
                                            </p>
                                            <span class="text-[10px] text-gray-500 mt-1 block">
                                                <?= date('d/m H:i', strtotime($log['created_at'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>
        
    </div>
</body>
</html>