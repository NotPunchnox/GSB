<?php

if (isset($_GET['login']) && isset($_GET['mdp'])) {
    try {

        $bdd = new PDO("mysql:host=localhost;dbname=gsbV2;charset=utf8", "Admin","AdminSupperSecretPassword");

    } catch(Exception $e) {
        echo "Impossible de se connecter à la base de données.";

        die('Erreur: ' . $e->getMessage());
    }

    $response = $bdd->prepare('SELECT * FROM Visiteur WHERE login = ? AND mdp = ?;');
    $response->execute(array($_GET['login'], $_GET['mdp']));
    $data = $response->fetch();

    echo $data['nom'];
    if ($data['nom'] !== null) {
        header('Location: /GSB/pages/visiteur/form.html');
    }
}


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GSB - Connexion</title>
    <link rel="stylesheet" href="../../public/css/connexion.css">
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <img src="../../public/images/home.png" alt="GSB Pharmacy Office" class="office-image">
        </div>
        <div class="right-panel">
            <h1>Connexion</h1>
            
            <!-- Version Pour PHP: <form class="login-form" method="POST" action=""> -->
            <form class="login-form" method="GET">
                <div class="form-group">
                    <input type="text" name="login" id="email" placeholder="login" required>
                </div>
                <div class="form-group">
                    <input type="password" name="mdp" id="password" placeholder="Password" required>
                </div>
                <div class="form-group checkbox">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="remember">Rester connecter</label>
                </div>
                <button type="submit" class="connect-button">se connecter</button>
            </form>
        </div>
    </div>
</body>
</html>