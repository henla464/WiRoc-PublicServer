<?php


require_once '../vendor/autoload.php';
session_start();

########## Google Settings.Client ID, Client Secret from https://console.developers.google.com #############
$ini_array = parse_ini_file("../config/config.ini", true);

$client_id = $ini_array['google_login']['client_id'];
$client_secret = $ini_array['google_login']['client_secret'];
$redirect_uri = $ini_array['google_login']['redirect_uri'];

$client = new Google_Client();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);
$client->addScope("email");
$client->addScope("profile");

$service = new Google_Service_Oauth2($client);
$authUrl = $client->createAuthUrl();

//Display user info or display login url as per the info we have.
echo '<div style="margin:20px">';
if (!isset($_SESSION['access_token'])){ 
    //show login url
    echo '<div align="center">';
    echo '<h3>Login with Google</h3>';
    echo '<div>Please click login button to connect to Google.</div>';
    echo '<a class="login" href="' . $authUrl . '"><img src="images/google-login.png" /></a>';
    echo '</div>';
    
} else {
	#$_SESSION['access_token'] = NULL;
	$client->setAccessToken($_SESSION['access_token']);
    $user = $service->userinfo->get(); //get user info 
	echo 'Hi '.$user->name.', you are logged in! [<a href="logout.php?logout=1">Log Out</a>]';
    
    //print user details
    #echo '<pre>';
    #print_r($user);
    #echo '</pre>';
}
echo '</div>';
