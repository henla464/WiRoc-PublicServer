<?php
session_start();

use DI\Container;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Slim\Factory\AppFactory;
require 'PHPMailer/PHPMailer-master/src/Exception.php';
require 'PHPMailer/PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer/PHPMailer-master/src/SMTP.php';


require '../vendor/autoload.php';
//require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI == 'cli-server') {

    $url  = parse_url($_SERVER['REQUEST_URI']);

    $file = __DIR__ . $url['path'];
    // check the file types, only serve standard files
    if (preg_match('/\.(?:png|js|jpg|jpeg|gif|css|html|css|js|htm)$/', $file)) {
        // does the file exist? If so, return it
        if (is_file($file)) {
            return false;
        }

        // file does not exist. return a 404
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
        printf('"%s" does not exist', $_SERVER['REQUEST_URI']);
        return false;
    }
}




$container = new DI\Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

$container->set('config', function () {
    $config['displayErrorDetails'] = true;
    $config['addContentLengthHeader'] = false;
    $ini_array = parse_ini_file("../config/config.ini", true);
    $config['db']['host']   = $ini_array['database']['database_hostname'];
    $config['db']['user']   = $ini_array['database']['database_username'];
    $config['db']['pass']   = $ini_array['database']['database_password'];
    $config['db']['dbname'] = $ini_array['database']['database_name'];
    $config['upload']['log_archive_upload_directory'] = $ini_array['upload']['log_archive_upload_directory'];
    $config['pepper'] = $ini_array['user']['pepper'];
    $config['api_key'] = $ini_array['api']['api_key'];
    $config['smtp']['username'] = $ini_array['smtp']['username'];
    $config['smtp']['password'] = $ini_array['smtp']['password'];
    $config['smtp']['from'] = $ini_array['smtp']['from'];
    $config['smtp']['replyto'] = $ini_array['smtp']['replyto'];

    return $config;
});

$authMap = new AuthorizationMap($app);
$authMiddleware = new AuthorizationMiddleware($authMap);


$app->add($authMiddleware);
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);



$container->set('logger', function($container) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler("../logs/app.log");
    $logger->pushHandler($file_handler);
    return $logger;
});

$container->set('db', function ($c) {
    $db = $c->get('config')['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'] .";charset=utf8mb4",
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
});

$container->set('helper', function ($container) {
    return new Helper($container);
});



/**
 * @SWG\Info(title="WiRoc Monitor API", version="1")
 * @SWG\Swagger(
 *   @SWG\SecurityScheme(
 *     securityDefinition="api_key",
 *     type="apiKey",
 *     in="header",
 *     name="X-Authorization"
 *   )
 * )
 */

/**
 * @SWG\Get(
 *     path="/api/v1",
 *     @SWG\Response(response="200", description="V1 of the api")
 * )
 */
$app->get('/api/v1', function($request, $response, $args) use ($app) {
    $swagger = \Swagger\scan(['.', '../data_classes']);
    //header('Content-Type: application/json');
    $response->getBody()->write(json_encode($swagger));
    return $response;
})->setName("ApiV1");


/**
 * @SWG\Get(
 *     path="/swagger/docs",
 *     @SWG\Response(response="200", description="Redirects to the swagger for the latest API version")
 * )
 */
$app->redirect('/swagger/docs', '/api/v1', 301)->setName("getDocs");

/**
 * @SWG\Get(
 *     path="/api/v1/ping",
 * 	   description="Ping to check that server is up and replies",
 *     @SWG\Response(
 *         response="200", 
 *         description="CommandResponse code=0 is success",
 *         @SWG\Schema(
 *             ref="#/definitions/CommandResponse"
 *         )
 *     )
 * )
 */
