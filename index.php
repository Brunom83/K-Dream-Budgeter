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

// --- LÓGICA DE FILTROS 📅 ---
// Se o user escolheu data na navbar, usa essa. Se não, usa o mês atual.
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// --- 1. AÇÕES (POST) ---

// Adicionar Transação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_transaction') {
    $amount = $_POST['amount'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];
    $date = date('Y-m-d'); // Ou podes usar a data do filtro se quiseres, mas hoje é mais seguro

    if (!empty($amount) && !empty($category_id)) {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $category_id, $amount, $description, $date])) {
            $msg = "✅ Movimento registado!";
        }
    }
}

// Apagar Transação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_transaction') {
    $trans_id = $_POST['transaction_id'];
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$trans_id, $user_id])) {
        $msg = "🗑️ Transação eliminada.";
    }
}

// Adicionar Meta (Target)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_target') {
    $target_name = $_POST['target_name'];
    $target_cost = $_POST['target_cost'];
    if (!empty($target_name) && !empty($target_cost)) {
        $stmt = $pdo->prepare("INSERT INTO targets (user_id, name, cost) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $target_name, $target_cost]);
        $msg = "📋 Item adicionado!";
    }
}

// Apagar Meta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_target') {
    $target_id = $_POST['target_id'];
    $stmt = $pdo->prepare("DELETE FROM targets WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$target_id, $user_id])) {
        $msg = "🗑️ Item removido.";
    }
}

// Atualizar Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $new_goal = $_POST['personal_goal'];
    if (is_numeric($new_goal)) {
        $stmt = $pdo->prepare("UPDATE users SET personal_goal = ? WHERE id = ?");
        $stmt->execute([$new_goal, $user_id]);
        
        // Log de atividade
        $stmt_g = $pdo->prepare("SELECT group_id FROM users WHERE id = ?");
        $stmt_g->execute([$user_id]);
        $gid = $stmt_g->fetchColumn();
        if ($gid) {
            logActivity($pdo, $gid, $user_id, "Definiu nova meta pessoal: " . number_format($new_goal, 0) . "€", "info");
        }
        $msg = "✅ Definições guardadas!";
    }
}

// Upload Avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $filesize = $_FILES['avatar']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $msg = "❌ Formato inválido!";
        } elseif ($filesize > 20 * 1024 * 1024) { 
            $msg = "❌ Imagem muito grande!";
        } else {
            if (!is_dir('uploads')) mkdir('uploads', 0777, true);
            $new_name = "user_" . $user_id . "_" . uniqid() . "." . $ext;
            $destination = "uploads/" . $new_name;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
                $stmt->execute([$destination, $user_id]);
                $msg = "📸 Foto atualizada!";
            }
        }
    }
}

// --- 2. BUSCAR DADOS (Otimizado) ---

