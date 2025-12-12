<?php
// logout.php
session_start();
session_destroy(); // Destroi todas as variáveis de sessão (limpa tudo)
header('Location: login.php'); // Manda de volta para o login
exit;