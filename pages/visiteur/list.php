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

// Récupération des notes de frais de l'utilisateur
$notesFrais = RequestSQL("SELECT * FROM NoteFrais WHERE idVisiteur = ? ORDER BY mois DESC", [$GLOBALS["id"]]);

// Récupération de tous les mois distincts pour le sélecteur
$moisDisponibles = RequestSQL("SELECT DISTINCT mois FROM NoteFrais WHERE idVisiteur = ? ORDER BY mois DESC", [$GLOBALS["id"]]);

// Traitement de la sélection du mois
$moisSelectionne = null;
$detailsFiche = null;
$fraisForfaitises = null;
$fraisHorsForfait = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mois-select']) && !empty($_POST['mois-select'])) {
    $moisSelectionne = $_POST['mois-select'];
    
    // Récupération des détails de la fiche de frais
    $detailsFiche = RequestSQL(
        "SELECT nf.*, e.libelle as etatLibelle 
         FROM NoteFrais nf 
         JOIN Etat e ON nf.idEtat = e.id 
         WHERE nf.idVisiteur = ? AND nf.mois = ?", 
        [$GLOBALS["id"], $moisSelectionne]
    );
    
    if (!empty($detailsFiche)) {
        $detailsFiche = $detailsFiche[0]; // Prendre la première ligne
        
        // Récupération des frais forfaitisés
        $fraisForfaitises = RequestSQL(
            "SELECT lff.*, ff.libelle as typeFrais, ff.montant as montantUnitaire 
             FROM LigneFraisForfait lff 
             JOIN FraisForfait ff ON lff.idFraisForfait = ff.id 
             WHERE lff.idVisiteur = ? AND lff.mois = ?", 
            [$GLOBALS["id"], $moisSelectionne]
        );

        // Récupération des frais hors forfait
        $fraisHorsForfait = RequestSQL(
            "SELECT * FROM LigneFraisHorsForfait 
             WHERE idVisiteur = ? AND mois = ?", 
            [$GLOBALS["id"], $moisSelectionne]
        );

        logs("Détails:");
        logs($fraisHorsForfait, true);
    }
}

// Fonction pour formater le mois (YYYYMM -> "Mois YYYY")
function formatMois($moisCode) {
    $annee = substr($moisCode, 0, 4);
    $mois = substr($moisCode, 4, 2);
    
    $nomsMois = [
        '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril',
        '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août',
        '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre'
    ];
    
    return $nomsMois[$mois] . ' ' . $annee;
}