// A) Dados do Utilizador
$stmt = $pdo->prepare("SELECT u.*, g.name as group_name, g.group_goal FROM users u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();

// B) Saldo TOTAL (Acumulado desde sempre - para a meta de poupança)
$stmt = $pdo->prepare("SELECT SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE -t.amount END) FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ?");
$stmt->execute([$user_id]);
$total_balance = $stmt->fetchColumn() ?: 0;

// C) Saldo do MÊS SELECIONADO (Filtrado - Cashflow)
$stmt = $pdo->prepare("SELECT SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE -t.amount END) 
                       FROM transactions t JOIN categories c ON t.category_id = c.id 
                       WHERE t.user_id = ? AND MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ?");
$stmt->execute([$user_id, $filter_month, $filter_year]);
$monthly_balance = $stmt->fetchColumn() ?: 0;

// D) Transações do MÊS SELECIONADO (Filtrado)
$stmt = $pdo->prepare("SELECT t.*, c.name as category_name, c.type, c.color_hex 
                       FROM transactions t JOIN categories c ON t.category_id = c.id 
                       WHERE t.user_id = ? AND MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ? 
                       ORDER BY t.transaction_date DESC, t.id DESC");
$stmt->execute([$user_id, $filter_month, $filter_year]);
$my_transactions = $stmt->fetchAll();

// E) Metas (Targets)
$stmt = $pdo->prepare("SELECT * FROM targets WHERE user_id = ? ORDER BY cost DESC");
$stmt->execute([$user_id]);
$my_targets = $stmt->fetchAll();
$total_targets_cost = 0;
foreach ($my_targets as $t) { $total_targets_cost += $t['cost']; }

// F) Gráfico (Últimos 6 Meses - Fixo para contexto)
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month_label,
    SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE 0 END) as income,
    SUM(CASE WHEN c.type = 'expense' THEN t.amount ELSE 0 END) as expense
    FROM transactions t JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? GROUP BY month_label ORDER BY month_label ASC LIMIT 6
");
$stmt->execute([$user_id]);
$chart_data = $stmt->fetchAll();

$js_labels = []; $js_income = []; $js_expense = [];
foreach($chart_data as $d) {
    $dateObj = DateTime::createFromFormat('!Y-m', $d['month_label']);
    $js_labels[] = $dateObj->format('M'); 
    $js_income[] = $d['income'];
    $js_expense[] = $d['expense'];
}

// Outros dados auxiliares
$categories = $pdo->query("SELECT * FROM categories ORDER BY type, name")->fetchAll();
$krw_rate = getEurToKrwRate($pdo);
$my_krw = $total_balance * $krw_rate;
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-Dream Dashboard</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#f8fafc">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: visible !important; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen pb-20">

    <nav class="bg-white border-b border-slate-200 p-4 sticky top-0 z-40 shadow-sm flex flex-col md:flex-row justify-between gap-4">
        <div class="flex justify-between items-center w-full md:w-auto">
            <div class="font-black text-xl text-slate-800">K-DREAM <span class="text-blue-600">🇰🇷</span></div>
            <div class="flex gap-2 md:hidden">
                <a href="profile.php" class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden border border-slate-300">
                    <?php if(!empty($me['avatar_url'])): ?><img src="<?= htmlspecialchars($me['avatar_url']) ?>?t=<?= time() ?>" class="w-full h-full object-cover"><?php endif; ?>
                </a>
            </div>
        </div>

        <form method="GET" class="flex gap-2 bg-slate-100 p-1 rounded-lg">
            <select name="month" onchange="this.form.submit()" class="bg-white text-sm font-bold text-slate-700 py-1 px-2 rounded-md shadow-sm border border-slate-200 outline-none">
                <?php 
                $months = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
                foreach($months as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $num == $filter_month ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" onchange="this.form.submit()" class="bg-white text-sm font-bold text-slate-700 py-1 px-2 rounded-md shadow-sm border border-slate-200 outline-none">
                <?php for($y=date('Y'); $y>=2024; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $filter_year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>

        <div class="hidden md:flex items-center gap-3">
            <a href="squad.php" class="text-xs font-bold bg-indigo-50 text-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-100 transition">Squad</a>
            <a href="profile.php" class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden border border-slate-300 hover:border-blue-500 transition">
                 <?php if(!empty($me['avatar_url'])): ?><img src="<?= htmlspecialchars($me['avatar_url']) ?>?t=<?= time() ?>" class="w-full h-full object-cover"><?php endif; ?>
            </a>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto p-4 mt-4 space-y-6">

        <?php if($msg): ?>
            <div class="bg-indigo-50 text-indigo-700 border border-indigo-200 p-3 rounded-lg text-center font-medium shadow-sm animate-bounce">
                <?= $msg ?>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-full blur-3xl opacity-50 -mr-10 -mt-10"></div>
                <h2 class="text-slate-400 text-xs font-bold uppercase tracking-wider relative z-10">Saldo Total (Acumulado)</h2>
                <div class="text-4xl font-black mt-1 text-slate-800 tracking-tight relative z-10">
                    <?= number_format($total_balance, 2, ',', '.') ?>€
                </div>
                <div class="text-blue-500 font-mono text-xs mt-1 font-medium relative z-10">
                    ≈ ₩ <?= number_format($my_krw, 0, ',', '.') ?> KRW
                </div>
                <div class="mt-4 w-full bg-slate-100 rounded-full h-2 overflow-hidden relative z-10">
                    <div class="bg-blue-500 h-2 rounded-full" style="width: <?= min(($total_balance / $me['personal_goal']) * 100, 100) ?>%"></div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-full blur-3xl opacity-50 -mr-10 -mt-10"></div>
                <h2 class="text-slate-400 text-xs font-bold uppercase tracking-wider relative z-10">
                    Fluxo de <?= $months[$filter_month] ?>
                </h2>
                <div class="text-4xl font-black mt-1 tracking-tight relative z-10 <?= $monthly_balance >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                    <?= ($monthly_balance > 0 ? '+' : '') . number_format($monthly_balance, 2, ',', '.') ?>€
                </div>
                <p class="text-xs text-slate-500 mt-2 relative z-10">Este valor reflete apenas o mês selecionado.</p>
                <button onclick="toggleModal('settingsModal')" class="mt-3 text-xs bg-white border border-slate-200 px-3 py-1 rounded-lg text-slate-500 hover:text-blue-600 font-bold transition shadow-sm relative z-10">
                    ⚙️ Editar Meta
                </button>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h3 class="font-bold text-lg mb-4 text-slate-700">📊 Visão Geral (6 Meses)</h3>
            <div class="w-full h-56"><canvas id="moneyChart"></canvas></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex flex-col h-full">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg flex items-center gap-2 text-indigo-600">📋 Planeamento</h3>
                    <div class="<?= ($total_targets_cost > $me['personal_goal']) ? 'text-red-500 bg-red-50' : 'text-emerald-600 bg-emerald-50' ?> px-2 py-1 rounded text-lg font-mono font-bold"><?= number_format($total_targets_cost, 2) ?> €</div>
                </div>
                
                <div class="flex-1 overflow-y-auto max-h-48 mb-4 space-y-2 pr-1">
                    <?php if(empty($my_targets)): ?>
                        <div class="text-slate-400 text-sm text-center italic py-4 border-2 border-dashed border-slate-200 rounded-xl">
                            Adiciona despesas previstas (Voo, etc)
                        </div>
                    <?php else: ?>
                        <?php foreach($my_targets as $t): ?>
                        <div class="flex justify-between items-center bg-slate-50 p-3 rounded-xl border-l-4 border-indigo-500 group">
                            <span class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($t['name']) ?></span>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-mono font-bold text-slate-600"><?= number_format($t['cost'], 0) ?> €</span>
                                <form method="POST" onsubmit="return confirm('Apagar?');">
                                    <input type="hidden" name="action" value="delete_target">
                                    <input type="hidden" name="target_id" value="<?= $t['id'] ?>">
                                    <button class="text-slate-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition">✕</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="POST" class="mt-auto border-t border-slate-100 pt-4 flex gap-2">
                    <input type="hidden" name="action" value="add_target">
                    <input type="text" name="target_name" placeholder="Item" required class="flex-1 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 outline-none transition">
                    <input type="number" name="target_cost" placeholder="€" required class="w-20 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 outline-none transition">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 font-bold transition shadow-md shadow-indigo-200">+</button>
                </form>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg flex items-center gap-2 text-amber-500">⚡ Adicionar</h3>
                    <a href="categories.php" class="text-[10px] text-slate-400 hover:text-blue-500 font-semibold uppercase tracking-wide transition">
                        Gerir Categorias ➡
                    </a>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_transaction">
                    <div class="grid grid-cols-2 gap-4">
                        <input type="number" step="0.01" name="amount" placeholder="Valor €" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-slate-800 focus:border-amber-400 outline-none font-mono text-lg font-bold transition">
                        <select name="category_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-slate-800 focus:border-amber-400 outline-none transition font-medium">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= $cat['type'] == 'income' ? '+' : '-' ?> <?= $cat['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="text" name="description" placeholder="Descrição (Opcional)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-slate-800 text-sm focus:border-amber-400 outline-none transition">
                    <button type="submit" class="w-full bg-amber-400 hover:bg-amber-300 text-amber-900 font-bold py-3 rounded-xl transition shadow-lg shadow-amber-100 transform active:scale-95">REGISTAR MOVIMENTO</button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mt-6">
             <div class="p-4 border-b border-slate-100 bg-white flex justify-between items-center">
                <h3 class="font-bold text-slate-700">Histórico de <?= $months[$filter_month] ?></h3>
            </div>
            <div class="divide-y divide-slate-100">
                <?php if(empty($my_transactions)): ?>
                    <div class="p-8 text-center text-slate-400 italic">Nada registado neste mês.</div>
                <?php else: ?>
                    <?php foreach($my_transactions as $t): ?>
                    <div class="flex items-center justify-between p-4 hover:bg-slate-50 transition group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg shadow-sm" style="background-color: <?= $t['color_hex'] ?>20; color: <?= $t['color_hex'] ?>">
                                <?= $t['type'] == 'income' ? '💰' : '💸' ?>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-700"><?= htmlspecialchars($t['description'] ?: $t['category_name']) ?></p>
                                <p class="text-[10px] text-slate-400 font-medium uppercase"><?= date('d/m', strtotime($t['transaction_date'])) ?> • <span style="color: <?= $t['color_hex'] ?>"><?= $t['category_name'] ?></span></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="font-mono font-bold <?= $t['type'] == 'income' ? 'text-emerald-600' : 'text-red-500' ?>">
                                <?= $t['type'] == 'income' ? '+' : '-' ?><?= number_format($t['amount'], 2) ?>€
                            </span>
                            <form method="POST" onsubmit="return confirm('Apagar registo?');">
                                <input type="hidden" name="action" value="delete_transaction">
                                <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="text-slate-300 hover:text-red-500 transition opacity-0 group-hover:opacity-100 p-1">🗑️</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div id="settingsModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto border border-slate-100">
            <div class="modal-content py-4 text-left px-6">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100">
                    <p class="text-xl font-bold text-slate-800">⚙️ Definições da Meta</p>
                    <div class="modal-close cursor-pointer z-50 text-slate-400 hover:text-slate-800" onclick="toggleModal('settingsModal')">✕</div>
                </div>

                <form method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="update_settings">
                    <div>
                        <label class="block text-sm text-slate-500 mb-2 font-medium">O teu Objetivo Pessoal (€)</label>
                        <input type="number" name="personal_goal" value="<?= $me['personal_goal'] ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-slate-800 text-lg font-bold focus:border-blue-500 focus:outline-none transition">
                    </div>
                    <div class="flex justify-end pt-4 gap-2">
                        <button type="button" onclick="toggleModal('settingsModal')" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-slate-500 hover:bg-slate-50 font-medium">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-xl hover:bg-slate-700 font-bold shadow-lg">Guardar</button>
                    </div>
                </form>

                 <form method="POST" enctype="multipart/form-data" class="mt-6 border-t border-slate-100 pt-6">
                    <input type="hidden" name="action" value="upload_avatar">
                    <label class="block text-sm text-slate-500 mb-2 font-medium">Foto de Perfil</label>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full overflow-hidden bg-slate-200 flex-shrink-0">
                            <?php if($me['avatar_url']): ?>
                                <img src="<?= htmlspecialchars($me['avatar_url']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center font-bold text-slate-400"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="avatar" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    </div>
                    <button type="submit" class="mt-3 w-full bg-blue-600 hover:bg-blue-500 text-white py-2 rounded-xl text-sm font-bold transition shadow-md shadow-blue-200">📸 Atualizar Foto</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleModal(modalID){
            const modal = document.getElementById(modalID);
            modal.classList.toggle('opacity-0');
            modal.classList.toggle('pointer-events-none');
            document.body.classList.toggle('modal-active');
        }
        document.querySelectorAll('.modal-overlay').forEach(el => el.addEventListener('click', () => toggleModal('settingsModal')));

        const ctx = document.getElementById('moneyChart');
        const labels = <?= json_encode($js_labels) ?>;
        const incomeData = <?= json_encode($js_income) ?>;
        const expenseData = <?= json_encode($js_expense) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Entradas',
                        data: incomeData,
                        backgroundColor: '#10B981', 
                        borderRadius: 6,
                        barPercentage: 0.6
                    },
                    {
                        label: 'Saídas',
                        data: expenseData,
                        backgroundColor: '#F43F5E', 
                        borderRadius: 6,
                        barPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#64748b', font: { family: "'Inter', sans-serif", weight: '600' } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { color: '#94a3b8' }, border: { display: false } },
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { family: "'Inter', sans-serif", weight: '600' } }, border: { display: false } }
                }
            }
        });
    </script>
</body>
</html>