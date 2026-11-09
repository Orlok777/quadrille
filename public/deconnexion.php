<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// Uniquement en POST avec jeton CSRF : un simple lien ou une image piégée ne peut pas déconnecter.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valide()) {
    fermer_session();
    flash('Vous êtes déconnecté.');
}
rediriger('index.php');
