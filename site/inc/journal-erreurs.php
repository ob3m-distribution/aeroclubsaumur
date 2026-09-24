<?php
declare(strict_types=1);

/* ==================================================================
   Journal des erreurs PHP dans un fichier lisible par SFTP.

   Sans ce réglage, error_log() part dans le log d'erreurs Apache
   d'IONOS, inaccessible au client. On écrit donc dans
   <dossier parent du site>/logs-php/<nom du dossier du site>.log :
     - HORS du dossier du site, qui est remplacé à chaque déploiement
       (voir deploy_ci.py) — un log placé dedans partirait dans _old ;
     - hors de la racine web du domaine, donc jamais servi en HTTP.
   Récupéré et vidé chaque jour par logs_ci.py (.github/workflows/logs.yml).
   ================================================================== */

(static function (): void {
    $dossierSite = dirname(__DIR__);
    $dossierLogs = dirname($dossierSite) . '/logs-php';

    if (!is_dir($dossierLogs)) {
        if (!@mkdir($dossierLogs, 0750) && !is_dir($dossierLogs)) {
            return;   // pas de dossier : on garde le comportement par défaut
        }
        // Ceinture et bretelles, au cas où un domaine pointerait un jour
        // sur le dossier parent.
        @file_put_contents($dossierLogs . '/.htaccess', "Require all denied\n");
    }

    ini_set('log_errors', '1');
    ini_set('error_log', $dossierLogs . '/' . basename($dossierSite) . '.log');
})();
