<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
deconnecter();
header('Location: /admin/connexion.php', true, 302);
exit;
