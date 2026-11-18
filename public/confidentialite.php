<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$contact = conf('contact_email');
$retour = utilisateur_courant() ? 'parties.php' : 'index.php';

page_debut('Confidentialité', 'simple');
?>
<main class="contenu etroit texte">
    <p><a href="<?= $retour ?>">← Retour</a></p>
    <h1>Confidentialité et données personnelles</h1>
    <p><?= e(conf('app_name')) ?> est un projet pédagogique réalisé dans le cadre du BUT Informatique (Université Gustave Eiffel). Cette page explique quelles données nous traitons, pourquoi, et comment exercer vos droits.</p>

    <h2>Données collectées</h2>
    <ul>
        <li><b>Compte :</b> pseudo, adresse e-mail, dates de création, de confirmation et de dernière connexion.</li>
        <li><b>Mot de passe :</b> jamais conservé en clair, seulement son empreinte (Argon2id).</li>
        <li><b>Double authentification, si vous l'activez :</b> la clé, chiffrée, et vos codes de secours, hachés.</li>
        <li><b>Sécurité :</b> pour limiter les tentatives de connexion, une empreinte de votre adresse IP (jamais l'adresse elle-même), conservée 24 heures au plus.</li>
        <li><b>Cookie :</b> un seul cookie de session, indispensable à la connexion. Aucun cookie publicitaire, aucune mesure d'audience, aucune ressource chargée depuis un site tiers.</li>
    </ul>

    <h2>Pourquoi</h2>
    <ul>
        <li>Fournir le service : créer et sécuriser votre compte, vous permettre de jouer (exécution du service).</li>
        <li>Protéger les comptes contre les attaques (intérêt légitime).</li>
    </ul>
    <p>Vos données ne sont ni vendues, ni utilisées à des fins publicitaires.</p>

    <h2>Durées de conservation</h2>
    <ul>
        <li>Compte non confirmé : supprimé après 24 heures.</li>
        <li>Compte sans connexion pendant 3 ans : supprimé automatiquement.</li>
        <li>Liens de confirmation et de réinitialisation : 24 heures et 30 minutes, à usage unique.</li>
        <li>Compteurs de tentatives : 24 heures.</li>
    </ul>

    <h2>Qui y a accès</h2>
    <ul>
        <li><b>Render</b> (hébergement du site et de la base, centre de données de Francfort, Union européenne).</li>
        <li><b>Brevo</b> (envoi des e-mails du site, France) : reçoit votre adresse e-mail et le contenu du message.</li>
        <li><b>Have I Been Pwned</b> : pour vérifier qu'un nouveau mot de passe n'a pas fuité, seuls les 5 premiers caractères de son empreinte sont envoyés. Le mot de passe ne quitte jamais notre serveur.</li>
    </ul>

    <h2>Vos droits</h2>
    <ul>
        <li><b>Accès et portabilité :</b> téléchargez vos données depuis « Mon compte ».</li>
        <li><b>Effacement :</b> supprimez votre compte depuis « Mon compte », la suppression est immédiate.</li>
        <li><b>Rectification, opposition, questions :</b>
            <?php if ($contact !== ''): ?>écrivez à <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.<?php else: ?>contactez l'équipe du projet.<?php endif; ?></li>
        <li>Vous pouvez aussi adresser une réclamation à la <a href="https://www.cnil.fr/fr/plaintes" rel="noopener">CNIL</a>.</li>
    </ul>
</main>
<?php
page_fin();
