<?php

function checkLogin(){

    if (isset($_COOKIE['GSB'])) {
        // Vérifier si l'utilisateur à un cookie
        $cookie_data = json_decode($_COOKIE['GSB']);

        // Vérifier que les valeurs nécessaires sont présentes dans le cookie
        if(!isset($cookie_data['login']) && !isset($cookie_data['mdp'])) return false;

        // Vérifier si l'utilisateur existe dans la db
        $data = Login($cookie_data['login'], $cookie_data['mdp']);

        // Si non retourner False
        if(!isset($data['nom'])) return false;

        // Si oui retourner true
        return true;
    
    } else if(isset($_SESSION['mdp']) && isset($_SESSION['login'])) {
        // Vérifier si l'utilisateur a une session

        // Vérifier si l'utilisateur existe dans la db
        $data = Login($_SESSION['login'], $_SESSION['mdp']);
    
         // Si non retourner False
        if(!isset($data['nom'])) return false;

        // Si oui retourner True
        return true;
    }

}

?>