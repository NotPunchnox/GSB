<?php include("../../functions/logs.php"); ?>
<?php include("../../functions/create-cookie.php"); ?>
<?php include("../../functions/alert.php"); ?>
<?php include("../../functions/login.php"); ?>
<?php include("../../functions/check-login.php"); ?>
<?php include("../../functions/sql-request.php"); ?>

<?php
session_start();

logs("Session start");

// Vérifier si l'utilisateur est déjà connecté, si oui le rediriger vers le dashboard
if(!checkLogin()) {
    header('Location: /GSB/');
}


$données = RequestSQL('select * from Etat');

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
        <div class="card">
            <h3>Informations visiteur</h3>
            <div class="grid">
                <div class="form-group">
                    <label>Nom</label>
                    <?php echo "<input type=\"text\" value=\"" . $GLOBALS['nom'] . "\" readonly>"; ?>
                </div>
                <div class="form-group">
                    <label>Prénom</label>
                    <?php echo "<input type=\"text\" value=\"" . $GLOBALS['prenom'] . "\" readonly>"; ?>
                </div>
                <div class="form-group">
                    <label>Matricule</label>
                    <?php echo "<input type=\"text\" value=\"" . $GLOBALS['id'] . "\" readonly>"; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <h3> hors forfait</h3>
            <div class="grid">
                <input type="date" id="date" required>
                <input type="text" id="libelle" placeholder="Libellé" required>
                <input type="number" id="montant" placeholder="Montant" required>
                <button type="button" class="btn btn-blue" id="ajouter-frais">Ajouter</button>
            </div>
            <ul id="hors-forfait-list"></ul>
        </div>

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
                <tbody id="forfaitises"></tbody>
            </table>
            <div class="validate">
                <button type="button" class="btn btn-green" id="valider-frais">Valider</button>
            </div>
        </div>
    </div>

    <script>
        const forfaitises = [
            { type: "Repas restaurant", prix: 20.00 },
            { type: "Nuitées hôtel", prix: 80.00 },
            { type: "Frais kilométriques", prix: 0.62 },
            { type: "Forfait Etape", prix: 110.00 }
        ];

        const forfaitTable = document.getElementById("forfaitises");
        forfaitises.forEach(frais => {
            const row = document.createElement("tr");
            row.innerHTML = `
                <td>${frais.type}</td>
                <td><input type="number" class="quantite" data-prix="${frais.prix}" value="0"></td>
                <td>${frais.prix % 2 == 0 ? frais.prix + ".00" : frais.prix} €</td>
                <td class="total">0 €</td>
            `;
            forfaitTable.appendChild(row);
        });

        document.querySelectorAll(".quantite").forEach(input => {
            input.addEventListener("input", () => {
                const prix = parseFloat(input.dataset.prix);
                const quantite = parseFloat(input.value) || 0;
                input.closest("tr").querySelector(".total").textContent = `${(prix * quantite).toFixed(2)} €`;
            });
        });

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
        });
    </script>
</body>
</html>
