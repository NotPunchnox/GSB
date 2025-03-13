<?php
include("../../functions/logs.php");
include("../../functions/create-cookie.php");
include("../../functions/alert.php");
include("../../functions/login.php");
include("../../functions/check-login.php");
include("../../functions/sql-request.php");

session_start();
logs("Session start");

// Redirection si non connecté
if (!checkLogin()) {
    header('Location: /GSB/');
    exit;
}

// Fonction pour vérifier si tous les éléments requis sont présents
function hasRequiredElements($data) {
    $requiredForfaitKeys = ['repas', 'hotel', 'kilometres', 'etape'];
    $requiredHorsForfaitKeys = ['date', 'libelle', 'montant'];
    $requiredVisitorKeys = ['nom', 'prenom', 'id'];

    // Vérification des frais forfaitisés
    if (!isset($data['frais_forfait']) || !is_array($data['frais_forfait'])) {
        return false;
    }
    foreach ($requiredForfaitKeys as $key) {
        if (!isset($data['frais_forfait'][$key]['quantite']) || 
            !is_numeric($data['frais_forfait'][$key]['quantite'])) {
            return false;
        }
    }

    // Vérification des frais hors forfait (au moins un élément requis)
    if (!isset($data['frais_hors_forfait']) || !is_array($data['frais_hors_forfait']) || empty($data['frais_hors_forfait'])) {
        return false;
    }
    foreach ($data['frais_hors_forfait'] as $frais) {
        foreach ($requiredHorsForfaitKeys as $key) {
            if (!isset($frais[$key]) || empty(trim($frais[$key]))) {
                return false;
            }
        }
    }

    // Vérification des informations visiteur
    foreach ($requiredVisitorKeys as $key) {
        if (!isset($data[$key]) || empty(trim($data[$key]))) {
            return false;
        }
    }

    return true;
}

// Traitement de la soumission POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logs("Début du traitement de la requête POST");

    // Vérifier si tous les éléments requis sont présents
    if (hasRequiredElements($_POST)) {
        logs("Tous les éléments requis sont présents. Affichage des détails :");

        // Log des frais forfaitisés
        logs("Frais forfaitisés :");
        $fraisForfait = $_POST['frais_forfait'];
        foreach ($fraisForfait as $type => $details) {
            logs(" - $type : Quantité = {$details['quantite']}");
        }

        // Log des frais hors forfait
        logs("Frais hors forfait :");
        foreach ($_POST['frais_hors_forfait'] as $index => $frais) {
            logs(" - #$index : Date = {$frais['date']}, Libellé = {$frais['libelle']}, Montant = {$frais['montant']}");
        }

        // Log des informations visiteur
        logs("Informations visiteur :");
        logs(" - Nom = {$_POST['nom']}, Prénom = {$_POST['prenom']}, Matricule = {$_POST['id']}");

    } else {
        logs("Erreur : Tous les éléments requis ne sont pas présents dans la requête.");
        logs("Contenu reçu : " . json_encode($_POST));
    }

    logs("Fin du traitement de la requête POST");
}

// Le reste de votre code HTML/PHP ici...
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renseigner Fiche Frais</title>
    <link rel="stylesheet" href="../../public/css/visiteur.css">
