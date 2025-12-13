<?php
// categories.php
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
            $msg = "⚠️ Essa categoria já existe.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, type, color_hex) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $type, $color])) {
                $msg = "✅ Categoria criada!";
            } else {
                $msg = "❌ Erro ao criar.";
            }
        }
    }
}

// APAGAR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    $cat_id = $_POST['category_id'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    if ($stmt->execute([$cat_id])) {
        $msg = "🗑️ Categoria eliminada.";
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY type, name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Categorias - K-Dream</title>
    <link rel="icon" href="https://fav.farm/🇰🇷" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-900 text-white min-h-screen pb-20">
    <nav class="bg-gray-800 border-b border-gray-700 p-4">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <h1 class="font-black text-xl text-blue-500">K-DREAM 🇰🇷</h1>
            <a href="index.php" class="text-gray-400 hover:text-white transition">⬅ Voltar</a>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto p-6 mt-4 space-y-8">
        <div class="flex justify-between items-end">
            <h2 class="text-3xl font-bold">🏷️ Categorias</h2>
        </div>

        <?php if($msg): ?>
            <div class="bg-blue-600/20 text-blue-200 p-3 rounded border border-blue-500/50 text-center animate-bounce">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-1">
                <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 sticky top-24">
                    <h3 class="font-bold text-lg mb-4 text-purple-400">Nova Categoria</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_category">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Nome</label>
                            <input type="text" name="name" placeholder="Ex: K-Pop" required class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Tipo</label>
                            <select name="type" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white">
                                <option value="expense">📉 Despesa</option>
                                <option value="income">📈 Receita</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Cor</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="color" value="#a855f7" class="h-10 w-full bg-transparent cursor-pointer rounded">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-bold py-2 rounded transition">Criar</button>
                    </form>
                </div>
            </div>

            <div class="md:col-span-2 space-y-6">
                <div>
                    <h3 class="font-bold text-green-400 mb-3 text-sm uppercase tracking-wider">Receitas</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach($categories as $cat): ?>
                            <?php if($cat['type'] === 'income'): ?>
                                <div class="bg-gray-800 p-3 rounded-lg border border-gray-700 flex justify-between items-center group hover:border-gray-500 transition">
                                    <div class="flex items-center gap-3">
                                        <div class="w-4 h-4 rounded-full" style="background-color: <?= $cat['color_hex'] ?>"></div>
                                        <span class="font-medium"><?= htmlspecialchars($cat['name']) ?></span>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Apagar?');">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button class="text-gray-600 hover:text-red-500 opacity-0 group-hover:opacity-100 transition p-1">✕</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold text-red-400 mb-3 text-sm uppercase tracking-wider">Despesas</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach($categories as $cat): ?>
                            <?php if($cat['type'] === 'expense'): ?>
                                <div class="bg-gray-800 p-3 rounded-lg border border-gray-700 flex justify-between items-center group hover:border-gray-500 transition">
                                    <div class="flex items-center gap-3">
                                        <div class="w-4 h-4 rounded-full" style="background-color: <?= $cat['color_hex'] ?>"></div>
                                        <span class="font-medium"><?= htmlspecialchars($cat['name']) ?></span>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Apagar?');">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button class="text-gray-600 hover:text-red-500 opacity-0 group-hover:opacity-100 transition p-1">✕</button>
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