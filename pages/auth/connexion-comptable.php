<?php
// Création d'une fonction permettant d'avertir l'utilisateur avec une alerte
function alert(string $message): void {
    echo "<script>alert('" . $message . "')</script>";
}


// Vérifier si les valeurs on été set
// Méthode post pour éviter que les identifiants soient présent dans l'url
if (isset($_POST['login']) && isset($_POST['mdp'])) {
    
    // Tentative de connexion à la base de donnée
    try {

        $bdd = new PDO("mysql:host=localhost;dbname=gsbV2;charset=utf8", "Admin","AdminSupperSecretPassword");

    } catch(Exception $e) {
        // En cas d'erreur lors de la connexion avertir l'utilisateur qu'un problème l'empêche d'acceder à la web app
        echo "Impossible de se connecter à la base de données.";
        die('Erreur: ' . $e->getMessage());
    }

    // Préparer la requête SQL avec prepare pour éviter les injections SQL
    $response = $bdd->prepare('SELECT * FROM Visiteur WHERE login = ? AND mdp = ?;');
    $response->execute(array($_POST['login'], $_POST['mdp']));
    $data = $response->fetch();

    if ($data['nom'] !== null) {
        // Si l'utilisateur existe alors effectuer la redirection.
        header('Location: /GSB/pages/comptable/dashboard.html');
    } else {
        // Si l'utilisateur n'est pas trouvé, alors les retourner un message d'erreur
        alert('Vos identifiants sont invalides.');
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
            <form class="login-form" method="POST">
                <div class="form-group">
                    <input type="text" name="login" id="email" placeholder="Email" required>
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