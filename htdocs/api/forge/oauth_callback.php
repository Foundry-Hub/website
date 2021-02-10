<?php
require '../../../vendor/autoload.php';
require '../../config-hub.php';
$provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId' => FORGE_CLIENT_ID, // The client ID assigned to you by the provider
    'clientSecret' => FORGE_CLIENT_SECRET, // The client password assigned to you by the provider
    'redirectUri' => 'https://www.foundryvtt-hub.com/api/forge/oauth_callback.php',
    'urlAuthorize' => 'https://forge-vtt.com/oauth2/authorize',
    'urlAccessToken' => 'https://forge-vtt.com/oauth2/token',
    'urlResourceOwnerDetails' => 'https://forge-vtt.com/oauth2/resource', //not implemented
    'scopes' => ['read-profile', 'write-data', 'read-data', 'manage-games'],
    'scopeSeparator' => ' '
]);
if(isset($_GET['hub_redirect'])){
    setcookie('hub_redirect',urldecode($_GET['hub_redirect']),0,"/","foundryvtt-hub.com",true,true);
}
// If we don't have an authorization code then get one
if (!isset($_GET['code'])) {

    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $authorizationUrl = $provider->getAuthorizationUrl();

    // Get the state generated for you and store it to the session.
    setcookie('forge_oauth2state',$provider->getState(),0,"/","foundryvtt-hub.com",true,true);

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorizationUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || (isset($_COOKIE['forge_oauth2state']) && $_GET['state'] !== $_COOKIE['forge_oauth2state'])) {

    if (isset($_COOKIE['forge_oauth2state'])) {
        setcookie('forge_oauth2state',"",time()-3600,0,"/","foundryvtt-hub.com",true,true);
    }

    exit('Invalid state');

} else {

    try {

        // Try to get an access token using the authorization code grant.
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code'],
        ]);

        //keep it in memcached
        setcookie('forge_accesstoken',$accessToken->getToken(),0,"/","foundryvtt-hub.com",true,true);
        
        header('Location: '.$_COOKIE['hub_redirect']);
        exit;
    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());

    }

}
