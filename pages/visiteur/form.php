<?php
// Désactiver l'affichage des erreurs pour éviter de corrompre la réponse
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

include("../../functions/logs.php");
include("../../functions/create-cookie.php");
include("../../functions/alert.php");
include("../../functions/login.php");
include("../../functions/check-login.php");
include("../../functions/sql-request-insert.php");
include("../../functions/reload.php");

session_start();
logs("Session start");

// Redirection si non connecté
if (!checkLogin()) {
    header('Location: /GSB/');
    exit;
}

// Fonction pour vérifier les éléments requis
function hasRequiredElements($data) {
    $requiredForfaitKeys = ['repas', 'hotel', 'kilometres', 'etape'];
    $requiredVisitorKeys = ['nom', 'prenom', 'id'];

    if (!isset($data['frais_forfait']) || !is_array($data['frais_forfait'])) return false;
    foreach ($requiredForfaitKeys as $key) {
        if (!isset($data['frais_forfait'][$key]['quantite']) || !is_numeric($data['frais_forfait'][$key]['quantite'])) {
            return false;
        }
    }

    foreach ($requiredVisitorKeys as $key) {
        if (!isset($data[$key]) || empty(trim($data[$key]))) return false;
    }

    return true;
}

// Fonction pour filtrer les frais hors forfait valides
function filterValidHorsForfait($horsForfait) {
    $requiredKeys = ['date', 'libelle', 'montant'];
    $validHorsForfait = [];
    
    if (!isset($horsForfait) || !is_array($horsForfait)) return $validHorsForfait;

    foreach ($horsForfait as $frais) {
        $isValid = true;
        foreach ($requiredKeys as $key) {
            if (!isset($frais[$key]) || empty(trim($frais[$key]))) {
                $isValid = false;
                break;
            }
        }
        if ($isValid) {
            $validHorsForfait[] = $frais;
        }
    }
    
    return $validHorsForfait;
}

// Traitement de la soumission POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logs("Début du traitement de la requête POST");


    try {
        if (hasRequiredElements($_POST)) {
            $idVisiteur = $_POST['id'];
            $mois = date('Ym');
            $dateCreation = date('Y-m-d');
            $idEtat = 'CR';

            // Calcul du montant total des frais forfaitisés
            $tarifsForfait = [
                'repas' => 20.00,
                'hotel' => 80.00,
                'kilometres' => 0.62,
                'etape' => 110.00
            ];
            $montantTotal = 0;


            foreach ($_POST['frais_forfait'] as $type => $details) {
                $quantite = floatval($details['quantite']);
                $montantTotal += $quantite * $tarifsForfait[$type];
            }

            // Filtrer et ajouter les frais hors forfait valides
            $validHorsForfait = filterValidHorsForfait($_POST['frais_hors_forfait']);
            foreach ($validHorsForfait as $frais) {
                $montantTotal += floatval($frais['montant']);
            }

            if($montantTotal <= 0) {
                alert('Impossible de sauvegarder vos fiches. Le montant est égale à 0.');
                reload('form', '/GSB/pages/visiteur/form.php');
            }

            // Insertion dans NoteFrais
            $sqlNoteFrais = "INSERT INTO NoteFrais (idVisiteur, mois, montantTotal, dateCreation, idEtat) VALUES (?, ?, ?, ?, ?)";
            $paramsNoteFrais = [$idVisiteur, $mois, $montantTotal, $dateCreation, $idEtat];
            logs("Requête NoteFrais : " . $sqlNoteFrais . " avec params " . json_encode($paramsNoteFrais));
            RequestSqlInsert($sqlNoteFrais, $paramsNoteFrais);

            logs("test");

            // Insertion des frais hors forfait valides
            if (!empty($validHorsForfait)) {
                $sqlHorsForfait = "INSERT INTO LigneFraisHorsForfait (idVisiteur, mois, libelle, date, montant) 
                                   VALUES (?, ?, ?, ?, ?)";
                foreach ($validHorsForfait as $index => $frais) {
                    $paramsHorsForfait = [
                        $idVisiteur,
                        $mois,
                        $frais['libelle'],
                        $frais['date'],
                        floatval($frais['montant'])
                    ];
                    logs("Requête LigneFraisHorsForfait #$index : " . $sqlHorsForfait . " avec params " . json_encode($paramsHorsForfait));
                    RequestSqlInsert($sqlHorsForfait, $paramsHorsForfait);
                }
            } else {
                logs("Aucun frais hors forfait valide à insérer.");
            }

            // Redirection après succès
            // header('Location: list.php');
            exit;
        } else {
            logs("Erreur : Éléments requis manquants dans la requête : " . json_encode($_POST));
        }
    } catch (Exception $e) {
        logs("Erreur lors du traitement : " . $e->getMessage());
    }

    logs("Fin du traitement de la requête POST");
}
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

            const li = document.createElement("li");
            li.textContent = `${date} - ${libelle}: ${montant} €`;
            document.getElementById("hors-forfait-list").appendChild(li);

            const form = document.getElementById("fraisForm");
            form.insertAdjacentHTML('beforeend', `
                <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][date]" value="${date}">
                <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][libelle]" value="${libelle}">
                <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][montant]" value="${montant}">
            `);
            horsForfaitIndex++;

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