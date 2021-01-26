<?php
session_start();
require '../../../vendor/autoload.php';
require '../../config-hub.php';
$provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId' => FORGE_CLIENT_ID, // The client ID assigned to you by the provider
    'clientSecret' => FORGE_CLIENT_SECRET, // The client password assigned to you by the provider
    'redirectUri' => 'https://www.foundryvtt-hub.com/api/forge/oauth_callback.php',
    'urlAuthorize' => 'https://forge-vtt.com/oauth2/authorize',
    'urlAccessToken' => 'https://forge-vtt.com/oauth2/token',
    'urlResourceOwnerDetails' => 'https://forge-vtt.com/oauth2/resource', //not implemented
    'scopes' => ['read-profile', 'write-data', 'read-data'],
    'scopeSeparator' => ' '
]);
if(isset($_GET['hub_redirect'])){
    $_SESSION['hub_redirect'] = urldecode($_GET['hub_redirect']);
}
// If we don't have an authorization code then get one
if (!isset($_GET['code'])) {

    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $authorizationUrl = $provider->getAuthorizationUrl();

    // Get the state generated for you and store it to the session.
    $_SESSION['forge_oauth2state'] = $provider->getState();

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorizationUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || (isset($_SESSION['forge_oauth2state']) && $_GET['state'] !== $_SESSION['forge_oauth2state'])) {

    if (isset($_SESSION['forge_oauth2state'])) {
        unset($_SESSION['forge_oauth2state']);
    }

    exit('Invalid state');

} else {

    try {

        // Try to get an access token using the authorization code grant.
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code'],
        ]);

        //keep it in memcached
        $_SESSION['forge_accesstoken'] = $accessToken->getToken();
        
        header('Location: '.$_SESSION['hub_redirect']);
        exit;
    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());

    }

}
