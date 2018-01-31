<?php
class AuthMiddleware
{
	########## Google Settings.Client ID, Client Secret from https://console.developers.google.com #############
	private $client;
	private $service;
	private $apiKey;
	public $container;
	
	function __construct($c) {
		$this->container = $c;
		$this->client = new Google_Client();
	
		$ini_array = parse_ini_file("../config/config.ini", true);
		$client_id = $ini_array['google_login']['client_id'];
		$client_secret = $ini_array['google_login']['client_secret'];
		$redirect_uri = $ini_array['google_login']['redirect_uri'];
		$this->apiKey = $ini_array['api']['api_key'];
		$this->client->setClientId($client_id);
		$this->client->setClientSecret($client_secret);
		$this->client->setRedirectUri($redirect_uri);
		$this->client->addScope("email");
		$this->client->addScope("profile");

		$this->service = new Google_Service_Oauth2($this->client);
	}
	
    /**
     * Example middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
		$path_only = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		#$response->getBody()->write($path_only);
		if ($path_only == '/api/v1' or $path_only == '/swagger/docs' or
				$path_only == '/api/v1/ping' or $path_only == '/api/v1/CreateTables' or $path_only == '/') {
			$response = $next($request, $response);
			return $response;
		}
		if ($path_only == '/api/v1/login') {
			if (isset($_GET['code'])) {
				$this->client->authenticate($_GET['code']);
				$_SESSION['access_token'] = $this->client->getAccessToken();
				$response = $next($request, $response);
				#echo('code');
				return $response;
			} else {
				#echo('idtoken');
				$id_token = $request->getParam('idtoken');
				#echo('idtoken $id_token');
				Firebase\JWT\JWT::$leeway = 5; // Allows a 5 second tolerance on timing checks
				$payload = $this->client->verifyIdToken($id_token);
				if ($payload) {
					$_SESSION['user_id'] = $payload['sub'];;
					$_SESSION['email'] = $payload['email'];;
					$response = $next($request, $response);
					return $response;
				} else {
					// Invalid ID token
					$_SESSION['user_id'] = NULL;
					$_SESSION['email'] = NULL;
					$_SESSION['access_token'] = NULL;
				}
			}
		}
		#echo($_SESSION['access_token']);
		$oauthUserId = NULL;
		$email = NULL;
		if (isset($_SESSION['access_token']) and $_SESSION['access_token']){
			$this->client->setAccessToken($_SESSION['access_token']);
			$googleUser = $this->service->userinfo->get(); //get user info
			$oauthUserId = $googleUser->id;
			$email = $googleUser->email;
		}
		if (isset($_SESSION['user_id']) and $_SESSION['user_id']){
			$userData = $_SESSION['user_id'];
			$oauthUserId = $_SESSION['user_id'];
			$email = $_SESSION['email'];
		}
		
		if ($oauthUserId) {
			$cls = User::class;
			$sql = "SELECT * FROM Users WHERE oauthUserId = :oauthUserId";
			$values = ['oauthUserId'=>$oauthUserId];
			$userFromDb = $this->container->helper->GetBySql($cls, $sql, $values);
			if (!$userFromDb) {
				$userToSaveToDb = ['email' => $email,'oauthProvider' => 'Google', 'oauthUserId' => $oauthUserId];
				$id = $this->container->helper->Insert($cls, $userToSaveToDb, $cls::$tableName);
				$userFromDb = $this->container->helper->Get($cls, $cls::$tableName, $id);
			}
			$request = $request->withAttribute('user', $userFromDb);
			$response = $next($request, $response);
		} else {
			$headerValueString = $request->getHeaderLine('Authorization');
			echo($headerValueString);
			if ($headerValueString == $this->apiKey) {
				$response = $next($request, $response);
				return $response;
			} else {
				/* Set response headers before giving it to error callback */
				$response = $response->withStatus(401);
				$response->getBody()->write('Unauthorized');
				return $response;
			}
		}
        return $response;
    }
}