</head>
<body>
    <nav>
        <div>
            <a href="form.php" class="active">Renseigner Fiche Frais</a>
            <a href="list.php">Consulter Fiche Frais</a>
        </div>
        <img class="svg" src="../../public/images/logout.svg" alt="logout">
    </nav>

    <div class="container">
        <h2 class="mb-4">Renseigner une fiche frais</h2>
        
        <form method="POST" id="fraisForm">
            <!-- Informations visiteur -->
            <div class="card">
                <h3>Informations visiteur</h3>
                <div class="grid">
                    <div class="form-group">
                        <label>Nom</label>
                        <?php echo "<input type=\"text\" name=\"nom\" value=\"" . htmlspecialchars($GLOBALS['nom']) . "\" readonly>"; ?>
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <?php echo "<input type=\"text\" name=\"prenom\" value=\"" . htmlspecialchars($GLOBALS['prenom']) . "\" readonly>"; ?>
                    </div>
                    <div class="form-group">
                        <label>Matricule</label>
                        <?php echo "<input type=\"text\" name=\"id\" value=\"" . htmlspecialchars($GLOBALS['id']) . "\" readonly>"; ?>
                    </div>
                </div>
            </div>

            <!-- Frais hors forfait -->
            <div class="card">
                <h3>Frais hors forfait</h3>
                <div class="grid">
                    <input type="date" id="date" name="frais_hors_forfait[0][date]">
                    <input type="text" id="libelle" name="frais_hors_forfait[0][libelle]" placeholder="Libellé">
                    <input type="number" id="montant" name="frais_hors_forfait[0][montant]" placeholder="Montant" step="0.01">
                    <button type="button" class="btn btn-blue" id="ajouter-frais">Ajouter</button>
                </div>
                <ul id="hors-forfait-list"></ul>
            </div>

            <!-- Frais forfaitisés -->
            <div class="card">
                <h3>Frais forfaitisés</h3>
                <table>
                    <thead>
                        <tr>
                            <th scope="row">Libellé</th>
                            <th scope="row">Quantité</th>
                            <th scope="row">Montant unitaire</th>
                            <th scope="row">Total</th>
                        </tr>
                    </thead>
                    <tbody id="forfaitises">
                        <tr>
                            <td>Repas restaurant</td>
                            <td><input type="number" name="frais_forfait[repas][quantite]" class="quantite" data-prix="20.00" value="0"></td>
                            <td>20.00 €</td>
                            <td class="total">0 €</td>
                        </tr>
                        <tr>
                            <td>Nuitées hôtel</td>
                            <td><input type="number" name="frais_forfait[hotel][quantite]" class="quantite" data-prix="80.00" value="0"></td>
                            <td>80.00 €</td>
                            <td class="total">0 €</td>
                        </tr>
                        <tr>
                            <td>Frais kilométriques</td>
                            <td><input type="number" name="frais_forfait[kilometres][quantite]" class="quantite" data-prix="0.62" value="0"></td>
                            <td>0.62 €</td>
                            <td class="total">0 €</td>
                        </tr>
                        <tr>
                            <td>Forfait Etape</td>
                            <td><input type="number" name="frais_forfait[etape][quantite]" class="quantite" data-prix="110.00" value="0"></td>
                            <td>110.00 €</td>
                            <td class="total">0 €</td>
                        </tr>
                    </tbody>
                </table>
                <div class="validate">
                    <button type="submit" class="btn btn-green" id="valider-frais">Valider</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        let horsForfaitIndex = 1;

        document.getElementById("ajouter-frais").addEventListener("click", () => {
            const date = document.getElementById("date").value;
            const libelle = document.getElementById("libelle").value;
            const montant = document.getElementById("montant").value;
            const erreurs = [];

            if (!date) erreurs.push("Le champ date doit être renseigné");
            if (!libelle) erreurs.push("Le champ libellé doit être renseigné");
            if (!montant || isNaN(montant)) erreurs.push("Le champ montant doit être valide");

            const dateEngagement = new Date(date);
            const dateMax = new Date();
            dateMax.setFullYear(dateMax.getFullYear() - 1);

            if (dateEngagement > new Date()) erreurs.push("La date d'engagement doit être valide");
            if (dateEngagement < dateMax) erreurs.push("La date d'engagement doit se situer dans l’année écoulée");

            if (erreurs.length > 0) {
                alert(erreurs.join("\n"));
                return;
            }

            // Ajouter à la liste visuelle
            const li = document.createElement("li");
            li.textContent = `${date} - ${libelle}: ${montant} €`;
            document.getElementById("hors-forfait-list").appendChild(li);

            // Ajouter les champs cachés au formulaire
            const form = document.getElementById("fraisForm");
            form.insertAdjacentHTML('beforeend', `
                <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][date]" value="${date}">
                <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][libelle]" value="${libelle}">
                <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][montant]" value="${montant}">
            `);
            horsForfaitIndex++;

            // Réinitialiser les champs
            document.getElementById("date").value = "";
            document.getElementById("libelle").value = "";
            document.getElementById("montant").value = "";
        });

        document.querySelectorAll(".quantite").forEach(input => {
            input.addEventListener("input", () => {
                const prix = parseFloat(input.dataset.prix);
                const quantite = parseFloat(input.value) || 0;
                input.closest("tr").querySelector(".total").textContent = `${(prix * quantite).toFixed(2)} €`;
            });
        });
    </script>
</body>
</html>