// Fonction pour déterminer la classe CSS en fonction de l'état
function getEtatClass($idEtat) {
    switch ($idEtat) {
        case 'VA': return 'badge-green'; // Validée
        case 'RB': return 'badge-blue';  // Remboursée
        case 'CL': return 'badge-gray';  // Clôturée
        case 'CR': return 'badge-orange'; // Créée
        case 'RE': return 'badge-red';   // Refusée
        default:   return 'badge-gray';  // Par défaut
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulter Fiche Frais</title>
    <link rel="stylesheet" href="../../public/css/visiteur.css">
</head>
<body>
    <nav>
        <div>
            <a href="form.php">Renseigner Fiche Frais</a>
            <a href="list.php" class="active">Consulter Fiche Frais</a>
        </div>
        <a href="../../logout.php">
            <img class="svg" src="../../public/images/logout.svg" alt="logout">
        </a>
    </nav>

    <div class="container">
        <h2 class="mb-4">Consultation des fiches de frais</h2>
        <div class="card">
            <form method="POST" action="list.php" class="text-right mb-4">
                <label for="mois-select">Sélectionner un mois :</label>
                <select id="mois-select" name="mois-select" class="search-input" style="max-width: 300px;">
                    <option value="">Choisir un mois</option>
                    <?php 
                    $displayedMonths = []; // Track months we've already shown
                    foreach($moisDisponibles as $mois): 
                        // Only show each month once
                        if(!in_array($mois, $displayedMonths)):
                            $displayedMonths[] = $mois;
                    ?>
                        <option value="<?php echo $mois['mois']; ?>" <?php echo ($moisSelectionne == $mois['mois']) ? 'selected' : ''; ?>>
                            <?php echo formatMois($mois['mois']); ?>
                        </option>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </select>
                <button type="submit" class="btn">Valider</button>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>Période</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="frais-list">
                    <?php if(empty($notesFrais)): ?>
                        <tr>
                            <td colspan="4" class="text-center">Aucune fiche de frais disponible</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($notesFrais as $frais): ?>
                            <tr>
                                <td><?php echo formatMois($frais['mois']); ?></td>
                                <td><?php echo number_format($frais['montantTotal'], 2, ',', ' '); ?> €</td>
                                <td>
                                    <span class="badge <?php echo getEtatClass($frais['idEtat']); ?>">
                                        <?php 
                                        // On pourrait faire une requête pour récupérer le libellé de l'état, ou utiliser un mapping simple
                                        $etats = [
                                            'CR' => 'Créée',
                                            'VA' => 'Validée',
                                            'RB' => 'Remboursée',
                                            'CL' => 'Clôturée',
                                            'RE' => 'Refusée'
                                        ];
                                        echo isset($etats[$frais['idEtat']]) ? $etats[$frais['idEtat']] : $frais['idEtat'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="list.php" style="display: inline;">
                                        <input type="hidden" name="mois-select" value="<?php echo $frais['mois']; ?>">
                                        <button type="submit" class="details-btn" data-key="<?php echo $frais['mois']; ?>">
                                            Voir détails
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Détails de la fiche -->
        <?php if($detailsFiche): ?>
        <div class="card mt-4">
            <h3>Détails de la fiche - <?php echo formatMois($moisSelectionne); ?></h3>
            
            <!-- Informations générales -->
            <div class="info-section">
                <div class="info-item">
                    <span class="info-label">Montant total:</span>
                    <span class="info-value"><?php echo number_format($detailsFiche['montantTotal'], 2, ',', ' '); ?> €</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date de création:</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($detailsFiche['dateCreation'])); ?></span>
                </div>
                <br>
                <div class="info-item">
                    <span class="info-label">État:</span>
                    <?php logs($detailsFiche); ?>
                    <span class="badge <?php echo getEtatClass($detailsFiche['idEtat']); ?>">
                        <?php echo $detailsFiche['etatLibelle']; ?>
                    </span>
                </div>
            </div>
            
            <!-- Frais forfaitisés -->
            <h4 class="mt-4">Frais forfaitisés</h4>
            <?php if(empty($fraisForfaitises)): ?>
                <p>Aucun frais forfaitisé pour cette période.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Quantité</th>
                            <th>Montant unitaire</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $totalForfaitises = 0;
                            foreach($fraisForfaitises as $frais):
                                $montantTotal = $frais['quantite'] * $frais['montantUnitaire'];
                                $totalForfaitises += $montantTotal;
                            ?>
                            <tr>
                                <td><?php echo $frais['typeFrais']; ?></td>
                                <td><?php echo $frais['quantite']; ?></td>
                                <td><?php echo number_format($frais['montantUnitaire'], 2, ',', ' '); ?> €</td>
                                <td><?php echo number_format($montantTotal, 2, ',', ' '); ?> €</td>
                            </tr>
                            <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="3" class="text-right"><strong>Total frais forfaitisés:</strong></td>
                            <td><strong></strong></td>
                            <td><strong></strong></td>
                            <td><strong><?php echo number_format($totalForfaitises, 2, ',', ' '); ?> €</strong></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- Frais hors forfait -->
            <h4 class="mt-4">Frais hors forfait</h4>
            <?php if(empty($fraisHorsForfait)): ?>
                <p>Aucun frais hors forfait pour cette période.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Libellé</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalHorsForfait = 0;
                        foreach($fraisHorsForfait as $frais): 
                            $totalHorsForfait += $frais['montant'];
                        ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($frais['date'])); ?></td>
                                <td><?php echo $frais['libelle']; ?></td>
                                <td><?php echo number_format($frais['montant'], 2, ',', ' '); ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="2" class="text-right"><strong>Total frais hors forfait:</strong></td>
                            <td></td>
                            <td><strong><?php echo number_format($totalHorsForfait, 2, ',', ' '); ?> €</strong></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Code JS uniquement pour les animations
            // Par exemple, animations pour les boutons, transitions, etc.
            
            // Animation pour les tableaux détaillés
            const detailsBtn = document.querySelectorAll(".details-btn");
            
            detailsBtn.forEach(btn => {
                btn.addEventListener("click", function() {
                    // Animation lors du clic sur le bouton (par exemple, un effet de pulsation)
                    this.classList.add("pulse");
                    setTimeout(() => {
                        this.classList.remove("pulse");
                    }, 500);
                });
            });
        });
    </script>
</body>
</html>