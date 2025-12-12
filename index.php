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

// --- 1. AÇÕES (POST) ---

// A) Adicionar Transação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_transaction') {
    $amount = $_POST['amount'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];
    $date = date('Y-m-d');

    if (!empty($amount) && !empty($category_id)) {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $category_id, $amount, $description, $date])) {
            $msg = "✅ Movimento registado!";
        }
    }
}

// B) Eliminar Transação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_transaction') {
    $trans_id = $_POST['transaction_id'];
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$trans_id, $user_id])) {
        $msg = "🗑️ Transação eliminada.";
    }
}

// C) Adicionar Sub-Meta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_target') {
    $target_name = $_POST['target_name'];
    $target_cost = $_POST['target_cost'];
    if (!empty($target_name) && !empty($target_cost)) {
        $stmt = $pdo->prepare("INSERT INTO targets (user_id, name, cost) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $target_name, $target_cost]);
        $msg = "📋 Item adicionado ao plano!";
    }
}

// D) Eliminar Sub-Meta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_target') {
    $target_id = $_POST['target_id'];
    $stmt = $pdo->prepare("DELETE FROM targets WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$target_id, $user_id])) {
        $msg = "🗑️ Item removido.";
    }
}

// E) Atualizar Meta Pessoal (VIA MODAL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $new_goal = $_POST['personal_goal'];
    if (is_numeric($new_goal)) {
        $stmt = $pdo->prepare("UPDATE users SET personal_goal = ? WHERE id = ?");
        $stmt->execute([$new_goal, $user_id]);
        
        $stmt_g = $pdo->prepare("SELECT group_id FROM users WHERE id = ?");
        $stmt_g->execute([$user_id]);
        $gid = $stmt_g->fetchColumn();
        if ($gid) {
            logActivity($pdo, $gid, $user_id, "Definiu nova meta pessoal: " . number_format($new_goal, 0) . "€", "info");
        }
        $msg = "✅ Definições guardadas com sucesso!";
    }
}


// --- 2. DADOS PARA A DASHBOARD ---

// Dados do User + Grupo
$stmt = $pdo->prepare("SELECT u.*, g.name as group_name, g.group_goal FROM users u LEFT JOIN groups g ON u.group_id = g.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();

// Saldo Pessoal
$stmt = $pdo->prepare("SELECT SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE -t.amount END) FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ?");
$stmt->execute([$user_id]);
$my_balance = $stmt->fetchColumn() ?: 0;

// Transações Recentes
$stmt = $pdo->prepare("SELECT t.*, c.name as category_name, c.type FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ? ORDER BY t.transaction_date DESC, t.id DESC LIMIT 5");
$stmt->execute([$user_id]);
$my_transactions = $stmt->fetchAll();

// Sub-Metas
$stmt = $pdo->prepare("SELECT * FROM targets WHERE user_id = ? ORDER BY cost DESC");
$stmt->execute([$user_id]);
$my_targets = $stmt->fetchAll();
$total_targets_cost = 0;
foreach ($my_targets as $t) { $total_targets_cost += $t['cost']; }

// Cálculos de Grupo
$group_balance = 0;
$group_percentage = 0;
if ($me['group_id']) {
    $stmt = $pdo->prepare("SELECT SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE -t.amount END) FROM transactions t JOIN categories c ON t.category_id = c.id JOIN users u ON t.user_id = u.id WHERE u.group_id = ?");
    $stmt->execute([$me['group_id']]);
    $group_balance = $stmt->fetchColumn() ?: 0;
    if ($me['group_goal'] > 0) $group_percentage = ($group_balance / $me['group_goal']) * 100;
}

// Percentagem Pessoal
$my_percentage = 0;
if ($me['personal_goal'] > 0) $my_percentage = ($my_balance / $me['personal_goal']) * 100;

$krw_rate = getEurToKrwRate($pdo);
$my_krw = $my_balance * $krw_rate;
$categories = $pdo->query("SELECT * FROM categories ORDER BY type, name")->fetchAll();

// --- 3. DADOS PARA O GRÁFICO (NOVO) 📊 ---
// Busca os últimos 6 meses
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m') as month_label,
        SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE 0 END) as income,
        SUM(CASE WHEN c.type = 'expense' THEN t.amount ELSE 0 END) as expense
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    GROUP BY month_label
    ORDER BY month_label ASC
    LIMIT 6
