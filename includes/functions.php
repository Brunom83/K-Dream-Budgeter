<?php
// includes/functions.php

function getEurToKrwRate($pdo) {
    // 1. Verifica se temos uma taxa guardada na BD com menos de 1 hora (Cache)
    // Isto poupa a API e faz o site voar.
    $stmt = $pdo->prepare("SELECT rate, fetched_at FROM exchange_rates ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $lastRate = $stmt->fetch();

    if ($lastRate) {
        $lastFetch = new DateTime($lastRate['fetched_at']);
        $now = new DateTime();
        $interval = $now->diff($lastFetch);

        // Se foi há menos de 1 hora, usa o valor da BD
        if ($interval->h < 1 && $interval->days == 0) {
            return $lastRate['rate'];
        }
    }

    // 2. Se não temos cache recente, vamos à API externa
    // API Free: frankfurter.app (Não precisa de chave API, é top para MVPs)
    try {
        $json = file_get_contents('https://api.frankfurter.app/latest?from=EUR&to=KRW');
        $data = json_decode($json, true);
        
        if (isset($data['rates']['KRW'])) {
            $rate = $data['rates']['KRW'];

            // Guarda na BD para a próxima vez
            $stmt = $pdo->prepare("INSERT INTO exchange_rates (rate) VALUES (?)");
            $stmt->execute([$rate]);

            return $rate;
        }
    } catch (Exception $e) {
        // Se a API falhar (sem net, etc), não entres em pânico.
    }

    // 3. Plano C: Se tudo falhar, usa a última da BD ou um valor fixo aproximado
    return $lastRate ? $lastRate['rate'] : 1450.00; 
}

function logActivity($pdo, $group_id, $user_id, $message, $type = 'info') {
    // Se não houver grupo, não registamos logs de grupo (ou podes adaptar para logs pessoais)
    if (!$group_id) return;

    $stmt = $pdo->prepare("INSERT INTO activity_logs (group_id, user_id, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$group_id, $user_id, $message, $type]);
}
?>