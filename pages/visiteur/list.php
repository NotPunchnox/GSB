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

$données = RequestSQL("select * from NoteFrais where idVisiteur = \"" .$GLOBALS["id"] . "\"");
foreach ($données as $row) {
    logs($row, true);
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
        <img class="svg" src="../../public/images/logout.svg" alt="logout">
    </nav>

    <div class="container">
        <h2 class="mb-4">Consultation des fiches de frais</h2>
        <div class="card">
            <div class="text-right mb-4">
                <label for="mois-select">Sélectionner un mois :</label>
                <select id="mois-select" class="search-input" style="max-width: 300px;"></select>
                <button type="button" id="valider-mois" class="btn">Valider</button>
            </div>
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
                    <tr>
                        <td>Avril 2024</td>
                        <td>1247 €</td>
                        <td><span class="badge badge-green">Validée</span></td>
                        <td><button class="details-btn" data-key="2024-04">Voir détails</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="side-panel" id="details-panel">
        <div class="side-panel-header">
            <h3 id="panel-title">Détails de la fiche</h3>
            <button type="button" class="close-btn">×</button>
        </div>
        <div id="details-content"></div>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const moisSelect = document.getElementById("mois-select");
            const validerBtn = document.getElementById("valider-mois");
            const fraisList = document.getElementById("frais-list");
            const detailsPanel = document.getElementById("details-panel");
            const panelTitle = document.getElementById("panel-title");
            const detailsContent = document.getElementById("details-content");
            const closeBtn = document.querySelector(".close-btn");
    
            validerBtn.addEventListener("click", () => {
                const selectedMonth = moisSelect.value;
                fraisList.innerHTML = "";
                // La logique d'affichage des données sera gérée côté serveur ou via une autre source
            });
    
            fraisList.addEventListener("click", (e) => {
                if (e.target.classList.contains("details-btn")) {
                    const key = e.target.getAttribute("data-key");
                    panelTitle.textContent = `Détails de la fiche - ${key}`;
                    // Les détails devront être chargés dynamiquement (par exemple via AJAX)
                    detailsPanel.classList.add("open");
                }
            });
    
            closeBtn.addEventListener("click", () => {
                detailsPanel.classList.remove("open");
            });
        });
    </script>
</body>
</html>