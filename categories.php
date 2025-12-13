<?php
session_start();
require 'includes/db.php';
require 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$msg = '';

// ADICIONAR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $color = $_POST['color'];

    if (!empty($name)) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->rowCount() > 0) {
            $msg = "⚠️ Já existe.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, type, color_hex) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $type, $color])) {
                $msg = "✅ Criada!";
            } else { $msg = "❌ Erro."; }
        }
    }
}

// APAGAR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    $cat_id = $_POST['category_id'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    if ($stmt->execute([$cat_id])) { $msg = "🗑️ Eliminada."; }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY type, name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Categorias - K-Dream</title>
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
            <a href="index.php" class="text-xs font-bold bg-slate-100 text-slate-600 px-3 py-2 rounded-lg hover:bg-slate-200 transition">⬅ Voltar</a>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 space-y-8">
        <div>
            <h2 class="text-3xl font-bold text-slate-800">🏷️ Categorias</h2>
            <p class="text-slate-500">Organiza o teu dinheiro com cor.</p>
        </div>

        <?php if($msg): ?>
            <div class="bg-indigo-50 text-indigo-700 p-3 rounded-xl border border-indigo-200 text-center font-medium shadow-sm animate-bounce">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-1">
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm sticky top-24">
                    <h3 class="font-bold text-lg mb-4 text-purple-600">Nova Categoria</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_category">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Nome</label>
                            <input type="text" name="name" placeholder="Ex: K-Pop" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 outline-none focus:border-purple-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Tipo</label>
                            <select name="type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 outline-none focus:border-purple-500 transition">
                                <option value="expense">📉 Despesa</option>
                                <option value="income">📈 Receita</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Cor</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="color" value="#a855f7" class="h-10 w-full bg-transparent cursor-pointer rounded-lg border border-slate-200">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-bold py-2 rounded-xl shadow-md transition">Criar</button>
                    </form>
                </div>
            </div>

            <div class="md:col-span-2 space-y-6">
                
                <div>
                    <h3 class="font-bold text-emerald-600 mb-3 text-sm uppercase tracking-wider border-b border-emerald-100 pb-1">Receitas</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach($categories as $cat): ?>
                            <?php if($cat['type'] === 'income'): ?>
                                <div class="bg-white p-3 rounded-xl border border-slate-200 flex justify-between items-center group hover:border-emerald-300 transition shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="w-6 h-6 rounded-full shadow-inner" style="background-color: <?= $cat['color_hex'] ?>"></div>
                                        <span class="font-medium text-slate-700"><?= htmlspecialchars($cat['name']) ?></span>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Apagar? Transações antigas ficarão sem categoria.');">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button class="text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition p-1">✕</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold text-red-500 mb-3 text-sm uppercase tracking-wider border-b border-red-100 pb-1">Despesas</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach($categories as $cat): ?>
                            <?php if($cat['type'] === 'expense'): ?>
                                <div class="bg-white p-3 rounded-xl border border-slate-200 flex justify-between items-center group hover:border-red-300 transition shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="w-6 h-6 rounded-full shadow-inner" style="background-color: <?= $cat['color_hex'] ?>"></div>
                                        <span class="font-medium text-slate-700"><?= htmlspecialchars($cat['name']) ?></span>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Apagar esta categoria?');">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button class="text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition p-1">✕</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>