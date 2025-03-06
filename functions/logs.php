<?php
// Fonction de logs permettant d'afficher le contenu souhaité dans les logs du navigateurs ( développeur tools )
function logs($msg, $json=false) {
    if($json == true) {
        echo "<script>console.log(JSON.parse('" . json_encode($msg) . "'));</script>";
    } else {
        echo "<script>console.log('" . $msg . "');</script>";
    }
}

?>