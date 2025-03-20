<?php
session_start();

include("../../functions/create-cookie.php");

// Reset le cookie
setcookie("GSB", "", 0);
setcookie("PHPSESSID", "", 0);

// Reset la session
$_SESSION['nom'] = "";
$_SESSION['id'] = "";
$_SESSION['mdp'] = "";
$_SESSION['login'] = "";

header("Location: /GSB/");

?>