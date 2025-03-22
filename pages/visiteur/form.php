<?php
session_start();

// Include functions
include("../../functions/index.php");

// Redirect if not logged in
if (!checkLogin()) {
    header('Location: ' . $GLOBALS['baseURL']);
    exit;
}

// Mapping and tariffs for forfaitized expenses (aligned with DB)
$fraisForfaitMapping = [
    'etape' => 'ETP',
    'kilometres' => 'KM',
    'hotel' => 'NUI',
    'repas' => 'REP'
];
$tarifsForfait = [
    'ETP' => 110.00,
    'KM' => 0.62,
    'NUI' => 80.00,
    'REP' => 25.00 // Aligned with FraisForfait table
];

// Validate required POST data
function hasRequiredElements($data) {
    $requiredKeys = ['nom', 'prenom', 'id'];
    foreach ($requiredKeys as $key) {
        if (empty(trim($data[$key] ?? ''))) return false;
    }
    $fraisForfait = $data['frais_forfait'] ?? [];
    $fraisHorsForfait = $data['frais_hors_forfait'] ?? [];
    return !empty(array_filter($fraisForfait, fn($f) => ($f['quantite'] ?? 0) > 0)) || !empty(filterValidHorsForfait($fraisHorsForfait));
}

// Filter valid hors forfait expenses
function filterValidHorsForfait($horsForfait) {
    if (!is_array($horsForfait)) return [];
    $requiredKeys = ['date', 'libelle', 'montant'];
    return array_filter($horsForfait, function ($frais) use ($requiredKeys) {
        foreach ($requiredKeys as $key) {
            if (empty(trim($frais[$key] ?? ''))) return false;
        }
        return is_numeric($frais['montant']) && floatval($frais['montant']) > 0;
    });
}

