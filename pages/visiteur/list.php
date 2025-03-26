<?php
session_start();

include("../../functions/index.php");

if (!checkLogin()) {
    header('Location: ' . $GLOBALS['baseURL']);
    exit;
}

$moisSelectionne = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mois-select-dropdown']) ? $_POST['mois-select-dropdown'] : null;
$moisDetails = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mois-details']) ? $_POST['mois-details'] : null;

$mois = $moisSelectionne == null ? $moisDetails : $moisSelectionne;

logs($mois);

$detailsFiche = $fraisForfaitises = $fraisHorsForfait = null;


$notesFrais = RequestSQL("SELECT * FROM NoteFrais WHERE idVisiteur = ? AND mois = ? ORDER BY mois DESC", [$GLOBALS["id"], $mois]);
$moisDisponibles = RequestSQL("SELECT DISTINCT mois FROM NoteFrais WHERE idVisiteur = ? ORDER BY mois DESC", [$GLOBALS["id"]]);

if ($moisDetails) {

    $detailsFiche = RequestSQL(
        "SELECT nf.mois, SUM(nf.montantTotal) as montantTotal, MIN(nf.dateCreation) as dateCreation, e.libelle as etatLibelle, nf.idEtat
         FROM NoteFrais nf 
         JOIN Etat e ON nf.idEtat = e.id 
         WHERE nf.idVisiteur = ? AND nf.mois = ?
         GROUP BY nf.mois, e.libelle, nf.idEtat",
        [$GLOBALS["id"], $mois]
    )[0] ?? null;

    if ($detailsFiche) {
        $fraisForfaitises = RequestSQL(
            "SELECT lff.*, ff.libelle as typeFrais, ff.montant as montantUnitaire 
             FROM LigneFraisForfait lff 
             JOIN FraisForfait ff ON lff.idFraisForfait = ff.id 
             WHERE lff.idVisiteur = ? AND lff.mois = ?",
            [$GLOBALS["id"], $mois]
        );

        $fraisHorsForfait = RequestSQL(
            "SELECT * FROM LigneFraisHorsForfait 
            WHERE idVisiteur = ? AND mois = ?",
            [$GLOBALS["id"], $mois]
        );
    }
}

function formatMois($moisCode) {
    $nomsMois = [
        '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril',
        '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août',
        '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre'
    ];
    return $nomsMois[substr($moisCode, 4, 2)] . ' ' . substr($moisCode, 0, 4);
}

function getEtatClass($idEtat) {
    $classes = [
        'VA' => 'badge-green', 'RB' => 'badge-blue', 'CL' => 'badge-gray',
        'CR' => 'badge-orange', 'RE' => 'badge-red'
    ];
    return $classes[$idEtat] ?? 'badge-gray';
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
        <a href="<?php echo $GLOBALS['baseURL']; ?>pages/auth/logout.php">
            <img class="svg" src="../../public/images/logout.svg" alt="logout">
        </a>
    </nav>

    <div class="container">
        <h2 class="mb-4">Consultation des fiches de frais</h2>
        <div class="card">
            <form method="POST" class="text-right mb-4">
                <label for="mois-select-dropdown">Sélectionner un mois :</label>
                <select id="mois-select-dropdown" name="mois-select-dropdown" class="search-input" style="max-width: 300px;">
                    <option value="">Choisir un mois</option>
                    <?php foreach ($moisDisponibles as $mois): ?>
                        <option value="<?php echo $mois['mois']; ?>" <?php echo $mois === $mois['mois'] ? 'selected' : ''; ?>>
                            <?php echo formatMois($mois['mois']); ?>
                        </option>
                    <?php endforeach; ?>
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
                <tbody>
                    <?php if (empty($notesFrais)): ?>
                        <tr><td colspan="4" class="text-center">Aucune fiche de frais disponible</td></tr>
                    <?php else: foreach ($notesFrais as $frais): ?>
                        <tr>
                            <td><?php echo formatMois($frais['mois']); ?></td>
                            <td><?php echo number_format($frais['montantTotal'], 2, ',', ' '); ?> €</td>
                            <td><span class="badge <?php echo getEtatClass($frais['idEtat']); ?>"><?php echo ['CR' => 'Créée', 'VA' => 'Validée', 'RB' => 'Remboursée', 'CL' => 'Clôturée', 'RE' => 'Refusée'][$frais['idEtat']] ?? $frais['idEtat']; ?></span></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="mois-details" value="<?php echo $frais['mois']; ?>">
                                    <button type="submit" class="details-btn">Voir détails</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($detailsFiche): ?>
            <div class="card mt-4">
                <h3>Détails de la fiche - <?php echo formatMois($moisDetails); ?></h3>
                <div class="info-section">
                    <div class="info-item">
                        <span class="info-label">Montant total:</span>
                        <span class="info-value"><?php echo number_format($detailsFiche['montantTotal'], 2, ',', ' '); ?> €</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date de création:</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($detailsFiche['dateCreation'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">État:</span>
                        <span class="badge <?php echo getEtatClass($detailsFiche['idEtat']); ?>"><?php echo $detailsFiche['etatLibelle']; ?></span>
                    </div>
                </div>

                <h4 class="mt-4">Frais forfaitisés</h4>
                <?php if (empty($fraisForfaitises)): ?>
                    <p>Aucun frais forfaitisé pour cette période.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th>Type</th><th>Quantité</th><th>Montant unitaire</th><th>Total</th></tr>
                        </thead>
                        <tbody>
                            <?php $totalForfaitises = 0; foreach ($fraisForfaitises as $frais):
                                $montantTotal = $frais['quantite'] * $frais['montantUnitaire'];
                                $totalForfaitises += $montantTotal; ?>
                                <tr>
                                    <td><?php echo $frais['typeFrais']; ?></td>
                                    <td><?php echo $frais['quantite']; ?></td>
                                    <td><?php echo number_format($frais['montantUnitaire'], 2, ',', ' '); ?> €</td>
                                    <td><?php echo number_format($montantTotal, 2, ',', ' '); ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="3" class="text-right"><strong>Total frais forfaitisés:</strong></td>
                                <td><strong><?php echo number_format($totalForfaitises, 2, ',', ' '); ?> €</strong></td>
                            </tr>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h4 class="mt-4">Frais hors forfait</h4>
                <?php if (empty($fraisHorsForfait)): ?>
                    <p>Aucun frais hors forfait pour cette période.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th>Date</th><th>Libellé</th><th>Montant</th></tr>
                        </thead>
                        <tbody>
                            <?php $totalHorsForfait = 0; foreach ($fraisHorsForfait as $frais):
                                logs($frais);
                                $totalHorsForfait += $frais['montant']; ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($frais['date'])); ?></td>
                                    <td><?php echo $frais['libelle']; ?></td>
                                    <td><?php echo number_format($frais['montant'], 2, ',', ' '); ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="2" class="text-right"><strong>Total frais hors forfait:</strong></td>
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
            document.querySelectorAll(".details-btn").forEach(btn => {
                btn.addEventListener("click", function() {
                    this.classList.add("pulse");
                    setTimeout(() => this.classList.remove("pulse"), 500);
                });
            });
        });
    </script>
</body>
</html>