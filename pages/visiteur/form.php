<?php
session_start();

// Inclure les fonctions
include("../../functions/index.php");

// Redirection si non connecté
if (!checkLogin()) {
    header('Location: /GSB/');
    exit;
}

// Fonction pour vérifier les éléments requis
function hasRequiredElements($data) {
    $requiredVisitorKeys = ['nom', 'prenom', 'id'];

    if (!isset($data['frais_forfait']) || !is_array($data['frais_forfait'])) {
        logs("Frais forfait non défini ou pas un tableau");
        return false;
    }

    // Vérifier si au moins une quantité est > 0 ou s'il y a au moins un frais hors forfait valide
    $hasPositiveQuantity = false;
    foreach ($data['frais_forfait'] as $details) {
        if (isset($details['quantite']) && is_numeric($details['quantite']) && floatval($details['quantite']) > 0) {
            $hasPositiveQuantity = true;
            break;
        }
    }

    $hasHorsForfait = isset($data['frais_hors_forfait']) && is_array($data['frais_hors_forfait']) && count(filterValidHorsForfait($data['frais_hors_forfait'])) > 0;

    if (!$hasPositiveQuantity && !$hasHorsForfait) {
        logs("Aucune quantité positive ni frais hors forfait valide");
        return false;
    }

    foreach ($requiredVisitorKeys as $key) {
        if (!isset($data[$key]) || empty(trim($data[$key]))) {
            logs("Clé requise manquante: " . $key);
            return false;
        }
    }

    return true;
}

// Fonction pour filtrer les frais hors forfait valides
function filterValidHorsForfait($horsForfait) {
    $requiredKeys = ['date', 'libelle', 'montant'];
    $validHorsForfait = [];
    
    if (!isset($horsForfait) || !is_array($horsForfait)) {
        logs("Hors forfait non défini ou pas un tableau");
        return $validHorsForfait;
    }

    foreach ($horsForfait as $frais) {
        $isValid = true;
        foreach ($requiredKeys as $key) {
            if (!isset($frais[$key]) || empty(trim($frais[$key]))) {
                $isValid = false;
                logs("Clé manquante dans hors forfait: " . $key);
                break;
            }
        }
        
        // Vérification supplémentaire pour le montant
        if ($isValid && (!is_numeric($frais['montant']) || floatval($frais['montant']) <= 0)) {
            $isValid = false;
            logs("Montant invalide: " . $frais['montant']);
        }
        
        if ($isValid) {
            $validHorsForfait[] = $frais;
        }
    }
    
    logs("Frais hors forfait valides trouvés: " . count($validHorsForfait));
    return $validHorsForfait;
}

// Mapping des types de frais forfait
$fraisForfaitMapping = [
    'etape' => 'ETP',
    'kilometres' => 'KM',
    'hotel' => 'NUI',
    'repas' => 'REP'
];

// Tarifs des frais forfaitisés
$tarifsForfait = [
    'ETP' => 110.00,
    'KM' => 0.62,
    'NUI' => 80.00,
    'REP' => 20.00
];