$app->get('/api/v1/ping', function($request, $response, $args) use ($app) {
    $res = new CommandResponse();
	$res->code = 0;
	$res->message = "Server up";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("getPing");


/**
 * @SWG\Get(
 *     path="/api/v1/login",
 * 	   description="Login method",
 *     @SWG\Response(
 *         response="200", 
 *         description="CommandResponse code=0 is success",
 *         @SWG\Schema(
 *             ref="#/definitions/LoginResponse"
 *         )
 *     )
 * )
 */
$app->get('/api/v1/login', function($request, $response, $args) use ($app) {
    $res = new LoginResponse();
    if (!isset($_SESSION['userId'])) {
        $res->code = 1;
        $res->message = "Not logged in";
        $res->isLoggedIn = false;
        $res->isAdmin = false;
    } else {
        $res->code = 0;
	    $res->message = "Login OK";
        $res->isLoggedIn = true;
        $res->isAdmin = $_SESSION['userIsAdmin'];
    }
	$response->getBody()->write(json_encode($res));
    return $response;
})->setName("getLogin");


/**
 * @SWG\Post(
 *     path="/api/v1/login",
 * 	   description="Login method",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         name="user",
 *         in="body",
 *         description="User to login",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/NewNewUser"),
 *     ),
 *     @SWG\Response(
 *         response="200", 
 *         description="CommandResponse code=0 is success",
 *         @SWG\Schema(
 *             ref="#/definitions/LoginResponse"
 *         )
 *     )
 * )
 */
$app->post('/api/v1/login', function($request, $response, $args) use ($app) {
	$objectArray = json_decode($request->getBody(), true);
	
	$pepper = $this->get('config')['pepper'];
	$pwd_peppered = hash_hmac("sha256", $objectArray['password'], $pepper);
	
	$cls = User::class;
	$sql = "SELECT * FROM {$cls::$tableName} WHERE Email = :Email";
	$user = $this->get('helper')->GetBySql($cls, $sql, ['Email'=>$objectArray['email']]);
	
	if ($user != null && password_verify($pwd_peppered, $user->hashedPassword)) {
		$_SESSION['userId'] = $user->id;
        $_SESSION['userEmail'] = $user->email;
        $_SESSION['userIsAdmin'] = $user->isAdmin == null ? false : ($user->isAdmin == 1);
     	$res = new LoginResponse();
		$res->code = 0;
		$res->message = "Login OK";
        $res->isLoggedIn = true;
        $res->isAdmin = $user->isAdmin == null ? false : $user->isAdmin;
  		$response->getBody()->write(json_encode($res));
		return $response;
	}
	else {
		$res = new LoginResponse();
		$res->code = 1;
		$res->message = "Login failed";
        $res->isLoggedIn = false;
        $res->isAdmin = false;
 		$response->getBody()->write(json_encode($res));
		return $response;
	}
})->setName("postLogin");


/**
 * @SWG\Get(
 *     path="/api/v1/logout",
 * 	   description="Logout method",
 *     @SWG\Response(
 *         response="200", 
 *         description="CommandResponse code=0 is success",
 *         @SWG\Schema(
 *             ref="#/definitions/CommandResponse"
 *         )
 *     )
 * )
 */
$app->get('/api/v1/logout', function($request, $response, $args) use ($app) {
    $_SESSION['userId'] = null;
    $_SESSION['userEmail'] = null;
    $res = new CommandResponse();
    $res->code = 0;
    $res->message = "Logout OK";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("getLogout");


/**
     * @SWG\Post(
     *     path="/api/v1/CreateTables",
     *     description="Create the tables in the database if they don't exist",
     *     operationId="postCreateTables",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="command response",
     *         @SWG\Schema(
     *             ref="#/definitions/CommandResponse"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->post('/api/v1/CreateTables', function (Request $request, Response $response) {
	$response->getBody()->write(json_encode($this->get('helper')->CreateTables()));
	return $response;
})->setName("postCreateTables");



# USERS
/**
     * @SWG\Get(
     *     path="/api/v1/Users?sort={sort}",
     *     description="Returns all users",
     *     operationId="getUsers",
     *     @SWG\Parameter(
     *         description="columns to sort on",
     *         in="path",
     *         name="sort",
     *         required=false,
     *         type="string"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/User")
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/Users', function (Request $request, Response $response) {
	$cls = User::class;
    $response->getBody()->write(json_encode($this->get('helper')->GetAll($cls, $cls::$tableName, $request)));
    return $response;
})->setName("getUsers");

/**
     * @SWG\Post(
     *     path="/api/v1/User",
     *     description="Adds a new user",
     *     operationId="postUser",
     *     @SWG\Parameter(
     *         name="user",
     *         in="body",
     *         description="User to add",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewNewUser"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="User response",
     *         @SWG\Schema(
     *             ref="#/definitions/CommandResponse"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->post('/api/v1/User', function (Request $request, Response $response) {
	$objectArray = json_decode($request->getBody(), true);
	
	$pepper = $this->get('config')['pepper'];
	$pwd_peppered = hash_hmac("sha256", $objectArray['password'], $pepper);
	$pwd_hashed = password_hash($pwd_peppered, PASSWORD_ARGON2ID);
	
	$user = [];
	$user['email'] = $objectArray['email'];
    $user['hashedPassword'] = $pwd_hashed;
	
	$cls = User::class;
	try {
		$id = $this->get('helper')->Insert($cls, $user, $cls::$tableName);
	} catch (PDOException $e) {
		if ($e->getCode() == "23000")
		{
			$res = new CommandResponse();
			$res->code = 1;
			$res->message = $e->getMessage();
			$response->getBody()->write(json_encode($res));
			return $response;
		}
		throw $e;
	}

   
	$res = new CommandResponse();
	$res->code = 0;
	$res->message = "Created user";
	$response->getBody()->write(json_encode($res));
    return $response;
})->setName("postUser");

/**
     * @SWG\Patch(
     *     path="/api/v1/User",
     *     description="Updates a user",
     *     operationId="patchUser",
     *     @SWG\Parameter(
     *         name="user",
     *         in="body",
     *         description="User to add",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewUser"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="User response",
     *         @SWG\Schema(
     *             ref="#/definitions/CommandResponse"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
    $app->patch('/api/v1/User', function (Request $request, Response $response) {
        $objectArray = json_decode($request->getBody(), true);
          
        $user = [];
       // $user['email'] = $objectArray['email'];
        $user['isAdmin'] = $objectArray['isAdmin'];
        
        $cls = User::class;
        try {
            $selecValues = [];
            $selecValues['email'] = $objectArray['email'];
            $selectSql = 'SELECT * FROM Users WHERE email = :email';
            $userObj = $this->get('helper')->GetBySql($cls, $selectSql, $selecValues);
            $this->get('helper')->Update($cls, $user, $cls::$tableName, $userObj->id);
        } catch (PDOException $e) {
            if ($e->getCode() == "23000")
            {
                $res = new CommandResponse();
                $res->code = 1;
                $res->message = $e->getMessage();
                $response->getBody()->write(json_encode($res));
                return $response;
            }
            throw $e;
        }
    
       
        $res = new CommandResponse();
        $res->code = 0;
        $res->message = $userObj; //"User updated";
        $response->getBody()->write(json_encode($res));
        return $response;
    })->setName("patchUser");
    

# DEVICES
/**
     * @SWG\Get(
     *     path="/api/v1/Devices?sort={sort}&limit={limit}",
     *     description="Returns all devices",
     *     operationId="getDevices",
     *     @SWG\Parameter(
     *         description="columns to sort on",
     *         in="path",
     *         name="sort",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         description="limit number of results returned",
     *         in="path",
     *         name="limit",
     *         required=false,
     *         type="string"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/Device")
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/Devices', function ($request, $response) use ($app) {
	$cls = Device::class;
    $response->getBody()->write(json_encode($this->get('helper')->GetAll($cls, $cls::$tableName, $request)));
    return $response;

})->setName("getDevices");


/**
     * @SWG\Get(
     *     path="/api/v1/DevicesView?sort={sort}&limit={limit}&limitToHeadBTAddress={limitToHeadBTAddress}&limitToCompetitionId={limitToCompetitionId}",
     *     description="Returns all devices",
     *     operationId="getDevicesView",
     *     @SWG\Parameter(
     *         description="columns to sort on",
     *         in="path",
     *         name="sort",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         description="limit number of results returned",
     *         in="path",
     *         name="limit",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         description="limit to headBTAddress",
     *         in="path",
     *         name="limitToHeadBTAddress",
     *         required=false,
     *         type="string"
     *     ),
     *      @SWG\Parameter(
     *         description="limit to CompetitionId",
     *         in="path",
     *         name="limitToCompetitionId",
     *         required=false,
     *         type="string"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/DeviceView")
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/DevicesView', function ($request, $response) use ($app) {
	$cls = DeviceView::class;
	$sql = 'SELECT CASE WHEN Devices.reportTime > DATE_ADD(SYSDATE(), INTERVAL -6 MINUTE) THEN true ELSE false END recentlyReported, 
        CASE WHEN Devices.connectedToInternetTime > DATE_ADD(SYSDATE(), INTERVAL -1 MINUTE) THEN true ELSE false END connectedToInternet, 
        Devices.*, Competitions.name as competitionName FROM Devices LEFT JOIN Competitions ON Devices.competitionId = Competitions.id';
    $sqlParam = [];
    $sort = $this->get('helper')->getSort($cls, $request);
    $limit = $this->get('helper')->getLimit($request);
    $queryParams = $request->getQueryParams();
    $headBTAddress = $queryParams['limitToHeadBTAddress'] ?? '';
    $headBTAddress = $headBTAddress == 'undefined' ? '': $headBTAddress;
    $competitionId = $queryParams['limitToCompetitionId'] ?? '';
    $competitionId = $competitionId == 'undefined' ? '': $competitionId;
    
    if (trim($headBTAddress) != '' || trim($competitionId) != '') {
        $sql .= ' WHERE ';
    }
    if (trim($headBTAddress) != '') {
        $sql .= 'headBTAddress = :headBTAddress';
        $sqlParam['headBTAddress'] = $headBTAddress;
    }
    if (trim($headBTAddress) != '' && trim($competitionId) != '') {
        $sql .= ' AND ';
    }
    if (trim($competitionId) != '') {
        $sql .= 'competitionId = :competitionId';
        $sqlParam['competitionId'] = $competitionId; 
    }
    $sql .= $sort . ' ' . $limit;
    
    $response->getBody()->write(json_encode($this->get('helper')->GetAllBySql($cls, $sql, $sqlParam, $request)));
    return $response;

})->setName("getDevicesView");

/**
     * @SWG\get(
     *     path="/api/v1/Devices/{BTAddress}",
     *     description="Gets device by BT Address",
     *     operationId="getDevice",
     *     @SWG\Parameter(
     *         description="BT Address of the Device",
     *         in="path",
     *         name="BTAddress",
     *         required=true,
     *         type="string"
     *     ), 
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             ref="#/definitions/Device"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/Devices/{BTAddress}', function ($request, $response) {
    $BTAddress = $request->getAttribute('BTAddress');
    $cls = Device::class;
    $sql = "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress";
    $device = $this->get('helper')->GetBySql($cls, $sql, ['BTAddress'=>$BTAddress]);
    $response->getBody()->write(json_encode($device));
	return $response;
})->setName('getDevice');


/**
     * @SWG\Get(
     *     path="/api/v1/Devices/{BTAddress}/UpdateDeviceName/{deviceName}",
     *     description="Update deviceName, returns the device",
     *     operationId="getDeviceUpdateDeviceName",
     *     @SWG\Parameter(
     *         description="BT Address of device to update",
     *         in="path",
     *         name="BTAddress",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         description="Name",
     *         in="path",
     *         name="deviceName",
     *         required=true,
     *         type="string"
     *     ), 
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             ref="#/definitions/Device"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/Devices/{BTAddress}/UpdateDeviceName/{deviceName}', function (Request $request, Response $response) {
    $BTAddress = $request->getAttribute('BTAddress');
    $name = $request->getAttribute('deviceName');
	$cls = Device::class;
	$objectArray = [];
	$objectArray['name'] = $name;
	$objectArray['BTAddress'] = $BTAddress;
	$sql = "UPDATE {$cls::$tableName} SET `name` = :name, `nameUpdateTime` = NOW(), `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    $this->get('helper')->RunSql($sql, $objectArray);
    
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $btAddressUrl = str_replace(':','%3A', $objectArray['BTAddress']);
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $btAddressUrl]);
	return $response->withStatus(302)->withHeader('Location', $url);
})->setName('getDeviceUpdateDeviceName');



/**
 * @SWG\Get(
 *     path="/api/v1/Devices/{BTAddress}/SetBatteryIsLow",
 *     description="Set batteryIsLow, returns the device",
 *     operationId="getDeviceSetBatteryIsLow",
 *     @SWG\Parameter(
 *         description="BT Address of device to update",
 *         in="path",
 *         name="BTAddress",
 *         required=true,
 *         type="string"
 *     ), 
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="device response",
 *         @SWG\Schema(
 *             ref="#/definitions/Device"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/Devices/{BTAddress}/SetBatteryIsLow', function (Request $request, Response $response) {
    $BTAddress = $request->getAttribute('BTAddress');
    $cls = Device::class;
    $objectArray = [];
    $objectArray['batteryIsLow'] = 1;
    $objectArray['BTAddress'] = $BTAddress;
    $sql = "UPDATE {$cls::$tableName} SET `batteryIsLow` = :batteryIsLow, `batteryIsLowTime` = NOW(), `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    $this->get('helper')->RunSql($sql, $objectArray);
    
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $btAddressUrl = str_replace(':','%3A', $objectArray['BTAddress']);
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $btAddressUrl]);
	return $response->withStatus(302)->withHeader('Location', $url);
    
})->setName('getDeviceSetBatteryIsLow');


/**
 * @SWG\Get(
 *     path="/api/v1/Devices/{BTAddress}/SetBatteryIsNormal",
 *     description="Set batteryIsLow to false, returns the device",
 *     operationId="getDeviceSetBatteryIsNormal",
 *     @SWG\Parameter(
 *         description="BT Address of device to update",
 *         in="path",
 *         name="BTAddress",
 *         required=true,
 *         type="string"
 *     ), 
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="device response",
 *         @SWG\Schema(
 *             ref="#/definitions/Device"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/Devices/{BTAddress}/SetBatteryIsNormal', function (Request $request, Response $response) {
    $BTAddress = $request->getAttribute('BTAddress');
    $cls = Device::class;
    $objectArray = [];
    $objectArray['batteryIsLow'] = 0;
    $objectArray['BTAddress'] = $BTAddress;
    $sql = "UPDATE {$cls::$tableName} SET `batteryIsLow` = :batteryIsLow, `batteryIsLowTime` = NOW(), `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    $this->get('helper')->RunSql($sql, $objectArray);
    
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $btAddressUrl = str_replace(':','%3A', $objectArray['BTAddress']);
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $btAddressUrl]);
	return $response->withStatus(302)->withHeader('Location', $url);
})->setName('getDeviceSetBatteryIsNormal');


/**
 * @SWG\Get(
 *     path="/api/v1/Devices/{BTAddress}/SetBatteryIsLowReceived",
 *     description="Set batteryIsLowReceived, returns the device",
 *     operationId="getDeviceSetBatteryIsLowReceived",
 *     @SWG\Parameter(
 *         description="BT Address of device to update",
 *         in="path",
 *         name="BTAddress",
 *         required=true,
 *         type="string"
 *     ), 
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="device response",
 *         @SWG\Schema(
 *             ref="#/definitions/Device"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/Devices/{BTAddress}/SetBatteryIsLowReceived', function (Request $request, Response $response) {
    $BTAddress = $request->getAttribute('BTAddress');
    $cls = Device::class;
    $objectArray = [];
    $objectArray['batteryIsLowReceived'] = 1;
    $objectArray['BTAddress'] = $BTAddress;
    $sql = "UPDATE {$cls::$tableName} SET `batteryIsLowReceived` = :batteryIsLowReceived, `batteryIsLowReceivedTime` = NOW(), `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    $this->get('helper')->RunSql($sql, $objectArray);
    
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $btAddressUrl = str_replace(':','%3A', $objectArray['BTAddress']);
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $btAddressUrl]);
    return $response->withStatus(302)->withHeader('Location', $url);
})->setName('getDeviceSetBatteryIsLowReceived');


/**
 * @SWG\Get(
 *     path="/api/v1/Devices/{BTAddress}/SetBatteryIsNormalReceived",
 *     description="Set batteryIsNormalReceived, returns the device",
 *     operationId="getDeviceSetBatteryIsNormalReceived",
 *     @SWG\Parameter(
 *         description="BT Address of device to update",
 *         in="path",
 *         name="BTAddress",
 *         required=true,
 *         type="string"
 *     ), 
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="device response",
 *         @SWG\Schema(
 *             ref="#/definitions/Device"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/Devices/{BTAddress}/SetBatteryIsNormalReceived', function (Request $request, Response $response) {
    $BTAddress = $request->getAttribute('BTAddress');
    $cls = Device::class;
    $objectArray = [];
    $objectArray['batteryIsLowReceived'] = 0;
    $objectArray['BTAddress'] = $BTAddress;
    $sql = "UPDATE {$cls::$tableName} SET `batteryIsLowReceived` = :batteryIsLowReceived, `batteryIsLowReceivedTime` = NOW(), `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    $this->get('helper')->RunSql($sql, $objectArray);
    
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $btAddressUrl = str_replace(':','%3A', $objectArray['BTAddress']);
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $btAddressUrl]);
    return $response->withStatus(302)->withHeader('Location', $url);
})->setName('getDeviceSetBatteryIsNormalReceived');

/**
     * @SWG\Delete(
     *     path="/api/v1/Devices/{BTAddress}",
     *     description="Deletes a device",
     *     operationId="deleteDevice",
     *     @SWG\Parameter(
     *         description="BT address of device to delete",
     *         in="path",
     *         name="BTAddress",
     *         required=true,
     *         type="string"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=204,
     *         description="delete",
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->delete('/api/v1/Devices/{BTAddress}', function (Request $request, Response $response) {
    $BTAddress = $request->getAttribute('BTAddress');
    $cls = Device::class;
    $objectArray = [];
    $objectArray['BTAddress'] = $BTAddress;
	$this->get('helper')->DeleteBySql("DELETE FROM {$cls::$tableName} WHERE BTAddress = :BTAddress", $objectArray);
    return $response->withStatus(204);
})->setName('deleteDevice');

/**
     * @SWG\Post(
     *     path="/api/v1/Devices",
     *     description="Adds a new device",
     *     operationId="postDevice",
     *     @SWG\Parameter(
     *         name="device",
     *         in="body",
     *         description="Device to add to the store",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewDevice"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             ref="#/definitions/Device"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->post('/api/v1/Devices', function (Request $request, Response $response) {
    $objectArray = json_decode($request->getBody(), true);
    $objectArrayForSelect = [];
    if ($objectArray['BTAddress'] == "NoBTAddress") {
        return $response->withStatus(400);
    }

    $objectArrayForSelect['BTAddress'] = $objectArray['BTAddress'];
	$cls = Device::class;
    $id = $this->get('helper')->InsertOrUpdate($cls, $objectArray, $cls::$tableName, "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress", $objectArrayForSelect);
    
    $objectArrayForUpdateReportTime = [];
    $objectArrayForUpdateReportTime['BTAddress'] = $objectArray['BTAddress'];
    $sql = "UPDATE {$cls::$tableName} SET `reportTime` = NOW(), `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    $this->get('helper')->RunSql($sql, $objectArrayForUpdateReportTime);
    
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $btAddressUrl = str_replace(':','%3A', $objectArray['BTAddress']);
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $btAddressUrl]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postDevices");

/**
     * @SWG\Post(
     *     path="/api/v1/Devices/{BTAddress}/SetConnectedToInternetTime",
     *     description="Set the connectedToInternetTime property to sysdate",
     *     operationId="postDeviceSetConnectedToInternetTime",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         description="BT Address of device to update",
     *         in="path",
     *         name="BTAddress",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             ref="#/definitions/Device"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->post('/api/v1/Devices/{BTAddress}/SetConnectedToInternetTime', function (Request $request, Response $response) {
    $BTAddress = $request->getAttribute('BTAddress');
    $cls = Device::class;
    
	$objectArray = [];
	$objectArray['BTAddress'] = $BTAddress;
	$sql = "UPDATE {$cls::$tableName} SET `connectedToInternetTime` = NOW(), `reportTime` = NOW(), `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    $this->get('helper')->RunSql($sql, $objectArray);
  	
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $btAddressUrl = str_replace(':','%3A', $BTAddress);
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $btAddressUrl]);
	return $response->withStatus(303)->withHeader('Location', $url);

})->setName("postDeviceSetConnectedToInternetTime");


/**
     * @SWG\Post(
     *     path="/api/v1/Devices/{BTAddress}/SetCompetition",
     *     description="Set the competitionId property",
     *     operationId="postDeviceSetCompetition",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="device",
     *         in="body",
     *         description="CompetitionId to add to the device",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/DeviceAddToCompetition"),
     *     ),
     *     @SWG\Parameter(
     *         description="BT Address of device to update",
     *         in="path",
     *         name="BTAddress",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             ref="#/definitions/Device"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
    $app->post('/api/v1/Devices/{BTAddress}/SetCompetition', function (Request $request, Response $response) {
        $objectArray = json_decode($request->getBody(), true);
        $BTAddress = $request->getAttribute('BTAddress');
        $cls = Device::class;
        
        if (!isset($_SESSION['userId'])) {
            return $response->withStatus(401);
        }

        $updateObjectArray = [];
        $updateObjectArray['BTAddress'] = $BTAddress;
        $updateObjectArray['competitionId'] = $objectArray['competitionId'];
        $userId = $_SESSION['userId'];
        $updateObjectArray['competitionIdSetByUserId'] = $userId;
        
        $sql = "UPDATE {$cls::$tableName} SET `competitionId` = :competitionId, `competitionIdSetByUserId` = :competitionIdSetByUserId, `updateTime` = NOW() WHERE BTAddress = :BTAddress";
        $this->get('helper')->RunSql($sql, $updateObjectArray);
          
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $btAddressUrl = str_replace(':','%3A', $BTAddress);
        $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $btAddressUrl]);
        return $response->withStatus(303)->withHeader('Location', $url);
    })->setName("postDeviceSetCompetition");



# DEVICESTATUS 
/**
     * @SWG\Get(
     *     path="/api/v1/Devices/{BTAddress}/DeviceStatuses?sort={sort}&limit={limit}&limitToCreatedTimeWithinSeconds={limitSeconds}",
     *     description="Returns all statuses of a device",
     *     operationId="getDeviceStatuses",
     *     @SWG\Parameter(
     *         description="BTAddress of device to get statuses for",
     *         in="path",
     *         name="BTAddress",
     *         required=true,
     *         type="string"
     *     ), 
     *     @SWG\Parameter(
     *         description="columns to sort on",
     *         in="path",
     *         name="sort",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         description="limit number of results returned",
     *         in="path",
     *         name="limit",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         description="limit results to those created within the last x seconds",
     *         in="path",
     *         name="limitToCreatedTimeWithinSeconds",
     *         required=false,
     *         type="string"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/DeviceStatus")
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/Devices/{BTAddress}/DeviceStatuses', function ($request, $response) {
    $BTAddress = $request->getAttribute('BTAddress');
    $cls = DeviceStatus::class;
    $sort = $this->get('helper')->getSort($cls, $request);
    $limit = $this->get('helper')->getLimit($request);
    $createdTimeLimit = $this->get('helper')->getCreatedTimeLimit($request);
    if ($createdTimeLimit != "") {
        $createdTimeLimit = " and " . $createdTimeLimit . " ";
    }
    $sql = "SELECT DeviceStatuses.* FROM DeviceStatuses WHERE DeviceStatuses.BTAddress = :BTAddress " . $createdTimeLimit . $sort . " " . $limit;
    $deviceStatuses = $this->get('helper')->GetAllBySql($cls, $sql, ['BTAddress'=>$BTAddress], $request);
    $response->getBody()->write(json_encode($deviceStatuses));
    return $response;
})->setName("getDeviceStatusesByBTAddress");


/**
     * @SWG\Get(
     *     path="/api/v1/DeviceStatuses?sort={sort}&limit={limit}",
     *     description="Returns DeviceStatuses",
     *     operationId="getDeviceStatuses",
     *     @SWG\Parameter(
     *         description="columns to sort on",
     *         in="path",
     *         name="sort",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         description="limit number of results returned",
     *         in="path",
     *         name="limit",
     *         required=false,
     *         format="int64",
     *         type="integer"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="DeviceStatuses response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/DeviceStatus")
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/DeviceStatuses', function ($request, $response) {
	$cls = DeviceStatus::class;
    $response->getBody()->write(json_encode($this->get('helper')->GetAll($cls, $cls::$tableName, $request)));
    return $response;
})->setName("getDeviceStatuses");



/**
     * @SWG\Get(
     *     path="/api/v1/DeviceStatuses/{deviceStatusId}",
     *     description="Returns a deviceStatus",
     *     operationId="getDeviceStatus",
     *     @SWG\Parameter(
     *         description="ID of the deviceStatus",
     *         format="int64",
     *         in="path",
     *         name="deviceStatusId",
     *         required=true,
     *         type="integer"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="deviceStatuses response",
     *         @SWG\Schema(
     *             ref="#/definitions/DeviceStatus"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/DeviceStatuses/{deviceStatusId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('deviceStatusId');
    $cls = DeviceStatus::class;
    $deviceStatus2 = $this->get('helper')->Get($cls, $cls::$tableName, $id);
    if ($deviceStatus2 == false) 
    {
        
        return $response->withStatus(404);
    }
    $response->getBody()->write(json_encode($deviceStatus2));
    return $response;
})->setName("getDeviceStatus");



/**
     * @SWG\Post(
     *     path="/api/v1/DeviceStatuses",
     *     description="Adds a new deviceStatus",
     *     operationId="postDeviceStatus",
     *     @SWG\Parameter(
     *         name="deviceStatus",
     *         in="body",
     *         description="Device to add to the store",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewDeviceStatus"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="DeviceStatus response",
     *         @SWG\Schema(
     *             ref="#/definitions/DeviceStatus"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->post('/api/v1/DeviceStatuses', function (Request $request, Response $response) {
	$objectArray = json_decode($request->getBody(), true);
	$cls = DeviceStatus::class;
    $id = $this->get('helper')->Insert($cls, $objectArray, $cls::$tableName);
    
    $objectArray2 = [];
	$objectArray2['BTAddress'] = $objectArray['BTAddress'];
	$clsDevice = Device::class;
	$sql = "UPDATE {$clsDevice::$tableName} SET `reportTime` = NOW(), `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    $this->get('helper')->RunSql($sql, $objectArray2);
    
   
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $url = $routeParser->relativeUrlFor('getDeviceStatus', ['deviceStatusId' => $id]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postDeviceStatus");

/**
     * @SWG\Delete(
     *     path="/api/v1/Devices/{BTAddress}/DeviceStatuses/DeleteByBTAddress",
     *     description="Deletes DeviceStatuses for a device",
     *     operationId="deleteDeviceStatusesByBTAddress",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         description="bt address",
     *         in="path",
     *         name="BTAddress",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=204,
     *         description="deleted",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->delete('/api/v1/Devices/{BTAddress}/DeviceStatuses/DeleteByBTAddress', function (Request $request, Response $response) {
	$BTAddress = $request->getAttribute('BTAddress');
    $objectArray = [];
    if ($BTAddress == 'all') {
		$this->get('helper')->DeleteBySql('DELETE FROM DeviceStatuses', $objectArray);
	} else {
		$objectArray['BTAddress'] = $BTAddress;
		$this->get('helper')->DeleteBySql('DELETE FROM DeviceStatuses WHERE BTAddress = :BTAddress', $objectArray);
	}
    return $response->withStatus(204);
})->setName("deleteDeviceStatusesByBTAddress");


# MESSAGESTATS
/**
     * @SWG\Get(
     *     path="/api/v1/Devices/{BTAddress}/MessageStats?sort={sort}&limit={limit}&outputType={outputType}",
     *     description="Returns MessageStats",
     *     operationId="getMessageStatsOfADevice",
     *     @SWG\Parameter(
     *         description="BTAddress of device to get stats for",
     *         in="path",
     *         name="BTAddress",
     *         required=true,
     *         type="string"
     *     ), 
     *     @SWG\Parameter(
     *         description="columns to sort on",
     *         in="path",
     *         name="sort",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         description="limit number of results returned",
     *         in="path",
     *         name="limit",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         description="outputType, aggregated | normal",
     *         in="path",
     *         name="outputType",
     *         required=false,
     *         type="string"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="MessageStats response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/MessageStat")
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/Devices/{BTAddress}/MessageStats', function (Request $request, Response $response) {
	$cls = MessageStat::class;
    $BTAddress = $request->getAttribute('BTAddress');
    $sort = $this->get('helper')->getSort($cls, $request);
    $limit = $this->get('helper')->getLimit($request);
    $queryParams = $request->getQueryParams();
    $outputType = $queryParams['outputType'];
    $sql = '';
    if (strtolower($outputType) == 'aggregated') {
		$sql = "SELECT MessageStats.BTAddress, MessageStats.adapterInstance, MessageStats.messageType, 
			(SELECT MessageStats.status FROM MessageStats ims WHERE ims.BTAddress = MessageStats.BTAddress 
			and ims.adapterInstance = MessageStats.adapterInstance and ims.messageType = MessageStats.messageType ORDER BY ims.createdTime desc LIMIT 1) as status, 
			sum(MessageStats.noOfMessages) as noOfMessages, max(MessageStats.updateTime) as updateTime, max(MessageStats.createdTime) as createdTime 
			FROM MessageStats WHERE MessageStats.BTAddress = :BTAddress GROUP BY MessageStats.BTAddress, MessageStats.adapterInstance, 
			MessageStats.messageType, status " . $sort . " " . $limit;
	} else {
		$sql = "SELECT MessageStats.* FROM MessageStats WHERE MessageStats.BTAddress = :BTAddress " . $sort . " " . $limit;
	}
    $deviceStats = $this->get('helper')->GetAllBySql($cls, $sql, ['BTAddress'=>$BTAddress], $request);
    $response->getBody()->write(json_encode($deviceStats));
    return $response;
})->setName("getMessageStatsOfADevice");



/**
     * @SWG\Get(
     *     path="/api/v1/MessageStats?sort={sort}",
     *     description="Returns MessageStats",
     *     operationId="getMessageStats",
     *     @SWG\Parameter(
     *         description="columns to sort on",
     *         in="path",
     *         name="sort",
     *         required=false,
     *         type="string"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="MessageStats response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/MessageStat")
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/MessageStats', function (Request $request, Response $response) {
	$cls = MessageStat::class;
    $response->getBody()->write(json_encode($this->get('helper')->GetAll($cls, $cls::$tableName, $request)));
    return $response;
})->setName("getMessageStats");

/**
     * @SWG\Get(
     *     path="/api/v1/MessageStats/{statId}",
     *     description="Returns an MessageStat",
     *     operationId="getMessageStat",
     *     @SWG\Parameter(
     *         description="ID of the MessageStat",
     *         format="int64",
     *         in="path",
     *         name="statId",
     *         required=true,
     *         type="integer"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="MessageStat response",
     *         @SWG\Schema(
     *             ref="#/definitions/MessageStat"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->get('/api/v1/MessageStats/{statId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('statId');
    $cls = MessageStat::class;
    $messageStat = $this->get('helper')->Get($cls, $cls::$tableName, $id);
    if ($messageStat == false) 
    {
        
        return $response->withStatus(404);
    }
    $response->getBody()->write(json_encode($messageStat));
    return $response;
})->setName("getMessageStat");

/**
     * @SWG\Post(
     *     path="/api/v1/MessageStats",
     *     description="Adds a new MessageStat",
     *     operationId="postMessageStat",
     *     @SWG\Parameter(
     *         name="MessageStat",
     *         in="body",
     *         description="MessageStat to add to the store",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewMessageStat"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="MessageStat response",
     *         @SWG\Schema(
     *             ref="#/definitions/MessageStat"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->post('/api/v1/MessageStats', function (Request $request, Response $response) {
	$objectArray = json_decode($request->getBody(), true);
	$cls = MessageStat::class;
    $id = $this->get('helper')->Insert($cls, $objectArray, $cls::$tableName);
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $url = $routeParser->relativeUrlFor('getMessageStat', ['statId' => $id]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postMessageStat");


/**
     * @SWG\Delete(
     *     path="/api/v1/Devices/{BTAddress}/MessageStats/DeleteByBTAddress",
     *     description="Deletes MessageStats for a device",
     *     operationId="deleteMessageStatsByBTAddress",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         description="bt address",
     *         in="path",
     *         name="BTAddress",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=204,
     *         description="deleted",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->delete('/api/v1/Devices/{BTAddress}/MessageStats/DeleteByBTAddress', function (Request $request, Response $response) {
	$BTAddress = $request->getAttribute('BTAddress');
    $objectArray = [];
    if ($BTAddress == 'all') {
		$this->get('helper')->DeleteBySql('DELETE FROM MessageStats', $objectArray);
	} else {
		$objectArray['BTAddress'] = $BTAddress;
		$this->get('helper')->DeleteBySql('DELETE FROM MessageStats WHERE BTAddress = :BTAddress', $objectArray);
	}
    return $response->withStatus(204);
})->setName("deleteMessageStatsByBTAddress");



/**
     * @SWG\Post(
     *     path="/api/v1/LogArchives",
     *     description="Upload a logarchive (zip file with logs and database): curl -X POST ""https://monitor.wiroc.se/api/v1/LogArchives"" -H ""accept: application/json"" -H ""Authorization: <apikey>"" -F ""newfile=@/path/to/zipfile.zip""",
     *     operationId="postLogArchives",
     *     @SWG\Response(
     *         response=200,
     *         description="",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->post('/api/v1/LogArchives', function (Request $request, Response $response, $args) {
    $files = $request->getUploadedFiles();
    if (empty($files['newfile'])) {
        throw new Exception('Expected a newfile');
    }
 
    $newfile = $files['newfile'];
    $target_dir = $this->get('config')['upload']['log_archive_upload_directory'];
    if ($newfile->getSize() > 15000000) {
		throw new Exception('File is too large!');
	}
	$uploadFileName = $newfile->getClientFilename();
	$imageFileType = strtolower(pathinfo($uploadFileName,PATHINFO_EXTENSION));
	#if ($imageFileType !== 'zip') {
	#	throw new Exception('Not a zip file!');
	#}
    if ($newfile->getError() === UPLOAD_ERR_OK) {
		$newfile->moveTo("$target_dir$uploadFileName");
	}
	return $response;
})->setName("postLogArchives");


# WiRocPython2Releases
/**
 * @SWG\Get(
 *     path="/api/v1/WiRocPython2Releases?sort={sort}&limit={limit}&hwVersion={hwVersion}&hwRevision={hwRevision}",
 *     description="Returns all releases of WiRocPython2",
 *     operationId="getWiRocPython2Releases",
 *     @SWG\Parameter(
*         description="columns to sort on",
*         in="path",
*         name="sort",
*         required=false,
*         type="string"
*     ),
*     @SWG\Parameter(
*         description="limit number of results returned",
*         in="path",
*         name="limit",
*         required=false,
*         type="string"
*     ),
*     @SWG\Parameter(
*         description="only return the ones relevant to hwVersion",
*         in="path",
*         name="hwVersion",
*         required=false,
*         type="string"
*     ),
*     @SWG\Parameter(
*         description="only return the ones relevant to hwRevision",
*         in="path",
*         name="hwRevision",
*         required=false,
*         type="string"
*     ),
*     produces={"application/json"},
*     @SWG\Response(
*         response=200,
*         description="WiRocPython2Releases response",
*         @SWG\Schema(
*             type="array",
*             @SWG\Items(ref="#/definitions/WiRocPython2Release")
*         ),
*     ),
*     @SWG\Response(
*         response="default",
*         description="unexpected error",
*         @SWG\Schema(
*             ref="#/definitions/ErrorModel"
*         )
*     ),
*     security={
*       {"api_key": {}}
*     }
* )
*/
$app->get('/api/v1/WiRocPython2Releases', function ($request, $response) use ($app) {
    $cls = WiRocPython2Release::class;
    $limit = $this->get('helper')->getLimit($request);
    $sort = $this->get('helper')->getSort($cls, $request);
    $hwVersion = $request->getQueryParams()['hwVersion'] ?? '';
    $hwRevision = $request->getQueryParams()['hwRevision'] ?? '';
    $sqlParams = [];
    $sql = 'SELECT WiRocPython2Releases.*, ReleaseStatuses.displayName as releaseStatusDisplayName, ReleaseStatuses.keyName as releaseStatusKeyName FROM WiRocPython2Releases LEFT JOIN ReleaseStatuses ON WiRocPython2Releases.releaseStatusId = ReleaseStatuses.id';
    if (ctype_digit($hwVersion) || ctype_digit($hwRevision))
    {
        $sql .= ' WHERE ';
    }
    if (ctype_digit($hwVersion))
    {
        $sql .= ' WiRocPython2Releases.minHWVersion <= :hwVersion and :hwVersion <= WiRocPython2Releases.maxHWVersion';
        $sqlParams['hwVersion'] = $hwVersion;
    }
    if (ctype_digit($hwVersion) && ctype_digit($hwRevision))
    {
        $sql .= ' and ';
    }
    if (ctype_digit($hwRevision))
    {
       $sql .= '(WiRocPython2Releases.minHWVersion < :hwVersion  or (WiRocPython2Releases.minHWVersion = :hwVersion and WiRocPython2Releases.minHWRevision <= :hwRevision))';
       $sql .= ' and (WiRocPython2Releases.maxHWVersion > :hwVersion or (:hwVersion = WiRocPython2Releases.maxHWVersion and :hwRevision <= WiRocPython2Releases.maxHWRevision))';
 
       $sqlParams['hwRevision'] = $hwRevision;
    }
    $sql .= $sort . ' ' . $limit;

    $response->getBody()->write(json_encode($this->get('helper')->GetAllBySql($cls, $sql, $sqlParams, $request)));
    return $response;
})->setName("getWiRocPython2Releases");

/**
     * @SWG\Get(
     *     path="/api/v1/WiRocPython2Releases/{releaseId}",
     *     description="Gets a WiRocPython2Release",
     *     operationId="getWiRocPython2Release",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         description="ID of the WiRocPython2Release",
     *         format="int64",
     *         in="path",
     *         name="releaseId",
     *         required=true,
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="WiRocPython2Release response",
     *         @SWG\Schema(
     *             ref="#/definitions/WiRocPython2Release"
     *         ),
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(
     *             ref="#/definitions/ErrorModel"
     *         )
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
    $app->get('/api/v1/WiRocPython2Releases/{releaseId}', function (Request $request, Response $response) {
        $cls = WiRocPython2Release::class;
        $id = $request->getAttribute('releaseId');
        $release = $this->get('helper')->Get($cls, $cls::$tableName, $id);
        if ($release == false) 
        {
            
            return $response->withStatus(404);
        }
        $response->getBody()->write(json_encode($release));
        return $response;
    })->setName("getWiRocPython2Release");


/**
 * @SWG\Post(
 *     path="/api/v1/WiRocPython2Releases",
 *     description="Adds a new WiRocPython2Release",
 *     operationId="postWiRocPython2Release",
 *     @SWG\Parameter(
 *         name="WiRocPython2Release",
 *         in="body",
 *         description="WiRocPython2Release to add to the store",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/NewWiRocPython2Release"),
 *     ),
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="WiRocPython2Release response",
 *         @SWG\Schema(
 *             ref="#/definitions/WiRocPython2Release"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->post('/api/v1/WiRocPython2Releases', function (Request $request, Response $response) {
    $cls = WiRocPython2Release::class;
    $objectArray = json_decode($request->getBody(), true);
    
    $urlToReleasePackage = 'https://github.com/henla464/WiRoc-Python-2/archive/v' . $objectArray['versionNumber'] . '.tar.gz';
    // Use basename() function to return the base name of file
    $localFilePath = "WiRocPython2ReleasePackages/" . basename($urlToReleasePackage);
      
    // Use file_get_contents() function to get the file
    // from url and use file_put_contents() function to
    // save the file by using base name
    if (file_put_contents($localFilePath, file_get_contents($urlToReleasePackage)))
    {
        // File downloaded successfully
        $md5Hash = md5_file($localFilePath);
        $objectArray['md5HashOfReleaseFile'] = $md5Hash;

        $objectArrayForSelect = [];
        if (array_key_exists("id", $objectArray)) {
            $objectArrayForSelect['id'] = $objectArray['id'];
        } else {
            $objectArrayForSelect['id'] = -1;
        }
        $id = $this->get('helper')->InsertOrUpdate($cls, $objectArray, $cls::$tableName, "SELECT * FROM {$cls::$tableName} WHERE id = :id", $objectArrayForSelect);
    
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $url = $routeParser->relativeUrlFor('getWiRocPython2Release', ['releaseId' => $id]);
        return $response->withStatus(303)->withHeader('Location', $url);
    }
    else
    {
        throw new Exception('File downloading failed.');
    }

    
})->setName("postWiRocPython2Release");


/**
 * @SWG\Delete(
 *     path="/api/v1/WiRocPython2Releases/{releaseId}",
 *     description="Delete a WiRocPython2Release",
 *     operationId="deleteWiRocPython2Release",
 *     @SWG\Parameter(
 *         description="ID of the WiRocPython2Release",
 *         format="int64",
 *         in="path",
 *         name="releaseId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=204,
 *         description="deleted",
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->delete('/api/v1/WiRocPython2Releases/{releaseId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('releaseId');
    $cls = WiRocPython2Release::class;
    $this->get('helper')->Delete($id, $cls::$tableName);
    return $response->withStatus(204);
})->setName("deleteWiRocPython2Release");

# WiRocBLEAPIReleases
/**
 * @SWG\Get(
 *     path="/api/v1/WiRocBLEAPIReleases?sort={sort}&limit={limit}&hwVersion={hwVersion}&hwRevision={hwRevision}",
 *     description="Returns all releases of WiRocBLEAPI",
 *     operationId="getWiRocBLEAPIReleases",
 *     @SWG\Parameter(
 *         description="columns to sort on",
 *         in="path",
 *         name="sort",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Parameter(
 *         description="limit number of results returned",
 *         in="path",
 *         name="limit",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Parameter(
 *         description="only return the ones relevant to hwVersion",
 *         in="path",
 *         name="hwVersion",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Parameter(
 *         description="only return the ones relevant to hwRevision",
 *         in="path",
 *         name="hwRevision",
 *         required=false,
 *         type="string"
 *     ),
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="WiRocBLEAPIReleases response",
 *         @SWG\Schema(
 *             type="array",
 *             @SWG\Items(ref="#/definitions/WiRocBLEAPIRelease")
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/WiRocBLEAPIReleases', function ($request, $response) use ($app) {
    $cls = WiRocBLEAPIRelease::class;
    $limit = $this->get('helper')->getLimit($request);
    $sort = $this->get('helper')->getSort($cls, $request);
    $hwVersion = $request->getQueryParams()['hwVersion'] ?? '';
    $hwRevision = $request->getQueryParams()['hwRevision'] ?? '';
    $sqlParams = [];
    $sql = 'SELECT WiRocBLEAPIReleases.*, ReleaseStatuses.displayName as releaseStatusDisplayName, ReleaseStatuses.keyName as releaseStatusKeyName FROM WiRocBLEAPIReleases LEFT JOIN ReleaseStatuses ON WiRocBLEAPIReleases.releaseStatusId = ReleaseStatuses.id';
    if (ctype_digit($hwVersion) || ctype_digit($hwRevision))
    {
        $sql .= ' WHERE ';
    }
    if (ctype_digit($hwVersion))
    {
        $sql .= ' WiRocBLEAPIReleases.minHWVersion <= :hwVersion and :hwVersion <= WiRocBLEAPIReleases.maxHWVersion';
        $sqlParams['hwVersion'] = $hwVersion;
    }
    if (ctype_digit($hwVersion) && ctype_digit($hwRevision))
    {
        $sql .= ' and ';
    }
    if (ctype_digit($hwRevision))
    {
        $sql .= '(WiRocBLEAPIReleases.minHWVersion < :hwVersion  or (WiRocBLEAPIReleases.minHWVersion = :hwVersion and WiRocBLEAPIReleases.minHWRevision <= :hwRevision))';
        $sql .= ' and (WiRocBLEAPIReleases.maxHWVersion > :hwVersion or (:hwVersion = WiRocBLEAPIReleases.maxHWVersion and :hwRevision <= WiRocBLEAPIReleases.maxHWRevision))';
        $sqlParams['hwRevision'] = $hwRevision;
    }
    $sql .= $sort . ' ' . $limit;

    $response->getBody()->write(json_encode($this->get('helper')->GetAllBySql($cls, $sql, $sqlParams , $request)));
    return $response;
})->setName("getWiRocBLEAPIReleases");

/**
 * @SWG\Get(
 *     path="/api/v1/WiRocBLEAPIReleases/{releaseId}",
 *     description="Gets a WiRocBLEAPIRelease",
 *     operationId="getWiRocBLEAPIRelease",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="ID of the WiRocBLEAPIRelease",
 *         format="int64",
 *         in="path",
 *         name="releaseId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="WiRocBLEAPIRelease response",
 *         @SWG\Schema(
 *             ref="#/definitions/WiRocBLEAPIRelease"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/WiRocBLEAPIReleases/{releaseId}', function (Request $request, Response $response) {
    $cls = WiRocBLEAPIRelease::class;
    $id = $request->getAttribute('releaseId');
    $release = $this->get('helper')->Get($cls, $cls::$tableName, $id);
    if ($release == false) 
    {
        return $response->withStatus(404);
    }
    $response->getBody()->write(json_encode($release));
    return $response;
})->setName("getWiRocBLEAPIRelease");


/**
 * @SWG\Post(
 *     path="/api/v1/WiRocBLEAPIReleases",
 *     description="Adds a new WiRocBLEAPIRelease",
 *     operationId="postWiRocBLEAPIRelease",
 *     @SWG\Parameter(
 *         name="WiRocBLEAPIRelease",
 *         in="body",
 *         description="WiRocBLEAPIRelease to add to the store",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/NewWiRocBLEAPIRelease"),
 *     ),
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="WiRocBLEAPIRelease response",
 *         @SWG\Schema(
 *             ref="#/definitions/WiRocBLEAPIRelease"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->post('/api/v1/WiRocBLEAPIReleases', function (Request $request, Response $response) {
    $cls = WiRocBLEAPIRelease::class;
    $objectArray = json_decode($request->getBody(), true);
    $urlToReleasePackage = 'https://github.com/henla464/WiRoc-BLE-API/archive/v' . $objectArray['versionNumber'] . '.tar.gz';
    // Use basename() function to return the base name of file
    $localFilePath = "WiRocBLEAPIReleasePackages/" . basename($urlToReleasePackage);
      
    // Use file_get_contents() function to get the file
    // from url and use file_put_contents() function to
    // save the file by using base name
    if (file_put_contents($localFilePath, file_get_contents($urlToReleasePackage)))
    {
        // File downloaded successfully
        $md5Hash = md5_file($localFilePath);
        $objectArray['md5HashOfReleaseFile'] = $md5Hash;
            
        $objectArrayForSelect = [];
        if (array_key_exists("id", $objectArray)) {
            $objectArrayForSelect['id'] = $objectArray['id'];
        } else {
            $objectArrayForSelect['id'] = -1;
        }
        $id = $this->get('helper')->InsertOrUpdate($cls, $objectArray, $cls::$tableName, "SELECT * FROM {$cls::$tableName} WHERE id = :id", $objectArrayForSelect);
        

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $url = $routeParser->relativeUrlFor('getWiRocBLEAPIRelease', ['releaseId' => $id]);
        return $response->withStatus(303)->withHeader('Location', $url);
    }
    else
    {
        throw new Exception('File downloading failed.');
    }
})->setName("postWiRocBLEAPIRelease");


/**
 * @SWG\Delete(
 *     path="/api/v1/WiRocBLEAPIReleases/{releaseId}",
 *     description="Delete a WiRocBLEAPIRelease",
 *     operationId="deleteWiRocBLEAPIRelease",
 *     @SWG\Parameter(
 *         description="ID of the WiRocBLEAPIRelease",
 *         format="int64",
 *         in="path",
 *         name="releaseId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=204,
 *         description="deleted",
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->delete('/api/v1/WiRocBLEAPIReleases/{releaseId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('releaseId');
    $cls = WiRocBLEAPIRelease::class;
    $this->get('helper')->Delete($id, $cls::$tableName);
    return $response->withStatus(204);
})->setName("deleteWiRocBLEAPIRelease");

    

# ReleaseStatus
/**
 * @SWG\Get(
 *     path="/api/v1/ReleaseStatuses?sort={sort}&limit={limit}",
 *     description="Returns all release statuses",
 *     operationId="getReleaseStatuses",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="columns to sort on",
 *         in="path",
 *         name="sort",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Parameter(
 *         description="limit number of results returned",
 *         in="path",
 *         name="limit",
 *         required=false,
 *         format="int64",
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="ReleaseStatuses response",
 *         @SWG\Schema(
 *             type="array",
 *             @SWG\Items(ref="#/definitions/ReleaseStatus")
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/ReleaseStatuses', function ($request, $response) use ($app) {
    $cls = ReleaseStatus::class;
    $response->getBody()->write(json_encode($this->get('helper')->GetAll($cls, $cls::$tableName, $request)));
    return $response;
})->setName("getReleaseStatuses");

/**
 * @SWG\Get(
 *     path="/api/v1/ReleaseStatuses/{releaseStatusId}",
 *     description="Gets a ReleaseStatus",
 *     operationId="getReleaseStatus",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="ID of the ReleaseStatus",
 *         format="int64",
 *         in="path",
 *         name="releaseStatusId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="ReleaseStatus response",
 *         @SWG\Schema(
 *             ref="#/definitions/ReleaseStatus"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/ReleaseStatuses/{releaseStatusId}', function (Request $request, Response $response) {
    $cls = ReleaseStatus::class;
    $id = $request->getAttribute('releaseStatusId');
    $releaseStatus = $this->get('helper')->Get($cls, $cls::$tableName, $id);
    if ($releaseStatus == false) 
    {
        
        return $response->withStatus(404);
    }
    $response->getBody()->write(json_encode($releaseStatus));
    return $response;
})->setName("getReleaseStatus");


/**
 * @SWG\Post(
 *     path="/api/v1/ReleaseStatuses",
 *     description="Adds a new ReleaseStatus",
 *     operationId="postReleaseStatus",
 *     @SWG\Parameter(
 *         name="ReleaseStatus",
 *         in="body",
 *         description="ReleaseStatus to add to the store",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/NewReleaseStatus"),
 *     ),
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="ReleaseStatus response",
 *         @SWG\Schema(
 *             ref="#/definitions/ReleaseStatus"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->post('/api/v1/ReleaseStatuses', function (Request $request, Response $response) {
    $cls = ReleaseStatus::class;
    $objectArray = json_decode($request->getBody(), true);
    
    $id = $this->get('helper')->Insert($cls, $objectArray, $cls::$tableName);
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $url = $routeParser->relativeUrlFor('getReleaseStatus', ['releaseStatusId' => $id]);
    return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postReleaseStatus");


/**
 * @SWG\Delete(
 *     path="/api/v1/ReleaseStatuses/{releaseStatusId}",
 *     description="Delete a ReleaseStatus",
 *     operationId="deleteReleaseStatus",
 *     @SWG\Parameter(
 *         description="ID of the ReleaseStatus",
 *         format="int64",
 *         in="path",
 *         name="releaseStatusId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=204,
 *         description="deleted",
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->delete('/api/v1/ReleaseStatuses/{releaseStatusId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('releaseStatusId');
    $cls = ReleaseStatus::class;
    $this->get('helper')->Delete($id, $cls::$tableName);
    return $response->withStatus(204);
})->setName("deleteReleaseStatus");


# WiRocBLEAPIReleaseUpgradeScripts
/**
 * @SWG\Get(
 *     path="/api/v1/WiRocBLEAPIReleaseUpgradeScripts?sort={sort}&limit={limit}&limitFromVersion={limitFromVersion}&limitToVersion={limitToVersion}",
 *     description="Returns all WiRocBLEAPIReleaseUpgradeScripts",
 *     operationId="getWiRocBLEAPIReleaseUpgradeScripts",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="columns to sort on",
 *         in="path",
 *         name="sort",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Parameter(
 *         description="limit number of results returned",
 *         in="path",
 *         name="limit",
 *         required=false,
 *         format="int64",
 *         type="integer"
 *     ),
 *     @SWG\Parameter(
 *         description="only return those belonging to version number > limitFromVersion",
 *         in="path",
 *         name="limitFromVersion",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Parameter(
 *         description="only return those belonging to version number <= limitToVersion",
 *         in="path",
 *         name="limitToVersion",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="WiRocBLEAPIReleaseUpgradeScripts response",
 *         @SWG\Schema(
 *             type="array",
 *             @SWG\Items(ref="#/definitions/WiRocBLEAPIReleaseUpgradeScript")
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/WiRocBLEAPIReleaseUpgradeScripts', function ($request, $response) use ($app) {
    $cls = WiRocBLEAPIReleaseUpgradeScript::class;
    $limitFromVersion = $request->getQueryParams()['limitFromVersion'] ?? '';
    $limitFromVersion = ltrim($limitFromVersion, 'v');
    $limitFromVersion = ltrim($limitFromVersion, 'V');
    $limitToVersion = $request->getQueryParams()['limitToVersion'] ?? '';
    $limitToVersion = ltrim($limitToVersion, 'v');
    $limitToVersion = ltrim($limitToVersion, 'V');

    $sort = $this->get('helper')->getSort($cls, $request);
    $limit = $this->get('helper')->getLimit($request);
  
    $sql = 'SELECT WiRocBLEAPIReleaseUpgradeScripts.*, WiRocBLEAPIReleases.releaseName, WiRocBLEAPIReleases.versionNumber 
    FROM WiRocBLEAPIReleaseUpgradeScripts JOIN WiRocBLEAPIReleases 
    ON WiRocBLEAPIReleaseUpgradeScripts.releaseId = WiRocBLEAPIReleases.id';
    $sqlParam = [];
    if ($limitFromVersion != '' && $limitToVersion != '') {
        $sql .= ' WHERE versionNumber > :limitFromVersion AND versionNumber <= :limitToVersion';
        $sqlParam['limitFromVersion'] = $limitFromVersion;
        $sqlParam['limitToVersion'] = $limitToVersion;
    }
    $sql .= $sort . ' ' . $limit;
	$response->getBody()->write(json_encode($this->get('helper')->GetAllBySql($cls, $sql, $sqlParam, $request)));

    return $response;
})->setName("getWiRocBLEAPIReleaseUpgradeScripts");


/**
 * @SWG\Get(
 *     path="/api/v1/WiRocBLEAPIReleaseUpgradeScript/{scriptId}",
 *     description="Gets a WiRocBLEAPIReleaseUpgradeScript",
 *     operationId="getWiRocBLEAPIReleaseUpgradeScript",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="ID of the WiRocBLEAPIReleaseUpgradeScript",
 *         format="int64",
 *         in="path",
 *         name="scriptId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="WiRocBLEAPIReleaseUpgradeScript response",
 *         @SWG\Schema(
 *             ref="#/definitions/WiRocBLEAPIReleaseUpgradeScript"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/WiRocBLEAPIReleaseUpgradeScripts/{scriptId}', function (Request $request, Response $response) {
    $cls = WiRocBLEAPIReleaseUpgradeScript::class;
    $id = $request->getAttribute('scriptId');
    $WiRocBLEAPIReleaseUpgradeScript = $this->get('helper')->Get($cls, $cls::$tableName, $id);
    if ($WiRocBLEAPIReleaseUpgradeScript == false) 
    {
        return $response->withStatus(404);
    }
    $response->getBody()->write(json_encode($WiRocBLEAPIReleaseUpgradeScript));
    return $response;
})->setName("getWiRocBLEAPIReleaseUpgradeScript");



/**
 * @SWG\Post(
 *     path="/api/v1/WiRocBLEAPIReleaseUpgradeScripts",
 *     description="Adds a new WiRocBLEAPIReleaseUpgradeScript",
 *     operationId="postWiRocBLEAPIReleaseUpgradeScript",
 *     @SWG\Parameter(
 *         name="WiRocBLEAPIReleaseUpgradeScript",
 *         in="body",
 *         description="WiRocBLEAPIReleaseUpgradeScript to add to the store",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/UpsertWiRocBLEAPIReleaseUpgradeScript"),
 *     ),
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="WiRocBLEAPIReleaseUpgradeScript response",
 *         @SWG\Schema(
 *             ref="#/definitions/WiRocBLEAPIReleaseUpgradeScript"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->post('/api/v1/WiRocBLEAPIReleaseUpgradeScripts', function (Request $request, Response $response) {
    $cls = WiRocBLEAPIReleaseUpgradeScript::class;
    $objectArray = json_decode($request->getBody(), true);
    
    $objectArrayForSelect = [];
    if (array_key_exists("id", $objectArray)) {
        $objectArrayForSelect['id'] = $objectArray['id'];
    } else {
        $objectArrayForSelect['id'] = -1;
    }
    //$objectArrayForSelect['id'] = 4;
    $id = $this->get('helper')->InsertOrUpdate($cls, $objectArray, $cls::$tableName, "SELECT * FROM {$cls::$tableName} WHERE id = :id", $objectArrayForSelect);
  
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $url = $routeParser->relativeUrlFor('getWiRocBLEAPIReleaseUpgradeScript', ['scriptId' => $id]);
    return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postWiRocBLEAPIReleaseUpgradeScript");


/**
 * @SWG\Delete(
 *     path="/api/v1/WiRocBLEAPIReleaseUpgradeScripts/{scriptId}",
 *     description="Delete a WiRocBLEAPIReleaseUpgradeScript",
 *     operationId="deleteWiRocBLEAPIReleaseUpgradeScript",
 *     @SWG\Parameter(
 *         description="ID of the WiRocBLEAPIReleaseUpgradeScript",
 *         format="int64",
 *         in="path",
 *         name="scriptId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=204,
 *         description="deleted",
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->delete('/api/v1/WiRocBLEAPIReleaseUpgradeScripts/{scriptId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('scriptId');
    $cls = WiRocBLEAPIReleaseUpgradeScript::class;
    $this->get('helper')->Delete($id, $cls::$tableName);
    return $response->withStatus(204);
})->setName("deleteWiRocBLEAPIReleaseUpgradeScript");


# WiRocPython2ReleaseUpgradeScripts
/**
 * @SWG\Get(
 *     path="/api/v1/WiRocPython2ReleaseUpgradeScripts?sort={sort}&limit={limit}&limitFromVersion={limitFromVersion}&limitToVersion={limitToVersion}",
 *     description="Returns all WiRocPython2ReleaseUpgradeScripts",
 *     operationId="getWiRocPython2ReleaseUpgradeScripts",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="columns to sort on",
 *         in="path",
 *         name="sort",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Parameter(
 *         description="limit number of results returned",
 *         in="path",
 *         name="limit",
 *         required=false,
 *         format="int64",
 *         type="integer"
 *     ),
 *     @SWG\Parameter(
 *         description="only return those belonging to version number > limitFromVersion",
 *         in="path",
 *         name="limitFromVersion",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Parameter(
 *         description="only return those belonging to version number <= limitToVersion",
 *         in="path",
 *         name="limitToVersion",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="WiRocPython2ReleaseUpgradeScripts response",
 *         @SWG\Schema(
 *             type="array",
 *             @SWG\Items(ref="#/definitions/WiRocPython2ReleaseUpgradeScript")
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/WiRocPython2ReleaseUpgradeScripts', function ($request, $response) use ($app) {
    $cls = WiRocPython2ReleaseUpgradeScript::class;
 
    $limitFromVersion = $request->getQueryParams()['limitFromVersion'] ?? '';
    $limitFromVersion = ltrim($limitFromVersion, 'v');
    $limitFromVersion = ltrim($limitFromVersion, 'V');
    $limitToVersion = $request->getQueryParams()['limitToVersion'] ?? '';
    $limitToVersion = ltrim($limitToVersion, 'v');
    $limitToVersion = ltrim($limitToVersion, 'V');
    $sort = $this->get('helper')->getSort($cls, $request);
    $limit = $this->get('helper')->getLimit($request);

    $sql = 'SELECT WiRocPython2ReleaseUpgradeScripts.*, WiRocPython2Releases.releaseName, WiRocPython2Releases.versionNumber 
    FROM WiRocPython2ReleaseUpgradeScripts LEFT JOIN WiRocPython2Releases 
    ON WiRocPython2ReleaseUpgradeScripts.releaseId = WiRocPython2Releases.id';
    $sqlParam = [];
	if ($limitFromVersion != '' && $limitToVersion != '') {
        $sql .= ' WHERE WiRocPython2Releases.versionNumber > :limitFromVersion AND WiRocPython2Releases.versionNumber <= :limitToVersion';
        $sqlParam['limitFromVersion'] = $limitFromVersion;
        $sqlParam['limitToVersion'] = $limitToVersion;
    }
    $sql .= $sort . ' ' . $limit;
    $response->getBody()->write(json_encode($this->get('helper')->GetAllBySql($cls, $sql, $sqlParam, $request)));
    
    return $response;
})->setName("getWiRocPython2ReleaseUpgradeScripts");

/**
 * @SWG\Get(
 *     path="/api/v1/WiRocPython2ReleaseUpgradeScript/{scriptId}",
 *     description="Gets a WiRocPython2ReleaseUpgradeScript",
 *     operationId="getWiRocPython2ReleaseUpgradeScript",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="ID of the WiRocPython2ReleaseUpgradeScript",
 *         format="int64",
 *         in="path",
 *         name="scriptId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="WiRocPython2ReleaseUpgradeScript response",
 *         @SWG\Schema(
 *             ref="#/definitions/WiRocPython2ReleaseUpgradeScript"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/WiRocPython2ReleaseUpgradeScripts/{scriptId}', function (Request $request, Response $response) {
    $cls = WiRocPython2ReleaseUpgradeScript::class;
    $id = $request->getAttribute('scriptId');
    $WiRocPython2ReleaseUpgradeScript = $this->get('helper')->Get($cls, $cls::$tableName, $id);
    if ($WiRocPython2ReleaseUpgradeScript == false) 
    {
        return $response->withStatus(404);
    }
    $response->getBody()->write(json_encode($WiRocPython2ReleaseUpgradeScript));
    return $response;
})->setName("getWiRocPython2ReleaseUpgradeScript");


/**
 * @SWG\Post(
 *     path="/api/v1/WiRocPython2ReleaseUpgradeScripts",
 *     description="Adds a new WiRocPython2ReleaseUpgradeScript",
 *     operationId="postWiRocPython2ReleaseUpgradeScript",
 *     @SWG\Parameter(
 *         name="WiRocPython2ReleaseUpgradeScript",
 *         in="body",
 *         description="WiRocPython2ReleaseUpgradeScript to add to the store",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/UpsertWiRocPython2ReleaseUpgradeScript"),
 *     ),
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="WiRocPython2ReleaseUpgradeScript response",
 *         @SWG\Schema(
 *             ref="#/definitions/WiRocPython2ReleaseUpgradeScript"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->post('/api/v1/WiRocPython2ReleaseUpgradeScripts', function (Request $request, Response $response) {
    $cls = WiRocPython2ReleaseUpgradeScript::class;
    $objectArray = json_decode($request->getBody(), true);
    
    $objectArrayForSelect = [];
    if (array_key_exists("id", $objectArray)) {
        $objectArrayForSelect['id'] = $objectArray['id'];
    } else {
        $objectArrayForSelect['id'] = -1;
    }
    $id = $this->get('helper')->InsertOrUpdate($cls, $objectArray, $cls::$tableName, "SELECT * FROM {$cls::$tableName} WHERE id = :id", $objectArrayForSelect);
  
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $url = $routeParser->relativeUrlFor('getWiRocPython2ReleaseUpgradeScript', ['scriptId' => $id]);
    return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postWiRocPython2ReleaseUpgradeScript");


/**
 * @SWG\Delete(
 *     path="/api/v1/WiRocPython2ReleaseUpgradeScripts/{scriptId}",
 *     description="Delete a WiRocPython2ReleaseUpgradeScript",
 *     operationId="deleteWiRocPython2ReleaseUpgradeScript",
 *     @SWG\Parameter(
 *         description="ID of the WiRocPython2ReleaseUpgradeScript",
 *         format="int64",
 *         in="path",
 *         name="scriptId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=204,
 *         description="deleted",
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->delete('/api/v1/WiRocPython2ReleaseUpgradeScripts/{scriptId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('scriptId');
    $cls = WiRocPython2ReleaseUpgradeScript::class;
    $this->get('helper')->Delete($id, $cls::$tableName);
    return $response->withStatus(204);
})->setName("deleteWiRocPython2ReleaseUpgradeScript");


# Competitions
/**
 * @SWG\Get(
 *     path="/api/v1/Competitions?sort={sort}&limit={limit}",
 *     description="Returns all Competitions",
 *     operationId="getCompetitions",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="columns to sort on",
 *         in="path",
 *         name="sort",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Parameter(
 *         description="limit number of results returned",
 *         in="path",
 *         name="limit",
 *         required=false,
 *         format="int64",
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="Competitions response",
 *         @SWG\Schema(
 *             type="array",
 *             @SWG\Items(ref="#/definitions/Competition")
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/Competitions', function ($request, $response) use ($app) {
    $cls = Competition::class;
    $response->getBody()->write(json_encode($this->get('helper')->GetAll($cls, $cls::$tableName, $request)));
    return $response;
})->setName("getCompetitions");

/**
 * @SWG\Get(
 *     path="/api/v1/Competitions/{competitionId}",
 *     description="Gets a Competition",
 *     operationId="getCompetition",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="ID of the Competition",
 *         format="int64",
 *         in="path",
 *         name="competitionId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="Competition response",
 *         @SWG\Schema(
 *             ref="#/definitions/Competition"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->get('/api/v1/Competitions/{competitionId}', function (Request $request, Response $response) {
    $cls = Competition::class;
    $id = $request->getAttribute('competitionId');
    $Competition = $this->get('helper')->Get($cls, $cls::$tableName, $id);
    if ($Competition == false) 
    {
        return $response->withStatus(404);
    }
    $response->getBody()->write(json_encode($Competition));
    return $response;
})->setName("getCompetition");


/**
 * @SWG\Post(
 *     path="/api/v1/Competitions",
 *     description="Adds a new Competition",
 *     operationId="postCompetition",
 *     @SWG\Parameter(
 *         name="Competition",
 *         in="body",
 *         description="Competition to add to the store",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/NewCompetition"),
 *     ),
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="Competition response",
 *         @SWG\Schema(
 *             ref="#/definitions/Competition"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->post('/api/v1/Competitions', function (Request $request, Response $response) {
    $cls = Competition::class;
    $objectArray = json_decode($request->getBody(), true);
    
    $objectArrayForSelect = [];
    if (array_key_exists("id", $objectArray)) {
        $objectArrayForSelect['id'] = $objectArray['id'];
    } else {
        $objectArrayForSelect['id'] = -1;
    }
    $id = $this->get('helper')->InsertOrUpdate($cls, $objectArray, $cls::$tableName, "SELECT * FROM {$cls::$tableName} WHERE id = :id", $objectArrayForSelect);
  
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $url = $routeParser->relativeUrlFor('getCompetition', ['competitionId' => $id]);
    return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postCompetition");


/**
 * @SWG\Delete(
 *     path="/api/v1/Competitions/{competitionId}",
 *     description="Delete a Competitions",
 *     operationId="deleteCompetition",
 *     @SWG\Parameter(
 *         description="ID of the Competition",
 *         format="int64",
 *         in="path",
 *         name="competitionId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=204,
 *         description="deleted",
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     ),
 *     security={
 *       {"api_key": {}}
 *     }
 * )
 */
$app->delete('/api/v1/Competitions/{competitionId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('competitionId');
    $cls = Competition::class;
    $this->get('helper')->Delete($id, $cls::$tableName);
    return $response->withStatus(204);
})->setName("deleteCompetition");


function GUID()
{
    if (function_exists('com_create_guid') === true)
    {
        return trim(com_create_guid(), '{}');
    }

    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}

/**
 * @SWG\Post(
 *     path="/api/v1/Users/PasswordRecovery",
 *     description="Initiates a password recovery",
 *     operationId="postPasswordRecovery",
 *     @SWG\Parameter(
 *         name="PasswordRecovery",
 *         in="body",
 *         description="Email of the user to recover password for",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/PasswordRecovery"),
 *     ),
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="PasswordRecovery CommandResponse",
 *         @SWG\Schema(
 *             ref="#/definitions/CommandResponse"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     )
 * )
 */
$app->post('/api/v1/Users/PasswordRecovery', function (Request $request, Response $response) {
    $cls = User::class;
    $objectArray = json_decode($request->getBody(), true);
    
    $objectArrayForSelect = [];
    $email = "";
    if (array_key_exists("email", $objectArray)) {
        $email = $objectArray['email'];
    }
    $objectArrayForSelect['email'] = $email;

	$sqlSelect = "SELECT * FROM {$cls::$tableName} WHERE email = :email";
	$user = $this->get('helper')->GetBySql($cls, $sqlSelect, $objectArrayForSelect);
	
	if ($user != null) {
        $objectArrayForUpdate = [];
        $objectArrayForUpdate['email'] = $email;
        $recGuid = GUID();
        $objectArrayForUpdate['recoveryGuid'] = $recGuid;
        $sqlUpdate = "UPDATE {$cls::$tableName} SET recoveryGuid = :recoveryGuid, recoveryTime = NOW() WHERE email = :email";
        $this->get('helper')->RunSql($sqlUpdate, $objectArrayForUpdate);

        $smtpUsername = $this->get('config')['smtp']['username'];
        $smtpPassword = $this->get('config')['smtp']['password'];
        $smtpFrom = $this->get('config')['smtp']['from'];
        $smtpReplyTo = $this->get('config')['smtp']['replyto'];
        


        $mail = new PHPMailer();
        $mail->isSMTP(); 
        $mail->Host = 'mailcluster.loopia.se'; 
        $mail->SMTPAuth = true; 
        $mail->Username = $smtpUsername; // SMTP username
        $mail->Password = $smtpPassword; // SMTP password
        $mail->SMTPSecure = 'tls'; 
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->From = $smtpFrom;;
        $mail->FromName = 'Mailer';
        $mail->addAddress($email); // Add a recipient
        $mail->addReplyTo($smtpReplyTo, '');
        $mail->Subject = 'Password recovery for monitor.wiroc.se';
        $mail->Body = 'Go to the <a href="https://monitor.wiroc.se/passwordrecovery.html?recoveryGuid=' . $recGuid . '">Password Recovery Page</a> to set a new password. Must be done within 10 minutes of the request.';
        $mail->AltBody = 'Go to the Password Recovery Page: https://monitor.wiroc.se/passwordrecovery.html?recoveryGuid=' . $recGuid . ' to set a new password. Must be done within 10 minutes of the request.';
        if($mail->send()) {
            $res = new CommandResponse();
            $res->code = 0;
            $res->message = "Email sent";
            $response->getBody()->write(json_encode($res));
            return $response;
        } else {
            $res = new CommandResponse();
            $res->code = 1;
            $res->message = "Error sending email";
            $response->getBody()->write(json_encode($res));
            return $response;
        }
    }
    $res = new CommandResponse();
	$res->code = 2;
	$res->message = "User not found";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("postPasswordRecovery");

/**
 * @SWG\Post(
 *     path="/api/v1/Users/SetNewPassword",
 *     description="Sets a new password given a correct and valid recoveryGuid",
 *     operationId="postSetNewPassword",
 *     @SWG\Parameter(
 *         name="SetNewPassword",
 *         in="body",
 *         description="recoveryGuid and password",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/UserSetPassword"),
 *     ),
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="SetNewPassword CommandResponse",
 *         @SWG\Schema(
 *             ref="#/definitions/CommandResponse"
 *         ),
 *     ),
 *     @SWG\Response(
 *         response="default",
 *         description="unexpected error",
 *         @SWG\Schema(
 *             ref="#/definitions/ErrorModel"
 *         )
 *     )
 * )
 */
$app->post('/api/v1/Users/SetNewPassword', function (Request $request, Response $response) {
    $cls = User::class;
    $objectArray = json_decode($request->getBody(), true);
    
    $objectArrayForSelect = [];
    $objectArrayForUpdate = [];
    $recoveryGuid = "";
    if (array_key_exists("recoveryGuid", $objectArray)) {
        $recoveryGuid = $objectArray['recoveryGuid'];
    }
    $objectArrayForSelect['recoveryGuid'] = $recoveryGuid;
    $objectArrayForUpdate['recoveryGuid'] = $recoveryGuid;
    
    //recoveryGuid
	$sqlSelect = "SELECT * FROM {$cls::$tableName} WHERE recoveryGuid = :recoveryGuid";
	$user = $this->get('helper')->GetBySql($cls, $sqlSelect, $objectArrayForSelect);
	
	if ($user != null) {

        $dateTimeNow = new DateTime();
        $recoveryTime = $user->recoveryTime;
        $dateTimeRecovery = new DateTime($recoveryTime);
        $dateTimeRecovery->modify("+10 minutes");

        if ($dateTimeRecovery > $dateTimeNow) {
            // Within 10 minutes
            $hashedPassword = "";
            if (array_key_exists("password", $objectArray)) {
                $password = $objectArray['password'];

                
                $pepper = $this->get('config')['pepper'];
                $pwd_peppered = hash_hmac("sha256", $objectArray['password'], $pepper);
                $pwd_hashed = password_hash($pwd_peppered, PASSWORD_ARGON2ID);
                $objectArrayForUpdate['hashedPassword'] = $pwd_hashed;

                $sqlUpdate = "UPDATE {$cls::$tableName} SET hashedPassword = :hashedPassword, updateTime = NOW() WHERE recoveryGuid = :recoveryGuid";
                $this->get('helper')->RunSql($sqlUpdate, $objectArrayForUpdate);

                $res = new CommandResponse();
                $res->code = 0;
                $res->message = "Password updated";
                $response->getBody()->write(json_encode($res));
                return $response;
            } else {
                $res = new CommandResponse();
                $res->code = 3;
                $res->message = "Password not supplied";
                $response->getBody()->write(json_encode($res));
                return $response;
            }
            
        } else {
            $res = new CommandResponse();
            $res->code = 1;
            $res->message = "To late, the recovery guid has expired!";
            $response->getBody()->write(json_encode($res));
            return $response;
        }
    }
    $res = new CommandResponse();
	$res->code = 2;
	$res->message = "User not found";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("postSetNewPassword");


$app->run();

