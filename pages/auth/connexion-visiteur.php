<?php
// Démarrer la session
session_start();

// Inclure les fonctions
include("../../functions/index.php");


// Vérifier si l'utilisateur est déjà connecté, si oui le rediriger vers le dashboard
if(checkLogin()) {
    header('Location: ' . $GLOBALS['baseURL'] . 'pages/visiteur/form.php');
}

// Vérifier si les valeurs on été set
// Méthode post pour éviter que les identifiants soient présent dans l'url
if (isset($_POST['login']) && isset($_POST['mdp'])) {
    
    $data = Login(htmlspecialchars($_POST['login']), htmlspecialchars($_POST['mdp']));

    logs($data, true);

    // Vérifier si l'utilisateur existe
    if (isset($data['nom'])) {

        // logs($data, true);

        // initialisation de la session
        $_SESSION['nom'] = $data['nom'];
        $_SESSION['prenom'] = $data['prenom'];
        $_SESSION['id'] = $data['id'];
        $_SESSION['mdp'] = $data['mdp'];
        $_SESSION['login'] = $data['login'];


        // Créer un cookie à l'utilisateur si il demande à rester connecter
        if(isset($_POST['remember']) && $_POST['remember'] == true) {
            createCookie("GSB", json_encode($data), 1);
        }

        // Effectuer la redirection vers le dashboard.
        header('Location: ' . $GLOBALS['baseURL'] . 'pages/visiteur/form.php');
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