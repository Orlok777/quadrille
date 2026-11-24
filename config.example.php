<?php
// Copier ce fichier en config.php (jamais versionné) puis adapter les valeurs.
return [
    'app_name'  => 'Quadrille',

    // URL publique, sans slash final. Sert à construire les liens envoyés par e-mail :
    // on ne se fie jamais à l'en-tête Host, falsifiable par le client.
    // Si elle commence par https://, le cookie de session passe en Secure et HSTS est activé.
    'app_url'   => 'http://localhost:8000',

    // Base PostgreSQL (obligatoire). En ligne, elle est fournie par la variable DATABASE_URL.
    'database_url' => 'postgresql://quadrille:quadrille@localhost:5432/quadrille',

    // 'mail' : envoi réel via mail() (en ligne).
    // 'log'  : écrit le message dans data/mails.log (développement uniquement).
    'mail_mode' => 'log',
    'mail_from' => 'no-reply@example.org',

    // Adresse affichée sur la page de confidentialité pour exercer ses droits (facultatif).
    'contact_email' => '',

    // Générer avec : php -r "echo bin2hex(random_bytes(32));"
    'secret'    => 'A_REMPLACER',
];