// Traitement de la soumission POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logs("Début du traitement de la requête POST: " . json_encode($_POST));

    try {
        // Validation des données soumises
        if (hasRequiredElements($_POST)) {
            $idVisiteur = $_POST['id'];
            $mois = date('Ym');
            $dateCreation = date('Y-m-d');
            $idEtat = 'CR'; // État "Créé"

            // Calcul du montant total des frais forfaitisés
            $montantTotalForfait = 0;
            foreach ($_POST['frais_forfait'] as $type => $details) {
                if (isset($fraisForfaitMapping[$type])) {
                    $idFraisForfait = $fraisForfaitMapping[$type];
                    $quantite = floatval($details['quantite']);
                    $montantTotalForfait += $quantite * $tarifsForfait[$idFraisForfait];
                }
            }

            // Filtrer et ajouter les frais hors forfait valides
            $validHorsForfait = filterValidHorsForfait($_POST['frais_hors_forfait'] ?? []);
            $montantTotalHorsForfait = 0;
            foreach ($validHorsForfait as $frais) {
                $montantTotalHorsForfait += floatval($frais['montant']);
            }

            $montantTotal = $montantTotalForfait + $montantTotalHorsForfait;
            logs("Montant total calculé: " . $montantTotal);

            if($montantTotal <= 0) {
                alert('Impossible de sauvegarder votre fiche. Le montant total est égal à 0.');
                reload('form', '/GSB/pages/visiteur/form.php');
                exit;
            }

            // Vérifier si une fiche existe déjà pour ce mois et créer ou mettre à jour
            $sqlCheckFiche = "SELECT idVisiteur, mois FROM FicheFrais WHERE idVisiteur = ? AND mois = ?";
            
            // Faire une insertion ou mise à jour directe sans vérifier explicitement l'existence
            $sqlFicheFrais = "INSERT INTO FicheFrais (idVisiteur, mois, nbJustificatifs, montantValide, dateModif, idEtat) 
                              VALUES (?, ?, ?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE 
                              nbJustificatifs = VALUES(nbJustificatifs), 
                              montantValide = VALUES(montantValide), 
                              dateModif = VALUES(dateModif)";
            
            $nbJustificatifs = count($validHorsForfait);
            $paramsFicheFrais = [
                $idVisiteur, 
                $mois, 
                $nbJustificatifs, 
                $montantTotal, 
                $dateCreation, 
                $idEtat
            ];
            
            logs("Requête FicheFrais: " . $sqlFicheFrais . " avec params " . json_encode($paramsFicheFrais));
            $resultFicheFrais = RequestSqlInsert($sqlFicheFrais, $paramsFicheFrais);
            logs("Résultat FicheFrais: " . json_encode($resultFicheFrais));

            // 2. Supprimer les anciennes lignes de frais forfaitisés pour ce mois
            $sqlDeleteLigneForfait = "DELETE FROM LigneFraisForfait WHERE idVisiteur = ? AND mois = ?";
            $paramsDeleteLigneForfait = [$idVisiteur, $mois];
            logs("Suppression LigneFraisForfait: " . json_encode($paramsDeleteLigneForfait));
            RequestSqlInsert($sqlDeleteLigneForfait, $paramsDeleteLigneForfait);

            // 3. Insérer les frais forfaitisés
            foreach ($_POST['frais_forfait'] as $type => $details) {
                if (isset($fraisForfaitMapping[$type])) {
                    $idFraisForfait = $fraisForfaitMapping[$type];
                    $quantite = intval($details['quantite']);
                    
                    if ($quantite > 0) {
                        $sqlLigneForfait = "INSERT INTO LigneFraisForfait (idVisiteur, mois, idFraisForfait, quantite) 
                                            VALUES (?, ?, ?, ?)";
                        $paramsLigneForfait = [$idVisiteur, $mois, $idFraisForfait, $quantite];
                        logs("Insertion LigneFraisForfait: " . json_encode($paramsLigneForfait));
                        RequestSqlInsert($sqlLigneForfait, $paramsLigneForfait);
                    }
                }
            }

            // 4. Insérer les frais hors forfait valides
            foreach ($validHorsForfait as $frais) {
                $sqlHorsForfait = "INSERT INTO LigneFraisHorsForfait (idVisiteur, mois, libelle, date, montant) VALUES (?, ?, ?, ?, ?)";
                
                $paramsHorsForfait = [
                    $idVisiteur,
                    $mois,
                    $frais['libelle'],
                    $frais['date'],
                    floatval($frais['montant'])
                ];
                
                logs("Insertion LigneFraisHorsForfait: " . json_encode($paramsHorsForfait));
                RequestSqlInsert($sqlHorsForfait, $paramsHorsForfait);
            }
            
            // 5. Insérer ou mettre à jour la note de frais
            $sqlNoteFrais = "INSERT INTO NoteFrais (idVisiteur, mois, montantTotal, dateCreation, idEtat) 
                             VALUES (?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE 
                             montantTotal = VALUES(montantTotal),
                             dateModif = CURRENT_TIMESTAMP";
            $paramsNoteFrais = [$idVisiteur, $mois, $montantTotal, $dateCreation, $idEtat];
            logs("Insertion/MAJ NoteFrais: " . json_encode($paramsNoteFrais));
            RequestSqlInsert($sqlNoteFrais, $paramsNoteFrais);

            // Redirection après succès
            alert('Votre fiche de frais a été sauvegardée avec succès!');
            reload("Renseigner Fiche Frais", "/GSB/pages/visiteur/form.php");
            exit;
        } else {
            logs("Erreur: Éléments requis manquants dans la requête");
            alert('Certaines informations requises sont manquantes ou incorrectes. Veuillez vérifier votre saisie.');
            // Ne pas rediriger pour permettre à l'utilisateur de corriger
        }
    } catch (Exception $e) {
        logs("Erreur lors du traitement: " . $e->getMessage());
        alert('Une erreur est survenue lors du traitement de votre demande: ' . $e->getMessage());
        // Ne pas rediriger pour permettre à l'utilisateur de corriger
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
        <a href="/GSB/pages/auth/logout.php">
            <img class="svg" src="../../public/images/logout.svg" alt="logout">
        </a>
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
                    <input type="date" id="date" placeholder="Date d'engagement">
                    <input type="text" id="libelle" placeholder="Libellé">
                    <input type="number" id="montant" placeholder="Montant" step="0.01" min="0.01">
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
                            <td><input type="number" name="frais_forfait[repas][quantite]" class="quantite" data-prix="20.00" value="0" min="0"></td>
                            <td>20.00 €</td>
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

            if (!date) erreurs.push("Le champ date doit être renseigné");
            if (!libelle) erreurs.push("Le champ libellé doit être renseigné");
            if (!montant || isNaN(montant) || parseFloat(montant) <= 0) erreurs.push("Le champ montant doit être valide et supérieur à 0");

            const dateEngagement = new Date(date);
            const dateActuelle = new Date();
            const dateMin = new Date();
            dateMin.setFullYear(dateActuelle.getFullYear() - 1);

            if (dateEngagement > dateActuelle) erreurs.push("La date d'engagement ne peut pas être dans le futur");
            if (dateEngagement < dateMin) erreurs.push("La date d'engagement doit se situer dans l'année écoulée");

            if (erreurs.length > 0) {
                alert(erreurs.join("\n"));
                return;
            }

            const li = document.createElement("li");
            li.textContent = `${date} - ${libelle}: ${parseFloat(montant).toFixed(2)} €`;
            
            // Boutton de suppression
            const btnDelete = document.createElement("button");
            btnDelete.textContent = "Supprimer";
            btnDelete.className = "btn btn-red delete-frais";
            btnDelete.dataset.index = horsForfaitIndex;
            btnDelete.style.marginLeft = "10px";
            li.appendChild(btnDelete);
            
            document.getElementById("hors-forfait-list").appendChild(li);

            const form = document.getElementById("fraisForm");
            const divId = `frais-hf-${horsForfaitIndex}`;
            
            form.insertAdjacentHTML('beforeend', `
                <div id="${divId}">
                    <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][date]" value="${date}">
                    <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][libelle]" value="${libelle}">
                    <input type="hidden" name="frais_hors_forfait[${horsForfaitIndex}][montant]" value="${montant}">
                </div>
            `);
            
            // Gestionnaire d'événement pour le bouton de suppression
            btnDelete.addEventListener("click", function() {
                if (confirm("Êtes-vous sûr de vouloir supprimer ce frais?")) {
                    // Supprimer l'élément de la liste visuelle
                    li.remove();
                    
                    // Supprimer les champs de formulaire associés
                    const divToRemove = document.getElementById(divId);
                    if (divToRemove) divToRemove.remove();
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
        
        // Validation du formulaire avant soumission
        document.getElementById("fraisForm").addEventListener("submit", function(e) {
            const fraisForfait = document.querySelectorAll(".quantite");
            const horsForfaitItems = document.querySelectorAll("#hors-forfait-list li");
            
            // Vérifier si au moins un frais forfait ou hors forfait est saisi
            let hasFrais = false;
            
            fraisForfait.forEach(input => {
                if (parseFloat(input.value) > 0) {
                    hasFrais = true;
                }
            });
            
            if (horsForfaitItems.length > 0) {
                hasFrais = true;
            }
            
            if (!hasFrais) {
                e.preventDefault();
                alert("Veuillez saisir au moins un frais forfaitaire ou hors forfait avant de valider.");
            }
            
            // Si le formulaire est valide, afficher un message de chargement
            if (hasFrais) {
                const btn = document.getElementById("valider-frais");
                btn.textContent = "Traitement en cours...";
                btn.disabled = true;
                btn.classList.add("btn-disabled");
            }
        });
    </script>
</body>
</html>