// Process POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRequiredElements($_POST)) {
    try {
        $idVisiteur = $_POST['id'];
        $mois = date('Ym');
        $dateCreation = date('Y-m-d');
        $idEtat = 'CR';

        // Calculate forfaitized expenses
        $montantTotalForfait = 0;
        $fraisForfaitData = $_POST['frais_forfait'] ?? [];
        foreach ($fraisForfaitData as $type => $details) {
            if (isset($fraisForfaitMapping[$type]) && ($quantite = floatval($details['quantite'] ?? 0)) > 0) {
                $montantTotalForfait += $quantite * $tarifsForfait[$fraisForfaitMapping[$type]];
            }
        }

        // Calculate hors forfait expenses
        $validHorsForfait = filterValidHorsForfait($_POST['frais_hors_forfait'] ?? []);
        $montantTotalHorsForfait = array_sum(array_column($validHorsForfait, 'montant'));

        $montantTotal = $montantTotalForfait + $montantTotalHorsForfait;
        if ($montantTotal <= 0) throw new Exception("Montant total doit être supérieur à 0");

        // Insert or update FicheFrais
        $sqlFicheFrais = "INSERT INTO FicheFrais (idVisiteur, mois, nbJustificatifs, montantValide, dateModif, idEtat) 
                          VALUES (?, ?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE nbJustificatifs = ?, montantValide = ?, dateModif = ?";
        $nbJustificatifs = count($validHorsForfait);
        RequestSqlInsert($sqlFicheFrais, [$idVisiteur, $mois, $nbJustificatifs, $montantTotal, $dateCreation, $idEtat, $nbJustificatifs, $montantTotal, $dateCreation]);

        // Clear and insert forfaitized expenses
        RequestSqlInsert("DELETE FROM LigneFraisForfait WHERE idVisiteur = ? AND mois = ?", [$idVisiteur, $mois]);
        foreach ($fraisForfaitData as $type => $details) {
            if (isset($fraisForfaitMapping[$type]) && ($quantite = intval($details['quantite'] ?? 0)) > 0) {
                $sqlLigneForfait = "INSERT INTO LigneFraisForfait (idVisiteur, mois, idFraisForfait, quantite) VALUES (?, ?, ?, ?)";
                RequestSqlInsert($sqlLigneForfait, [$idVisiteur, $mois, $fraisForfaitMapping[$type], $quantite]);
            }
        }

        // Insert hors forfait expenses
        foreach ($validHorsForfait as $frais) {
            $sqlHorsForfait = "INSERT INTO LigneFraisHorsForfait (idVisiteur, mois, libelle, date, montant) VALUES (?, ?, ?, ?, ?)";
            RequestSqlInsert($sqlHorsForfait, [$idVisiteur, $mois, $frais['libelle'], $frais['date'], floatval($frais['montant'])]);
        }

        // Insert or update NoteFrais
        $sqlNoteFrais = "INSERT INTO NoteFrais (idVisiteur, mois, montantTotal, dateCreation, idEtat) 
                         VALUES (?, ?, ?, ?, ?) 
                         ON DUPLICATE KEY UPDATE montantTotal = ?, dateModif = CURRENT_TIMESTAMP";
        RequestSqlInsert($sqlNoteFrais, [$idVisiteur, $mois, $montantTotal, $dateCreation, $idEtat, $montantTotal]);

        alert('Fiche de frais sauvegardée avec succès!');
        reload("Renseigner Fiche Frais", $GLOBALS['baseURL'] . "pages/visiteur/form.php");
        exit;
    } catch (Exception $e) {
        alert('Erreur: ' . $e->getMessage());
    }
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
        <a href="<?php echo $GLOBALS['baseURL']; ?>pages/auth/logout.php">
            <img class="svg" src="../../public/images/logout.svg" alt="logout">
        </a>
    </nav>

    <div class="container">
        <h2>Renseigner une fiche frais</h2>
        <form method="POST" id="fraisForm">
            <div class="card">
                <h3>Informations visiteur</h3>
                <div class="grid">
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" name="nom" value="<?php echo htmlspecialchars($GLOBALS['nom']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" value="<?php echo htmlspecialchars($GLOBALS['prenom']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Matricule</label>
                        <input type="text" name="id" value="<?php echo htmlspecialchars($GLOBALS['id']); ?>" readonly>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Frais hors forfait</h3>
                <div class="grid">
                    <input type="date" id="date" placeholder="Date d'engagement">
                    <input type="text" id="libelle" placeholder="Libellé">
                    <input type="number" id="montant" placeholder="Montant" step="0.01" min="0.01">
                    <button type="button" class="btn btn-blue" id="ajouter-frais">Ajouter</button>
                </div>
                <ul id="hors-forfait-list"></ul>
            </div>

            <div class="card">
                <h3>Frais forfaitisés</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th>Quantité</th>
                            <th>Montant unitaire</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Repas restaurant</td>
                            <td><input type="number" name="frais_forfait[repas][quantite]" class="quantite" data-prix="25.00" value="0" min="0"></td>
                            <td>25.00 €</td>
                            <td class="total">0 €</td>
                        </tr>
                        <tr>
                            <td>Nuitées hôtel</td>
                            <td><input type="number" name="frais_forfait[hotel][quantite]" class="quantite" data-prix="80.00" value="0" min="0"></td>
                            <td>80.00 €</td>
                            <td class="total">0 €</td>
                        </tr>
                        <tr>
                            <td>Frais kilométriques</td>
                            <td><input type="number" name="frais_forfait[kilometres][quantite]" class="quantite" data-prix="0.62" value="0" min="0"></td>
                            <td>0.62 €</td>
                            <td class="total">0 €</td>
                        </tr>
                        <tr>
                            <td>Forfait Etape</td>
                            <td><input type="number" name="frais_forfait[etape][quantite]" class="quantite" data-prix="110.00" value="0" min="0"></td>
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
        let horsForfaitIndex = 0;
        document.getElementById("ajouter-frais").addEventListener("click", () => {
            const date = document.getElementById("date").value;
            const libelle = document.getElementById("libelle").value;
            const montant = document.getElementById("montant").value;
            const erreurs = [];

            if (!date) erreurs.push("Date requise");
            if (!libelle) erreurs.push("Libellé requis");
            if (!montant || parseFloat(montant) <= 0) erreurs.push("Montant invalide");

            const dateEngagement = new Date(date);
            const dateActuelle = new Date();
            const dateMin = new Date(dateActuelle.setFullYear(dateActuelle.getFullYear() - 1));
            if (dateEngagement > new Date()) erreurs.push("Date dans le futur");
            if (dateEngagement < dateMin) erreurs.push("Date trop ancienne");

            if (erreurs.length) return alert(erreurs.join("\n"));

            const li = document.createElement("li");
            li.innerHTML = `${date} - ${libelle}: ${parseFloat(montant).toFixed(2)} € <button class="btn btn-red delete-frais" data-index="${horsForfaitIndex}">Supprimer</button>`;
            document.getElementById("hors-forfait-list").appendChild(li);

            document.getElementById("fraisForm").insertAdjacentHTML('beforeend', `
                <div id="frais-hf-${horsForfaitIndex}">
                    <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][date]" value="${date}">
                    <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][libelle]" value="${libelle}">
                    <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][montant]" value="${montant}">
                </div>
            `);

            li.querySelector(".delete-frais").addEventListener("click", () => {
                if (confirm("Supprimer ce frais?")) {
                    li.remove();
                    document.getElementById(`frais-hf-${horsForfaitIndex}`).remove();
                }
            });

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

        document.getElementById("fraisForm").addEventListener("submit", e => {
            const hasFrais = [...document.querySelectorAll(".quantite")].some(i => parseFloat(i.value) > 0) || 
                             document.querySelectorAll("#hors-forfait-list li").length > 0;
            if (!hasFrais) {
                e.preventDefault();
                alert("Saisissez au moins un frais.");
            } else {
                const btn = document.getElementById("valider-frais");
                btn.textContent = "Traitement...";
                btn.disabled = true;
            }
        });
    </script>
</body>
</html>