");
$stmt->execute([$user_id]);
$chart_data = $stmt->fetchAll();

// Prepara arrays para o JS
$js_labels = [];
$js_income = [];
$js_expense = [];
foreach($chart_data as $d) {
    // Transforma "2025-12" em "Dec"
    $dateObj = DateTime::createFromFormat('!Y-m', $d['month_label']);
    $js_labels[] = $dateObj->format('M'); 
    $js_income[] = $d['income'];
    $js_expense[] = $d['expense'];
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-Dream Dashboard Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <style>
        body { font-family: 'Inter', sans-serif; }
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: visible !important; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen pb-20">

    <nav class="bg-gray-800 border-b border-gray-700 p-4 sticky top-0 z-40 shadow-lg">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="font-black text-xl tracking-tighter text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-500">
                <a href="./">K-DREAM 🇰🇷</a>
            </div>
            <div class="flex items-center gap-4">
                <a href="squad" class="text-sm bg-purple-600/20 text-purple-400 px-3 py-1 rounded hover:bg-purple-600/40 transition">Squad</a>
                <a href="logout" class="text-sm bg-red-500/10 text-red-400 px-3 py-1 rounded hover:bg-red-500/20 transition">Sair</a>
            </div>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto p-4 mt-4 space-y-6">

        <?php if($msg): ?>
            <div class="bg-blue-600/20 text-blue-200 p-3 rounded-lg border border-blue-500/50 text-center animate-bounce">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-blue-600 rounded-full blur-[100px] opacity-20"></div>

            <div class="flex flex-col md:flex-row justify-between items-end mb-6 gap-4 relative z-10">
                <div>
                    <h2 class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Meu Saldo Atual</h2>
                    <div class="text-5xl font-black mt-1 text-white tracking-tight">
                        <?= number_format($my_balance, 2, ',', '.') ?> <span class="text-2xl text-gray-500">€</span>
                    </div>
                    <div class="text-blue-400 font-mono text-sm mt-1">
                        ≈ ₩ <?= number_format($my_krw, 0, ',', '.') ?> KRW
                    </div>
                </div>
                <div>
                    <button onclick="toggleModal('settingsModal')" class="flex items-center gap-2 bg-gray-700 hover:bg-gray-600 border border-gray-600 text-white px-4 py-2 rounded-lg transition shadow-lg">
                        <span>⚙️ Editar Meta</span>
                    </button>
                    <div class="text-right mt-2 text-xs text-gray-400">
                        Objetivo: <?= number_format($me['personal_goal'], 0) ?> €
                    </div>
                </div>
            </div>

            <div class="space-y-5 relative z-10">
                <div>
                    <div class="flex justify-between text-xs font-bold mb-1">
                        <span class="text-blue-400">Progresso Pessoal</span>
                        <span class="text-gray-300">
                            <?= number_format($my_balance, 0, ',', '.') ?>€ / <?= number_format($me['personal_goal'], 0, ',', '.') ?>€
                        </span>
                    </div>
                    <div class="w-full bg-gray-900 rounded-full h-4 overflow-hidden border border-gray-700">
                        <div class="bg-blue-600 h-4 transition-all duration-1000 shadow-[0_0_15px_rgba(37,99,235,0.6)]" style="width: <?= min($my_percentage, 100) ?>%"></div>
                    </div>
                </div>
                <?php if ($me['group_id']): ?>
                <div>
                    <div class="flex justify-between text-xs font-bold mb-1">
                        <span class="text-purple-400">Squad: <?= htmlspecialchars($me['group_name']) ?></span>
                        <span class="text-gray-300">
                            <?= number_format($group_balance, 0, ',', '.') ?>€ / <?= number_format($me['group_goal'], 0, ',', '.') ?>€
                        </span>
                    </div>
                    <div class="w-full bg-gray-900 rounded-full h-4 overflow-hidden border border-gray-700">
                        <div class="bg-gradient-to-r from-purple-600 to-pink-600 h-4 transition-all duration-1000 shadow-[0_0_15px_rgba(147,51,234,0.6)]" style="width: <?= min($group_percentage, 100) ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-gray-800 p-6 rounded-2xl border border-gray-700 shadow-xl">
            <h3 class="font-bold text-lg mb-4 text-gray-300 flex items-center gap-2">
                📊 Fluxo de Caixa (6 Meses)
            </h3>
            <div class="w-full h-64">
                <canvas id="moneyChart"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-gray-800 p-5 rounded-xl border border-gray-700 flex flex-col h-full">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg flex items-center gap-2 text-indigo-400">📋 Planeamento</h3>
                    <div class="<?= ($total_targets_cost > $me['personal_goal']) ? 'text-red-400 animate-pulse' : 'text-green-400' ?> text-lg font-mono font-bold"><?= number_format($total_targets_cost, 2) ?> €</div>
                </div>
                
                <div class="flex-1 overflow-y-auto max-h-48 mb-4 space-y-2 custom-scrollbar">
                    <?php if(empty($my_targets)): ?>
                        <div class="text-gray-600 text-sm text-center italic py-4 border-2 border-dashed border-gray-700 rounded">
                            Adiciona despesas previstas (Voo, etc)
                        </div>
                    <?php else: ?>
                        <?php foreach($my_targets as $t): ?>
                        <div class="flex justify-between items-center bg-gray-900 p-3 rounded border-l-4 border-indigo-500 shadow-sm group">
                            <span class="text-sm font-medium"><?= htmlspecialchars($t['name']) ?></span>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-mono font-bold"><?= number_format($t['cost'], 0) ?> €</span>
                                <form method="POST" onsubmit="return confirm('Apagar?');">
                                    <input type="hidden" name="action" value="delete_target">
                                    <input type="hidden" name="target_id" value="<?= $t['id'] ?>">
                                    <button class="text-gray-600 hover:text-red-500 opacity-0 group-hover:opacity-100 transition">✕</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="POST" class="mt-auto border-t border-gray-700 pt-4 flex gap-2">
                    <input type="hidden" name="action" value="add_target">
                    <input type="text" name="target_name" placeholder="Item (ex: Voo)" required class="flex-1 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white focus:border-indigo-500 outline-none">
                    <input type="number" name="target_cost" placeholder="€" required class="w-20 bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm text-white focus:border-indigo-500 outline-none">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded px-4 font-bold transition">+</button>
                </form>
            </div>

            <div class="bg-gray-800 p-5 rounded-xl border border-gray-700">
                <h3 class="font-bold text-lg mb-4 flex items-center gap-2 text-yellow-400">⚡ Adicionar</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_transaction">
                    <div class="grid grid-cols-2 gap-4">
                        <input type="number" step="0.01" name="amount" placeholder="Valor €" required class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-3 text-white focus:border-yellow-400 outline-none font-mono text-lg">
                        <select name="category_id" required class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-3 text-white focus:border-yellow-400 outline-none">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= $cat['type'] == 'income' ? '+' : '-' ?> <?= $cat['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="text" name="description" placeholder="Descrição" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white text-sm focus:border-yellow-400 outline-none">
                    <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-400 text-black font-bold py-3 rounded transition shadow-lg transform active:scale-95">REGISTAR</button>
                </form>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden mt-6">
             <div class="p-4 border-b border-gray-700 bg-gray-800/50 flex justify-between items-center">
                <h3 class="font-bold text-gray-300">Histórico Recente</h3>
            </div>
            <div class="divide-y divide-gray-700">
                <?php foreach($my_transactions as $t): ?>
                <div class="flex items-center justify-between p-4 hover:bg-gray-700/30 transition group">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full <?= $t['type'] == 'income' ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                        <div>
                            <p class="text-sm font-semibold text-white"><?= htmlspecialchars($t['description'] ?: $t['category_name']) ?></p>
                            <p class="text-[10px] text-gray-500 uppercase"><?= date('d/m', strtotime($t['transaction_date'])) ?> • <?= $t['category_name'] ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="font-mono font-bold <?= $t['type'] == 'income' ? 'text-green-400' : 'text-red-400' ?>">
                            <?= $t['type'] == 'income' ? '+' : '-' ?><?= number_format($t['amount'], 2) ?>€
                        </span>
                        <form method="POST" onsubmit="return confirm('Apagar registo?');">
                            <input type="hidden" name="action" value="delete_transaction">
                            <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="text-gray-600 hover:text-red-500 transition opacity-0 group-hover:opacity-100 p-1">🗑️</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <div id="settingsModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-black opacity-50"></div>
        <div class="modal-container bg-gray-800 w-11/12 md:max-w-md mx-auto rounded-xl shadow-2xl z-50 overflow-y-auto border border-gray-600">
            <div class="modal-content py-4 text-left px-6">
                <div class="flex justify-between items-center pb-3 border-b border-gray-700">
                    <p class="text-xl font-bold text-blue-400">⚙️ Definições da Meta</p>
                    <div class="modal-close cursor-pointer z-50 text-gray-400 hover:text-white" onclick="toggleModal('settingsModal')">
                        <svg class="fill-current text-white" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18"><path d="M14.53 4.53l-1.06-1.06L9 7.94 4.53 3.47 3.47 4.53 7.94 9l-4.47 4.47 1.06 1.06L9 10.06l4.47 4.47 1.06-1.06L10.06 9z"></path></svg>
                    </div>
                </div>
                <form method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="update_settings">
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">O teu Objetivo Pessoal (€)</label>
                        <input type="number" name="personal_goal" value="<?= $me['personal_goal'] ?>" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-3 text-white text-lg font-bold focus:border-blue-500 focus:outline-none">
                        <p class="text-xs text-gray-500 mt-2">Esta é a tua parte do objetivo total.</p>
                    </div>
                    <div class="flex justify-end pt-4">
                        <button type="button" onclick="toggleModal('settingsModal')" class="px-4 py-2 bg-transparent p-3 rounded-lg text-gray-400 hover:bg-gray-700 hover:text-white mr-2">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 rounded-lg text-white hover:bg-blue-500 font-bold shadow-lg">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal Logic
        function toggleModal(modalID){
            const modal = document.getElementById(modalID);
            modal.classList.toggle('opacity-0');
            modal.classList.toggle('pointer-events-none');
            document.body.classList.toggle('modal-active');
        }
        document.querySelectorAll('.modal-overlay').forEach(el => el.addEventListener('click', () => toggleModal('settingsModal')));

        // --- CHART.JS CONFIGURATION ---
        const ctx = document.getElementById('moneyChart');
        
        // Recebe os dados do PHP (json_encode transforma o Array PHP em Array JS)
        const labels = <?= json_encode($js_labels) ?>;
        const incomeData = <?= json_encode($js_income) ?>;
        const expenseData = <?= json_encode($js_expense) ?>;

        new Chart(ctx, {
            type: 'bar', // Gráfico de Barras
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Entradas (Income)',
                        data: incomeData,
                        backgroundColor: '#10B981', // Verde Esmeralda
                        borderRadius: 4,
                        barPercentage: 0.6
                    },
                    {
                        label: 'Saídas (Expenses)',
                        data: expenseData,
                        backgroundColor: '#EF4444', // Vermelho Perigo
                        borderRadius: 4,
                        barPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Deixa o gráfico esticar
                plugins: {
                    legend: {
                        labels: { color: '#9CA3AF' } // Cor da legenda
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#374151' }, // Cor das linhas de grade (cinza escuro)
                        ticks: { color: '#9CA3AF' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#9CA3AF' }
                    }
                }
            }
        });
    </script>
</body>
</html>