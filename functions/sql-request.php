<?php include('./logs.php'); ?>

<?php
    function RequestSQL($request) {
        // Tentative de connexion à la base de donnée
        try {
            $bdd = new PDO("mysql:host=localhost;dbname=gsbV2;charset=utf8", "Admin","AdminSupperSecretPassword");
        } catch(Exception $e) {
            // En cas d'erreur lors de la connexion avertir l'utilisateur qu'un problème l'empêche d'acceder à la web app
            echo "Impossible de se connecter à la base de données.";
            die('Erreur: ' . $e->getMessage());
        }

        // Préparer la requête SQL avec prepare pour éviter les injections SQL
        $response = $bdd->prepare($request);
        $response->execute();
        $data = $response->fetch();

        logs($data, true);

        return $data;
    }

?>