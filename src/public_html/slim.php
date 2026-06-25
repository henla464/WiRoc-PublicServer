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
    if (preg_match('/\.(?:png|js|jpg|jpeg|gif|css|html|css|js|htm|xz)$/', $file)) {
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
    $config['upload']['map_upload_directory'] = $ini_array['upload']['map_upload_directory'] ?? '../uploads/Maps/';
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
            $res->userId = $_SESSION['userId'];
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
			$res->userId = $_SESSION['userId'];
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

/**
     * @SWG\Post(
     *     path="/api/v1/UpdateDatabaseSchema",
     *     description="Update tables, add columns if they don't exist",
     *     operationId="postUpdateDatabaseSchema",
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
$app->post('/api/v1/UpdateDatabaseSchema', function (Request $request, Response $response) {
	$response->getBody()->write(json_encode($this->get('helper')->UpdateDatabaseSchema()));
	return $response;
})->setName("postUpdateDatabaseSchema");



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
        Devices.*, Competitions.name as competitionName, Controls.controlNumber as controlNumber, Controls.name as controlName, Controls.controlType as controlType, IFNULL(DeviceStatuses.batteryLevel, 0) as batteryLevel, IFNULL(DeviceStatuses.createdTime, "1980-01-01T00:00:08.000Z") as batteryLevelTime
        FROM Devices LEFT JOIN Competitions ON Devices.competitionId = Competitions.id
        LEFT JOIN Controls ON Devices.controlId = Controls.id
        LEFT JOIN DeviceStatuses ON (DeviceStatuses.Id = (SELECT Id FROM DeviceStatuses WHERE BTAddress = Devices.BTAddress ORDER BY createdTime DESC LIMIT 1))';

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
        $sql .= 'Devices.headBTAddress = :headBTAddress';
        $sqlParam['headBTAddress'] = $headBTAddress;
    }
    if (trim($headBTAddress) != '' && trim($competitionId) != '') {
        $sql .= ' AND ';
    }
    if (trim($competitionId) != '') {
        $sql .= 'Devices.competitionId = :competitionId';
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
     * @SWG\Delete(
     *     path="/api/v1/Devices/DeleteById/{Id}",
     *     description="Deletes a device",
     *     operationId="deleteDeviceById",
     *     @SWG\Parameter(
     *         description="Id of device to delete",
     *         in="path",
     *         name="Id",
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
$app->delete('/api/v1/Devices/DeleteById/{Id}', function (Request $request, Response $response) {
    $Id = $request->getAttribute('Id');
    $cls = Device::class;
    $objectArray = [];
    $objectArray['Id'] = $Id;
	$this->get('helper')->DeleteBySql("DELETE FROM {$cls::$tableName} WHERE id = :Id", $objectArray);
    return $response->withStatus(204);
})->setName('deleteDeviceById');

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

    if (!preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $objectArray['BTAddress'])) {
        $res = new CommandResponse();
        $res->code = 3;
        $res->message = "Invalid BTAddress format. Expected format: 00:00:00:00:00:00";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }

    $objectArrayForSelect['BTAddress'] = $objectArray['BTAddress'];
	$cls = Device::class;
    $id = $this->get('helper')->InsertOrUpdate($cls, $objectArray, $cls::$tableName, "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress", $objectArrayForSelect);
    
    $objectArrayForUpdateReportTime = [];
    $objectArrayForUpdateReportTime['BTAddress'] = $objectArray['BTAddress'];
    // removed setting of reportTime here, as it is set in the SetConnectedToInternetTime endpoint and in the postDeviceStatus endpoint
    $sql = "UPDATE {$cls::$tableName} SET `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    $this->get('helper')->RunSql($sql, $objectArrayForUpdateReportTime);
    
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $btAddressUrl = str_replace(':','%3A', $objectArray['BTAddress']);
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $btAddressUrl]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postDevices");

/**
     * @SWG\Patch(
     *     path="/api/v1/Device",
     *     description="Updates a device",
     *     operationId="patchDevice",
     *     @SWG\Parameter(
     *         name="device",
     *         in="body",
     *         description="Device to update",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/DeviceUpdate"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="Device response",
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
     *       {}
     *     }
     * )
     */
    $app->patch('/api/v1/Device', function (Request $request, Response $response) {
        $objectArray = json_decode($request->getBody(), true);

        $BTAddress = $objectArray['BTAddress'] ?? null;
        if (isset($BTAddress) && !preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $BTAddress)) {
            $res = new CommandResponse();
            $res->code = 3;
            $res->message = "Invalid BTAddress format. Expected format: 00:00:00:00:00:00";
            $response->getBody()->write(json_encode($res));
            return $response->withStatus(400);
        }
        $deviceId = $objectArray['id'] ?? null;
        if (isset($deviceId)) {
            unset($objectArray['id']);
        } elseif (isset($BTAddress)) {
            unset($objectArray['BTAddress']);
            $cls = Device::class;
            $sql = "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress";
            $device = $this->get('helper')->GetBySql($cls, $sql, ['BTAddress'=>$BTAddress]);
            if (!$device) {
                return $response->withStatus(404);
            }
            $deviceId = $device->id;
        } else {
            $res = new CommandResponse();
            $res->code = 2;
            $res->message = "No device identifier supplied";
            $response->getBody()->write(json_encode($res));
            return $response;
        }

        // Resolve BTAddress if we only have deviceId, then check DeviceAccess
        if (!isset($BTAddress) && isset($deviceId)) {
            $device = $this->get('helper')->Get(Device::class, Device::$tableName, $deviceId);
            $BTAddress = $device ? $device->BTAddress : null;
        }
        if (isset($BTAddress)) {
            $err = $this->get('helper')->requireDeviceAccess($response, $BTAddress);
            if ($err) return $err;
        }

        $cls = Device::class;
        try {
            $this->get('helper')->Update($cls, $objectArray, $cls::$tableName, $deviceId);
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
        $res->message = "Device updated";
        $response->getBody()->write(json_encode($res));
        return $response;
    })->setName("patchDevice");

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

        $err = $this->get('helper')->requireDeviceAccess($response, $BTAddress);
        if ($err) return $err;

        $newCompetitionId = $objectArray['competitionId'];

        // Check if competition is changing; if so, clear controlId
        $currentDevice = $this->get('helper')->GetBySql($cls, "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress", ['BTAddress' => $BTAddress]);
        $competitionChanged = $currentDevice && $currentDevice->competitionId != $newCompetitionId;

        $updateObjectArray = [];
        $updateObjectArray['BTAddress'] = $BTAddress;
        $updateObjectArray['competitionId'] = $newCompetitionId;
        $userId = $_SESSION['userId'];
        $updateObjectArray['competitionIdSetByUserId'] = $userId;
        
        if ($competitionChanged) {
            $sql = "UPDATE {$cls::$tableName} SET `competitionId` = :competitionId, `competitionIdSetByUserId` = :competitionIdSetByUserId, `controlId` = NULL, `updateTime` = NOW() WHERE BTAddress = :BTAddress";
        } else {
            $sql = "UPDATE {$cls::$tableName} SET `competitionId` = :competitionId, `competitionIdSetByUserId` = :competitionIdSetByUserId, `updateTime` = NOW() WHERE BTAddress = :BTAddress";
        }
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
     *     path="/api/v1/Devices/{BTAddress}/DeviceStatuses?sort={sort}&limit={limit}&limitToCreatedTimeWithinSeconds={limitToCreatedTimeWithinSeconds}",
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

    if ($BTAddress == 'all') {
        // Admin-only: delete all statuses
        if (empty($_SESSION['userIsAdmin'])) {
            return $response->withStatus(403);
        }
        $this->get('helper')->DeleteBySql('DELETE FROM DeviceStatuses', []);
    } else {
        $err = $this->get('helper')->requireDeviceAccess($response, $BTAddress);
        if ($err) return $err;
        $this->get('helper')->DeleteBySql('DELETE FROM DeviceStatuses WHERE BTAddress = :BTAddress', ['BTAddress' => $BTAddress]);
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

    if ($BTAddress == 'all') {
        // Admin-only: delete all message stats
        if (empty($_SESSION['userIsAdmin'])) {
            return $response->withStatus(403);
        }
        $this->get('helper')->DeleteBySql('DELETE FROM MessageStats', []);
    } else {
        $err = $this->get('helper')->requireDeviceAccess($response, $BTAddress);
        if ($err) return $err;
        $this->get('helper')->DeleteBySql('DELETE FROM MessageStats WHERE BTAddress = :BTAddress', ['BTAddress' => $BTAddress]);
    }
    return $response->withStatus(204);
})->setName("deleteMessageStatsByBTAddress");


# DEVICEACCESS 
/**
     * @SWG\Post(
     *     path="/api/v1/DeviceAccess/grant",
     *     description="Grant device access to a user. Called from WiRoc Config mobile app after BLE connection.",
     *     operationId="postDeviceAccessGrant",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="Grant request",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/DeviceAccessGrantRequest"),
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="command response",
     *         @SWG\Schema(
     *             ref="#/definitions/CommandResponse"
     *         ),
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     * )
     */
$app->post('/api/v1/DeviceAccess/grant', function (Request $request, Response $response) {
    $objectArray = json_decode($request->getBody(), true);
    
    if (!isset($objectArray['UserEmail'], $objectArray['UserPassword'], $objectArray['BTAddress'])) {
        $res = new CommandResponse();
        $res->code = 1;
        $res->message = "Missing required fields: UserEmail, UserPassword, BTAddress";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }

    if (!preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $objectArray['BTAddress'])) {
        $res = new CommandResponse();
        $res->code = 3;
        $res->message = "Invalid BTAddress format. Expected format: 00:00:00:00:00:00";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }
    
    $pepper = $this->get('config')['pepper'];
    $pwd_peppered = hash_hmac("sha256", $objectArray['UserPassword'], $pepper);
    
    $userCls = User::class;
    $sql = "SELECT * FROM {$userCls::$tableName} WHERE Email = :Email";
    $user = $this->get('helper')->GetBySql($userCls, $sql, ['Email' => $objectArray['UserEmail']]);
    
    if ($user == null || !password_verify($pwd_peppered, $user->hashedPassword)) {
        $res = new CommandResponse();
        $res->code = 2;
        $res->message = "Invalid credentials";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(401);
    }
    
    $deviceAccessCls = DeviceAccess::class;
    $checkSql = "SELECT * FROM {$deviceAccessCls::$tableName} WHERE BTAddress = :BTAddress AND UserId = :UserId";
    $existing = $this->get('helper')->GetBySql($deviceAccessCls, $checkSql, [
        'BTAddress' => $objectArray['BTAddress'],
        'UserId' => $user->id
    ]);
    
    if ($existing) {
        $res = new CommandResponse();
        $res->code = 0;
        $res->message = "Access already granted";
        $response->getBody()->write(json_encode($res));
        return $response;
    }
    
    $insertSql = "INSERT INTO {$deviceAccessCls::$tableName} (BTAddress, UserId, GrantedAt, GrantedByUserId, createdTime) VALUES (:BTAddress, :UserId, NOW(), :GrantedByUserId, NOW())";
    $this->get('helper')->RunSql($insertSql, [
        'BTAddress' => $objectArray['BTAddress'],
        'UserId' => $user->id,
        'GrantedByUserId' => $user->id
    ]);
    
    $res = new CommandResponse();
    $res->code = 0;
    $res->message = "Device access granted";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("postDeviceAccessGrant");


/**
     * @SWG\Post(
     *     path="/api/v1/DeviceAccess/revoke",
     *     description="Revoke device access from a user. The requesting user must have access to the device.",
     *     operationId="postDeviceAccessRevoke",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="Revoke request",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/DeviceAccessRevokeRequest"),
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="command response",
     *         @SWG\Schema(
     *             ref="#/definitions/CommandResponse"
     *         ),
     *     ),
     *     security={
     *       {}
     *     }
     * )
     */
$app->post('/api/v1/DeviceAccess/revoke', function (Request $request, Response $response) {
    $objectArray = json_decode($request->getBody(), true);
    $currentUserId = $_SESSION['userId'];
    
    if (!isset($objectArray['BTAddress'], $objectArray['UserId'])) {
        $res = new CommandResponse();
        $res->code = 1;
        $res->message = "Missing required fields: BTAddress, UserId";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }
    
    $deviceAccessCls = DeviceAccess::class;
    $checkSql = "SELECT * FROM {$deviceAccessCls::$tableName} WHERE BTAddress = :BTAddress AND UserId = :UserId";
    $requesterAccess = $this->get('helper')->GetBySql($deviceAccessCls, $checkSql, [
        'BTAddress' => $objectArray['BTAddress'],
        'UserId' => $currentUserId
    ]);
    
    if (!$requesterAccess) {
        $res = new CommandResponse();
        $res->code = 3;
        $res->message = "You do not have access to this device";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(403);
    }
    
    $deleteSql = "DELETE FROM {$deviceAccessCls::$tableName} WHERE BTAddress = :BTAddress AND UserId = :UserId";
    $this->get('helper')->RunSql($deleteSql, [
        'BTAddress' => $objectArray['BTAddress'],
        'UserId' => $objectArray['UserId']
    ]);
    
    $res = new CommandResponse();
    $res->code = 0;
    $res->message = "Device access revoked";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("postDeviceAccessRevoke");


/**
     * @SWG\Get(
     *     path="/api/v1/DeviceAccess",
     *     description="Returns all device access entries for devices the current user has access to",
     *     operationId="getDeviceAccesses",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="device access response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/DeviceAccess")
     *         ),
     *     ),
     *     security={
     *       {}
     *     }
     * )
     */
$app->get('/api/v1/DeviceAccess', function (Request $request, Response $response) {
    $currentUserId = $_SESSION['userId'];
    $isAdmin = !empty($_SESSION['userIsAdmin']);

    $deviceAccessCls = DeviceAccess::class;
    $selectFields = "SELECT da.id, da.BTAddress, da.UserId, u.email AS UserEmail, da.GrantedAt, da.GrantedByUserId, da.updateTime, da.createdTime,
                   d.name AS DeviceName
            FROM {$deviceAccessCls::$tableName} da
            JOIN Users u ON da.UserId = u.id
            LEFT JOIN Devices d ON da.BTAddress = d.BTAddress";

    if ($isAdmin) {
        $sql = "$selectFields ORDER BY da.BTAddress, da.UserId";
        $entries = $this->get('helper')->GetAllBySql($deviceAccessCls, $sql, []);
    } else {
        $sql = "$selectFields WHERE da.BTAddress IN (
                SELECT BTAddress FROM {$deviceAccessCls::$tableName} WHERE UserId = :CurrentUserId
            )
            ORDER BY da.BTAddress, da.UserId";
        $entries = $this->get('helper')->GetAllBySql($deviceAccessCls, $sql, [
            'CurrentUserId' => $currentUserId
        ]);
    }

    $response->getBody()->write(json_encode($entries));
    return $response;
})->setName("getDeviceAccesses");


/**
     * @SWG\Post(
     *     path="/api/v1/DeviceAccess/grantFromWeb",
     *     description="Grant device access from the website. No password required since the user is already authenticated. Admins can grant to any device; other users can only grant to devices they have access to.",
     *     operationId="postDeviceAccessGrantFromWeb",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="Grant request (admin)",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/DeviceAccessGrantWebRequest"),
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="command response",
     *         @SWG\Schema(
     *             ref="#/definitions/CommandResponse"
     *         ),
     *     ),
     *     security={
     *       {}
     *     }
     * )
     */
$app->post('/api/v1/DeviceAccess/grantFromWeb', function (Request $request, Response $response) {
    $objectArray = json_decode($request->getBody(), true);

    if (!isset($objectArray['UserEmail'], $objectArray['BTAddress'])) {
        $res = new CommandResponse();
        $res->code = 1;
        $res->message = "Missing required fields: UserEmail, BTAddress";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }

    if (!preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $objectArray['BTAddress'])) {
        $res = new CommandResponse();
        $res->code = 3;
        $res->message = "Invalid BTAddress format. Expected format: 00:00:00:00:00:00";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }

    $userCls = User::class;
    $sql = "SELECT * FROM {$userCls::$tableName} WHERE Email = :Email";
    $user = $this->get('helper')->GetBySql($userCls, $sql, ['Email' => $objectArray['UserEmail']]);

    if ($user == null) {
        $res = new CommandResponse();
        $res->code = 2;
        $res->message = "User not found";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(404);
    }

    $deviceAccessCls = DeviceAccess::class;
    $checkSql = "SELECT * FROM {$deviceAccessCls::$tableName} WHERE BTAddress = :BTAddress AND UserId = :UserId";
    $existing = $this->get('helper')->GetBySql($deviceAccessCls, $checkSql, [
        'BTAddress' => $objectArray['BTAddress'],
        'UserId' => $user->id
    ]);

    if ($existing) {
        $res = new CommandResponse();
        $res->code = 0;
        $res->message = "Access already granted";
        $response->getBody()->write(json_encode($res));
        return $response;
    }

    $currentUserId = $_SESSION['userId'];

    // Non-admin users must have access to the device to grant access to others
    if (empty($_SESSION['userIsAdmin'])) {
        $deviceAccessCls = DeviceAccess::class;
        $checkSql = "SELECT * FROM {$deviceAccessCls::$tableName} WHERE BTAddress = :BTAddress AND UserId = :UserId";
        $requesterAccess = $this->get('helper')->GetBySql($deviceAccessCls, $checkSql, [
            'BTAddress' => $objectArray['BTAddress'],
            'UserId' => $currentUserId
        ]);
        if (!$requesterAccess) {
            $res = new CommandResponse();
            $res->code = 4;
            $res->message = "You do not have access to this device";
            $response->getBody()->write(json_encode($res));
            return $response->withStatus(403);
        }
    }

    $insertSql = "INSERT INTO {$deviceAccessCls::$tableName} (BTAddress, UserId, GrantedAt, GrantedByUserId, createdTime) VALUES (:BTAddress, :UserId, NOW(), :GrantedByUserId, NOW())";
    $this->get('helper')->RunSql($insertSql, [
        'BTAddress' => $objectArray['BTAddress'],
        'UserId' => $user->id,
        'GrantedByUserId' => $currentUserId
    ]);

    $res = new CommandResponse();
    $res->code = 0;
    $res->message = "Device access granted";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("postDeviceAccessGrantFromWeb");


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
 *     path="/api/v1/WiRocPython2Releases?sort={sort}&limit={limit}&hwVersion={hwVersion}&hwRevision={hwRevision}&BTAddress={BTAddress}",
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
*     @SWG\Parameter(
*         description="BT Address of device to get relevant releases for (checks releaseStatusKeyName of the device)",
*         in="path",
*         name="BTAddress",
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
    // find the device to get sortOrder of the releaseStatusKeyName
    $BTAddress = $request->getQueryParams()['BTAddress'] ?? '';
    $releaseStatusSortOrder = 0;
    if ($BTAddress != '') {
        $cls = Device::class;
        $releaseStatusKeyName = '';
        $sql = "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress";
        $device = $this->get('helper')->GetBySql($cls, $sql, ['BTAddress'=>$BTAddress]);
        if ($device) {
            $releaseStatusKeyName = $device->releaseStatusKeyName;
        }
        if ($releaseStatusKeyName != '') {
            $cls = ReleaseStatus::class;
            $sql = 'SELECT ReleaseStatuses.sortOrder FROM ReleaseStatuses WHERE ReleaseStatuses.keyName = :keyName';
            $sqlParams['keyName'] = $releaseStatusKeyName;
            $releaseStatus = $this->get('helper')->GetAllBySql($cls, $sql, $sqlParams, $request);
            if ($releaseStatus && count($releaseStatus) > 0) {
                $releaseStatusSortOrder = $releaseStatus[0]->sortOrder;
            }
        }
    }

    $cls = WiRocPython2Release::class;
    $limit = $this->get('helper')->getLimit($request);
    $sort = $this->get('helper')->getSort($cls, $request);
    $hwVersion = $request->getQueryParams()['hwVersion'] ?? '';
    $hwRevision = $request->getQueryParams()['hwRevision'] ?? '';

    $sqlParams = [];
    $sql = 'SELECT WiRocPython2Releases.*, ReleaseStatuses.displayName as releaseStatusDisplayName, ReleaseStatuses.keyName as releaseStatusKeyName FROM WiRocPython2Releases LEFT JOIN ReleaseStatuses ON WiRocPython2Releases.releaseStatusId = ReleaseStatuses.id';
    $sql .= ' WHERE ReleaseStatuses.sortOrder >= :releaseStatusSortOrder';
    $sqlParams['releaseStatusSortOrder'] = $releaseStatusSortOrder;
    if (ctype_digit($hwVersion))
    {
        $sql .= ' and (WiRocPython2Releases.minHWVersion <= :hwVersion and :hwVersion <= WiRocPython2Releases.maxHWVersion)';
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
 *     path="/api/v1/WiRocBLEAPIReleases?sort={sort}&limit={limit}&hwVersion={hwVersion}&hwRevision={hwRevision}&BTAddress={BTAddress}",
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
 *      @SWG\Parameter(
 *         description="BTAddress of device to get relevant releases for (checks releaseStatusKeyName of the device)",
 *         in="path",
 *         name="BTAddress",
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
    // find the device to get sortOrder of the releaseStatusKeyName
    $BTAddress = $request->getQueryParams()['BTAddress'] ?? '';
    $releaseStatusSortOrder = 0;
    if ($BTAddress != '') {
        $cls = Device::class;
        $releaseStatusKeyName = '';
        $sql = "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress";
        $device = $this->get('helper')->GetBySql($cls, $sql, ['BTAddress'=>$BTAddress]);
        if ($device) {
            $releaseStatusKeyName = $device->releaseStatusKeyName;
        }
        if ($releaseStatusKeyName != '') {
            $cls = ReleaseStatus::class;
            $sql = 'SELECT ReleaseStatuses.sortOrder FROM ReleaseStatuses WHERE ReleaseStatuses.keyName = :keyName';
            $sqlParams['keyName'] = $releaseStatusKeyName;
            $releaseStatus = $this->get('helper')->GetAllBySql($cls, $sql, $sqlParams, $request);
            if ($releaseStatus && count($releaseStatus) > 0) {
                $releaseStatusSortOrder = $releaseStatus[0]->sortOrder;
            }
        }
    }

    $cls = WiRocBLEAPIRelease::class;
    $limit = $this->get('helper')->getLimit($request);
    $sort = $this->get('helper')->getSort($cls, $request);
    $hwVersion = $request->getQueryParams()['hwVersion'] ?? '';
    $hwRevision = $request->getQueryParams()['hwRevision'] ?? '';
    $btAddress = $request->getQueryParams()['btAddress'] ?? '';
    $sqlParams = [];
    $sql = 'SELECT WiRocBLEAPIReleases.*, ReleaseStatuses.displayName as releaseStatusDisplayName, ReleaseStatuses.keyName as releaseStatusKeyName FROM WiRocBLEAPIReleases LEFT JOIN ReleaseStatuses ON WiRocBLEAPIReleases.releaseStatusId = ReleaseStatuses.id';
    $sql .= ' WHERE ReleaseStatuses.sortOrder >= :releaseStatusSortOrder';
    $sqlParams['releaseStatusSortOrder'] = $releaseStatusSortOrder;
    if (ctype_digit($hwVersion))
    {
        $sql .= ' and (WiRocBLEAPIReleases.minHWVersion <= :hwVersion and :hwVersion <= WiRocBLEAPIReleases.maxHWVersion)';
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


# COMPETITION MAP
/**
 * @SWG\Get(
 *     path="/api/v1/Competitions/{competitionId}/Controls/withDevices",
 *     description="Returns all controls for a competition with their associated WiRoc device info. Requires competition edit access.",
 *     operationId="getCompetitionControlsWithDevices",
 *     @SWG\Parameter(
 *         description="ID of the Competition",
 *         format="int64", in="path", name="competitionId", required=true, type="integer"
 *     ),
 *     @SWG\Response(response=200, description="Controls with device info"),
 *     security={ {} }
 * )
 */
$app->get('/api/v1/Competitions/{competitionId}/Controls/withDevices', function (Request $request, Response $response) {
    $competitionId = $request->getAttribute('competitionId');

    $err = $this->get('helper')->requireCompetitionViewAccess($response, $competitionId);
    if ($err) return $err;

    $sql = "SELECT c.id, c.controlNumber, c.name as controlName, c.description, c.mapX, c.mapY, c.controlType,
                   d.id as deviceId, d.name as deviceName, d.BTAddress as deviceBTAddress,
                   d.headBTAddress as deviceHeadBTAddress,
                   d.batteryIsLow, IFNULL(ds.batteryLevel, 0) as batteryLevel
            FROM Controls c
            LEFT JOIN Devices d ON d.controlId = c.id
            LEFT JOIN DeviceStatuses ds ON (ds.Id = (SELECT Id FROM DeviceStatuses WHERE BTAddress = d.BTAddress ORDER BY createdTime DESC LIMIT 1))
            WHERE c.competitionId = :competitionId
            ORDER BY c.controlNumber";
    $entries = $this->get('helper')->GetAllBySql(Control::class, $sql, ['competitionId' => $competitionId]);
    $response->getBody()->write(json_encode($entries));
    return $response;
})->setName("getCompetitionControlsWithDevices");

/**
 * @SWG\Patch(
 *     path="/api/v1/Controls/{controlId}/MapPosition",
 *     description="Update a control's position on the map. Requires competition edit access.",
 *     operationId="patchControlMapPosition",
 *     @SWG\Parameter(
 *         description="ID of the Control",
 *         format="int64", in="path", name="controlId", required=true, type="integer"
 *     ),
 *     @SWG\Parameter(
 *         name="body", in="body", required=true,
 *         @SWG\Schema(
 *             required={"mapX", "mapY"},
 *             @SWG\Property(property="mapX", type="number"),
 *             @SWG\Property(property="mapY", type="number")
 *         ),
 *     ),
 *     @SWG\Response(response=200, description="Position saved"),
 *     security={ {} }
 * )
 */
$app->patch('/api/v1/Controls/{controlId}/MapPosition', function (Request $request, Response $response) {
    $controlId = $request->getAttribute('controlId');
    $objectArray = json_decode($request->getBody(), true);

    $controlCls = Control::class;
    $control = $this->get('helper')->Get($controlCls, $controlCls::$tableName, $controlId);
    if (!$control) {
        return $response->withStatus(404);
    }

    $err = $this->get('helper')->requireCompetitionEditAccess($response, $control->competitionId);
    if ($err) return $err;

    $updateData = ['mapX' => $objectArray['mapX'], 'mapY' => $objectArray['mapY']];
    $this->get('helper')->Update($controlCls, $updateData, $controlCls::$tableName, $controlId);

    $res = new CommandResponse();
    $res->code = 0;
    $res->message = "Position saved";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("patchControlMapPosition");

/**
 * @SWG\Get(
 *     path="/api/v1/Competitions/{competitionId}/Map",
 *     description="Get map metadata for a competition",
 *     operationId="getCompetitionMap",
 *     @SWG\Parameter(
 *         description="ID of the Competition",
 *         format="int64", in="path", name="competitionId", required=true, type="integer"
 *     ),
 *     @SWG\Response(response=200, description="Map metadata"),
 *     security={ {"api_key": {}} }
 * )
 */
$app->get('/api/v1/Competitions/{competitionId}/Map', function (Request $request, Response $response) {
    $competitionId = $request->getAttribute('competitionId');
    $cls = CompetitionMap::class;
    $sql = "SELECT * FROM {$cls::$tableName} WHERE competitionId = :competitionId";
    $map = $this->get('helper')->GetBySql($cls, $sql, ['competitionId' => $competitionId]);

    if (!$map) {
        $res = new \stdClass();
        $res->exists = false;
        $response->getBody()->write(json_encode($res));
        return $response;
    }

    $res = new \stdClass();
    $res->exists = true;
    $res->id = $map->id;
    $res->competitionId = $map->competitionId;
    $res->originalFileName = $map->originalFileName;
    $res->fileType = $map->fileType;
    $res->defaultZoom = $map->defaultZoom;
    $res->defaultCenterX = $map->defaultCenterX;
    $res->defaultCenterY = $map->defaultCenterY;
    $res->mapScale = $map->mapScale;
    $res->mapScaleRatio = $map->mapScaleRatio;
    $res->georefP1X = $map->georefP1X;
    $res->georefP1Y = $map->georefP1Y;
    $res->georefP1Lat = $map->georefP1Lat;
    $res->georefP1Lng = $map->georefP1Lng;
    $res->georefP2X = $map->georefP2X;
    $res->georefP2Y = $map->georefP2Y;
    $res->georefP2Lat = $map->georefP2Lat;
    $res->georefP2Lng = $map->georefP2Lng;
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("getCompetitionMap");

/**
 * @SWG\Get(
 *     path="/api/v1/Competitions/{competitionId}/Map/file",
 *     description="Get the map image file for a competition. Requires competition edit access.",
 *     operationId="getCompetitionMapFile",
 *     @SWG\Parameter(
 *         description="ID of the Competition",
 *         format="int64", in="path", name="competitionId", required=true, type="integer"
 *     ),
 *     @SWG\Response(response=200, description="Map image file"),
 *     security={ {} }
 * )
 */
$app->get('/api/v1/Competitions/{competitionId}/Map/file', function (Request $request, Response $response) {
    $competitionId = $request->getAttribute('competitionId');

    $err = $this->get('helper')->requireCompetitionViewAccess($response, $competitionId);
    if ($err) return $err;

    $cls = CompetitionMap::class;
    $sql = "SELECT * FROM {$cls::$tableName} WHERE competitionId = :competitionId";
    $map = $this->get('helper')->GetBySql($cls, $sql, ['competitionId' => $competitionId]);

    if (!$map || !$map->storedFileName) {
        return $response->withStatus(404);
    }

    $targetDir = $this->get('config')['upload']['map_upload_directory'];
    $filePath = $targetDir . $map->storedFileName;

    if (!file_exists($filePath)) {
        return $response->withStatus(404);
    }

    $response->getBody()->write(file_get_contents($filePath));
    $contentType = 'image/png';
    if ($map->fileType === 'jpeg' || $map->fileType === 'jpg') {
        $contentType = 'image/jpeg';
    }
    return $response->withHeader('Content-Type', $contentType)
                    ->withHeader('Cache-Control', 'public, max-age=86400');
})->setName("getCompetitionMapFile");

/**
 * @SWG\Post(
 *     path="/api/v1/Competitions/{competitionId}/Map",
 *     description="Upload a map file. Requires competition edit access.",
 *     operationId="postCompetitionMap",
 *     @SWG\Parameter(
 *         description="ID of the Competition",
 *         format="int64", in="path", name="competitionId", required=true, type="integer"
 *     ),
 *     @SWG\Response(response=200, description="Map uploaded"),
 *     security={ {} }
 * )
 */
$app->post('/api/v1/Competitions/{competitionId}/Map', function (Request $request, Response $response) {
    $competitionId = $request->getAttribute('competitionId');

    $err = $this->get('helper')->requireCompetitionEditAccess($response, $competitionId);
    if ($err) return $err;

    $files = $request->getUploadedFiles();
    if (empty($files['mapfile'])) {
        $res = new CommandResponse();
        $res->code = 1;
        $res->message = "No file uploaded";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }

    $uploadedFile = $files['mapfile'];
    if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
        $res = new CommandResponse();
        $res->code = 2;
        $res->message = "Upload error";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }

    $originalName = $uploadedFile->getClientFilename();
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExts = ['png'];
    if (!in_array($ext, $allowedExts)) {
        $res = new CommandResponse();
        $res->code = 3;
        $res->message = "Unsupported file type. Allowed: PNG";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }

    $targetDir = $this->get('config')['upload']['map_upload_directory'];
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $storedName = $uuid . '.' . $ext;
    $uploadedFile->moveTo($targetDir . $storedName);

    // Delete old map if exists
    $mapCls = CompetitionMap::class;
    $oldSql = "SELECT * FROM {$mapCls::$tableName} WHERE competitionId = :competitionId";
    $oldMap = $this->get('helper')->GetBySql($mapCls, $oldSql, ['competitionId' => $competitionId]);
    if ($oldMap) {
        $oldPath = $targetDir . $oldMap->storedFileName;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
        $this->get('helper')->Delete($oldMap->id, $mapCls::$tableName);
    }

    // Insert new map record
    $objectArray = [
        'competitionId' => $competitionId,
        'originalFileName' => $originalName,
        'storedFileName' => $storedName,
        'fileType' => $ext
    ];
    $id = $this->get('helper')->Insert($mapCls, $objectArray, $mapCls::$tableName);

    $res = new CommandResponse();
    $res->code = 0;
    $res->message = "Map uploaded";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("postCompetitionMap");

/**
 * @SWG\Patch(
 *     path="/api/v1/Competitions/{competitionId}/Map",
 *     description="Save default view state and calibration (zoom, center, mapScale, mapScaleRatio). Requires competition edit access.",
 *     operationId="patchCompetitionMap",
 *     @SWG\Parameter(
 *         description="ID of the Competition",
 *         format="int64", in="path", name="competitionId", required=true, type="integer"
 *     ),
 *     @SWG\Parameter(
 *         name="body", in="body", required=true,
 *         @SWG\Schema(ref="#/definitions/CompetitionMapViewState"),
 *     ),
 *     @SWG\Response(response=200, description="View state saved"),
 *     security={ {} }
 * )
 */
$app->patch('/api/v1/Competitions/{competitionId}/Map', function (Request $request, Response $response) {
    $competitionId = $request->getAttribute('competitionId');
    $objectArray = json_decode($request->getBody(), true);

    $err = $this->get('helper')->requireCompetitionEditAccess($response, $competitionId);
    if ($err) return $err;

    $mapCls = CompetitionMap::class;
    $oldSql = "SELECT * FROM {$mapCls::$tableName} WHERE competitionId = :competitionId";
    $map = $this->get('helper')->GetBySql($mapCls, $oldSql, ['competitionId' => $competitionId]);
    if (!$map) {
        $res = new CommandResponse();
        $res->code = 1;
        $res->message = "No map exists for this competition";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(404);
    }

    $updateData = [];
    if (isset($objectArray['zoom'])) $updateData['defaultZoom'] = $objectArray['zoom'];
    if (isset($objectArray['centerX'])) $updateData['defaultCenterX'] = $objectArray['centerX'];
    if (isset($objectArray['centerY'])) $updateData['defaultCenterY'] = $objectArray['centerY'];
    if (isset($objectArray['mapScale'])) $updateData['mapScale'] = $objectArray['mapScale'];
    if (isset($objectArray['mapScaleRatio'])) $updateData['mapScaleRatio'] = $objectArray['mapScaleRatio'];
    if (isset($objectArray['georefP1X'])) $updateData['georefP1X'] = $objectArray['georefP1X'];
    if (isset($objectArray['georefP1Y'])) $updateData['georefP1Y'] = $objectArray['georefP1Y'];
    if (isset($objectArray['georefP1Lat'])) $updateData['georefP1Lat'] = $objectArray['georefP1Lat'];
    if (isset($objectArray['georefP1Lng'])) $updateData['georefP1Lng'] = $objectArray['georefP1Lng'];
    if (isset($objectArray['georefP2X'])) $updateData['georefP2X'] = $objectArray['georefP2X'];
    if (isset($objectArray['georefP2Y'])) $updateData['georefP2Y'] = $objectArray['georefP2Y'];
    if (isset($objectArray['georefP2Lat'])) $updateData['georefP2Lat'] = $objectArray['georefP2Lat'];
    if (isset($objectArray['georefP2Lng'])) $updateData['georefP2Lng'] = $objectArray['georefP2Lng'];
    $this->get('helper')->Update($mapCls, $updateData, $mapCls::$tableName, $map->id);

    $res = new CommandResponse();
    $res->code = 0;
    $res->message = "View state saved";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("patchCompetitionMap");

/**
 * @SWG\Delete(
 *     path="/api/v1/Competitions/{competitionId}/Map",
 *     description="Delete a competition map. Requires competition edit access.",
 *     operationId="deleteCompetitionMap",
 *     @SWG\Parameter(
 *         description="ID of the Competition",
 *         format="int64", in="path", name="competitionId", required=true, type="integer"
 *     ),
 *     @SWG\Response(response=204, description="deleted"),
 *     security={ {} }
 * )
 */
$app->delete('/api/v1/Competitions/{competitionId}/Map', function (Request $request, Response $response) {
    $competitionId = $request->getAttribute('competitionId');

    $err = $this->get('helper')->requireCompetitionEditAccess($response, $competitionId);
    if ($err) return $err;

    $mapCls = CompetitionMap::class;
    $oldSql = "SELECT * FROM {$mapCls::$tableName} WHERE competitionId = :competitionId";
    $map = $this->get('helper')->GetBySql($mapCls, $oldSql, ['competitionId' => $competitionId]);
    if ($map) {
        $targetDir = $this->get('config')['upload']['map_upload_directory'];
        $filePath = $targetDir . $map->storedFileName;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $this->get('helper')->Delete($map->id, $mapCls::$tableName);
    }

    return $response->withStatus(204);
})->setName("deleteCompetitionMap");
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
        // Verify the current user is the creator or admin
        $existing = $this->get('helper')->GetBySql($cls, "SELECT * FROM {$cls::$tableName} WHERE id = :id", $objectArrayForSelect);
        if ($existing && $existing->createdByUserId != $_SESSION['userId'] && empty($_SESSION['userIsAdmin'])) {
            return $response->withStatus(403);
        }
    } else {
        $objectArrayForSelect['id'] = -1;
        // Set the creator
        $objectArray['createdByUserId'] = $_SESSION['userId'];
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
    // Verify the current user is the creator or admin
    $competition = $this->get('helper')->Get($cls, $cls::$tableName, $id);
    if ($competition && $competition->createdByUserId != $_SESSION['userId'] && empty($_SESSION['userIsAdmin'])) {
        return $response->withStatus(403);
    }
    $this->get('helper')->Delete($id, $cls::$tableName);
    return $response->withStatus(204);
})->setName("deleteCompetition");


# CONTROLS
/**
 * @SWG\Get(
 *     path="/api/v1/Controls?competitionId={competitionId}&sort={sort}&limit={limit}",
 *     description="Returns Controls for a competition (requires edit access) or all controls (admin only)",
 *     operationId="getControls",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="Filter by competitionId (requires edit access to that competition)",
 *         in="path",
 *         name="competitionId",
 *         required=false,
 *         type="string"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="Controls response",
 *         @SWG\Schema(
 *             type="array",
 *             @SWG\Items(ref="#/definitions/Control")
 *         ),
 *     ),
 *     security={
 *       {}
 *     }
 * )
 */
$app->get('/api/v1/Controls', function ($request, $response) use ($app) {
    $cls = Control::class;
    $queryParams = $request->getQueryParams();
    $competitionId = $queryParams['competitionId'] ?? '';
    $sort = $this->get('helper')->getSort($cls, $request);
    $limit = $this->get('helper')->getLimit($request);
    if (trim($competitionId) != '' && ctype_digit($competitionId)) {
        $err = $this->get('helper')->requireCompetitionViewAccess($response, $competitionId);
        if ($err) return $err;
        $sql = "SELECT * FROM {$cls::$tableName} WHERE competitionId = :competitionId " . $sort . " " . $limit;
        $response->getBody()->write(json_encode($this->get('helper')->GetAllBySql($cls, $sql, ['competitionId'=>$competitionId])));
    } else {
        // No competitionId filter: admin only
        if (empty($_SESSION['userIsAdmin'])) {
            return $response->withStatus(403);
        }
        $response->getBody()->write(json_encode($this->get('helper')->GetAll($cls, $cls::$tableName, $request)));
    }
    return $response;
})->setName("getControls");

/**
 * @SWG\Get(
 *     path="/api/v1/Controls/{controlId}",
 *     description="Gets a Control. Requires edit access to the control's competition.",
 *     operationId="getControl",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         description="ID of the Control",
 *         format="int64",
 *         in="path",
 *         name="controlId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="Control response",
 *         @SWG\Schema(ref="#/definitions/Control")
 *     ),
 *     security={
 *       {}
 *     }
 * )
 */
$app->get('/api/v1/Controls/{controlId}', function (Request $request, Response $response) {
    $cls = Control::class;
    $id = $request->getAttribute('controlId');
    $control = $this->get('helper')->Get($cls, $cls::$tableName, $id);
    if ($control == false) {
        return $response->withStatus(404);
    }

    $err = $this->get('helper')->requireCompetitionViewAccess($response, $control->competitionId);
    if ($err) return $err;

    $response->getBody()->write(json_encode($control));
    return $response;
})->setName("getControl");

/**
 * @SWG\Post(
 *     path="/api/v1/Controls",
 *     description="Adds or updates a Control. Requires competition access.",
 *     operationId="postControl",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         name="control",
 *         in="body",
 *         description="Control to add/update",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/NewControl"),
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="Control response",
 *         @SWG\Schema(ref="#/definitions/Control")
 *     ),
 *     security={
 *       {}
 *     }
 * )
 */
$app->post('/api/v1/Controls', function (Request $request, Response $response) {
    $cls = Control::class;
    $objectArray = json_decode($request->getBody(), true);

    $competitionId = $objectArray['competitionId'];
    $err = $this->get('helper')->requireCompetitionEditAccess($response, $competitionId);
    if ($err) return $err;

    // Check uniqueness of controlNumber+controlType within competition
    if (!array_key_exists("id", $objectArray) || $objectArray['id'] == null) {
        $checkType = isset($objectArray['controlType']) ? $objectArray['controlType'] : 'Control';
        $checkSql = "SELECT * FROM {$cls::$tableName} WHERE competitionId = :competitionId AND controlType = :controlType AND controlNumber = :controlNumber";
        $existing = $this->get('helper')->GetBySql($cls, $checkSql, [
            'competitionId' => $competitionId,
            'controlType' => $checkType,
            'controlNumber' => $objectArray['controlNumber']
        ]);
        if ($existing) {
            $res = new CommandResponse();
            $res->code = 1;
            $res->message = "Control number " . $objectArray['controlNumber'] . " already exists in this competition";
            $response->getBody()->write(json_encode($res));
            return $response->withStatus(400);
        }
    }

    $objectArrayForSelect = [];
    if (array_key_exists("id", $objectArray) && $objectArray['id'] != null) {
        $objectArrayForSelect['id'] = $objectArray['id'];
    } else {
        $objectArrayForSelect['id'] = -1;
    }
    $id = $this->get('helper')->InsertOrUpdate($cls, $objectArray, $cls::$tableName, "SELECT * FROM {$cls::$tableName} WHERE id = :id", $objectArrayForSelect);

    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $url = $routeParser->relativeUrlFor('getControl', ['controlId' => $id]);
    return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postControl");

/**
 * @SWG\Delete(
 *     path="/api/v1/Controls/{controlId}",
 *     description="Delete a Control",
 *     operationId="deleteControl",
 *     @SWG\Parameter(
 *         description="ID of the Control",
 *         format="int64",
 *         in="path",
 *         name="controlId",
 *         required=true,
 *         type="integer"
 *     ),
 *     @SWG\Response(response=204, description="deleted"),
 *     security={
 *       {}
 *     }
 * )
 */
$app->delete('/api/v1/Controls/{controlId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('controlId');
    $cls = Control::class;
    $control = $this->get('helper')->Get($cls, $cls::$tableName, $id);
    if (!$control) {
        return $response->withStatus(404);
    }
    $err = $this->get('helper')->requireCompetitionEditAccess($response, $control->competitionId);
    if ($err) return $err;
    $this->get('helper')->Delete($id, $cls::$tableName);
    return $response->withStatus(204);
})->setName("deleteControl");


# DEVICE CONTROL ASSIGNMENT
/**
 * @SWG\Post(
 *     path="/api/v1/Devices/{BTAddress}/SetControl",
 *     description="Set the controlId for a device",
 *     operationId="postDeviceSetControl",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         name="body",
 *         in="body",
 *         description="Control assignment object",
 *         required=true,
 *         @SWG\Schema(
 *             required={"controlId"},
 *             @SWG\Property(property="controlId", type="integer")
 *         ),
 *     ),
 *     @SWG\Parameter(
 *         description="BT Address of device to update",
 *         in="path",
 *         name="BTAddress",
 *         required=true,
 *         type="string"
 *     ),
 *     @SWG\Response(response=200, description="device response"),
 *     security={
 *       {}
 *     }
 * )
 */
$app->post('/api/v1/Devices/{BTAddress}/SetControl', function (Request $request, Response $response) {
    $objectArray = json_decode($request->getBody(), true);
    $BTAddress = $request->getAttribute('BTAddress');
    $cls = Device::class;

    if (!isset($_SESSION['userId'])) {
        return $response->withStatus(401);
    }

    $err = $this->get('helper')->requireDeviceAccess($response, $BTAddress);
    if ($err) return $err;

    // Get the device to check its competition
    $device = $this->get('helper')->GetBySql($cls, "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress", ['BTAddress' => $BTAddress]);
    if (!$device) {
        return $response->withStatus(404);
    }

    $controlId = !empty($objectArray['controlId']) ? $objectArray['controlId'] : null;
    $controlType = !empty($objectArray['controlType']) ? $objectArray['controlType'] : null;
    $controlNumber = !empty($objectArray['controlNumber']) ? $objectArray['controlNumber'] : null;

    // Find-or-create control for Repeater/Receiver
    if ($controlType && $controlNumber && in_array($controlType, ['Repeater', 'Receiver'])) {
        $controlCls = Control::class;
        $sql = "SELECT * FROM {$controlCls::$tableName} WHERE competitionId = :competitionId AND controlType = :controlType AND controlNumber = :controlNumber";
        $control = $this->get('helper')->GetBySql($controlCls, $sql, [
            'competitionId' => $device->competitionId,
            'controlType' => $controlType,
            'controlNumber' => $controlNumber
        ]);
        if ($control) {
            $controlId = $control->id;
        } else {
            // Auto-create the Repeater/Receiver control; if old unique index still present, catch and re-query
            try {
                $insertData = [
                    'competitionId' => $device->competitionId,
                    'controlNumber' => $controlNumber,
                    'controlType' => $controlType,
                    'name' => $controlType . ' ' . $controlNumber
                ];
                $controlId = $this->get('helper')->Insert($controlCls, $insertData, $controlCls::$tableName);
            } catch (\PDOException $e) {
                // Duplicate key — control was created by another request or old index prevents insert
                $control = $this->get('helper')->GetBySql($controlCls, $sql, [
                    'competitionId' => $device->competitionId,
                    'controlType' => $controlType,
                    'controlNumber' => $controlNumber
                ]);
                if ($control) $controlId = $control->id;
            }
        }
    }

    // Verify regular control if controlId was given directly
    if ($controlId !== null && !$controlType) {
        $controlCls = Control::class;
        $control = $this->get('helper')->Get($controlCls, $controlCls::$tableName, $controlId);
        if (!$control || $control->competitionId != $device->competitionId) {
            $res = new CommandResponse();
            $res->code = 1;
            $res->message = "Control does not belong to the device's competition";
            $response->getBody()->write(json_encode($res));
            return $response->withStatus(400);
        }
    }

    $updateObjectArray = [];
    $updateObjectArray['BTAddress'] = $BTAddress;
    if ($controlId !== null) {
        $sql = "UPDATE {$cls::$tableName} SET `controlId` = :controlId, `updateTime` = NOW() WHERE BTAddress = :BTAddress";
        $updateObjectArray['controlId'] = $controlId;
    } else {
        $sql = "UPDATE {$cls::$tableName} SET `controlId` = NULL, `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    }
    $this->get('helper')->RunSql($sql, $updateObjectArray);

    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $btAddressUrl = str_replace(':','%3A', $BTAddress);
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $btAddressUrl]);
    return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postDeviceSetControl");


# COMPETITION ACCESS
/**
 * @SWG\Get(
 *     path="/api/v1/CompetitionAccess",
 *     description="Returns competition access entries for competitions the current user created or has access to. Admins see all.",
 *     operationId="getCompetitionAccesses",
 *     produces={"application/json"},
 *     @SWG\Response(
 *         response=200,
 *         description="competition access response",
 *         @SWG\Schema(
 *             type="array",
 *             @SWG\Items(ref="#/definitions/CompetitionAccess")
 *         ),
 *     ),
 *     security={
 *       {}
 *     }
 * )
 */
$app->get('/api/v1/CompetitionAccess', function (Request $request, Response $response) {
    $currentUserId = $_SESSION['userId'];
    $isAdmin = !empty($_SESSION['userIsAdmin']);

    try {
        $compAccessCls = CompetitionAccess::class;
        $selectFields = "SELECT ca.id, ca.competitionId, ca.UserId, u.email AS UserEmail, ca.GrantedAt, ca.GrantedByUserId, ca.accessRole, ca.updateTime, ca.createdTime,
                       c.name AS CompetitionName
                FROM {$compAccessCls::$tableName} ca
                JOIN Users u ON ca.UserId = u.id
                JOIN Competitions c ON ca.competitionId = c.id";

        if ($isAdmin) {
            $sql = "$selectFields ORDER BY ca.competitionId, ca.UserId";
            $entries = $this->get('helper')->GetAllBySql($compAccessCls, $sql, []);
        } else {
            // Show entries where user is creator (to manage grants) OR is the grant recipient
            $sql = "$selectFields WHERE ca.competitionId IN (
                    SELECT id FROM Competitions WHERE createdByUserId = :CurrentUserId
                ) OR ca.UserId = :CurrentUserId2
                ORDER BY ca.competitionId, ca.UserId";
            $entries = $this->get('helper')->GetAllBySql($compAccessCls, $sql, [
                'CurrentUserId' => $currentUserId,
                'CurrentUserId2' => $currentUserId
            ]);
        }
    } catch (\PDOException $e) {
        // Table may not exist yet; return empty array
        $entries = [];
    }

    $response->getBody()->write(json_encode($entries));
    return $response;
})->setName("getCompetitionAccesses");

/**
 * @SWG\Get(
 *     path="/api/v1/Competitions/{competitionId}/access",
 *     description="Get current user's access level for a competition",
 *     operationId="getCompetitionAccessLevel",
 *     @SWG\Parameter(
 *         description="ID of the Competition",
 *         format="int64", in="path", name="competitionId", required=true, type="integer"
 *     ),
 *     @SWG\Response(response=200, description="Access level info"),
 *     security={ {} }
 * )
 */
$app->get('/api/v1/Competitions/{competitionId}/access', function (Request $request, Response $response) {
    $competitionId = $request->getAttribute('competitionId');
    $res = new \stdClass();
    $res->isLoggedIn = !empty($_SESSION['userId']);
    $res->isAdmin = !empty($_SESSION['userIsAdmin']);
    $res->hasViewAccess = false;
    $res->hasEditAccess = false;
    $res->isCreator = false;

    if (!$res->isLoggedIn) {
        $response->getBody()->write(json_encode($res));
        return $response;
    }

    // Check creator
    $compCls = Competition::class;
    $competition = $this->get('helper')->Get($compCls, $compCls::$tableName, $competitionId);
    if ($competition && $competition->createdByUserId == $_SESSION['userId']) {
        $res->isCreator = true;
        $res->hasViewAccess = true;
        $res->hasEditAccess = true;
        $response->getBody()->write(json_encode($res));
        return $response;
    }

    // Admin
    if (!empty($_SESSION['userIsAdmin'])) {
        $res->hasViewAccess = true;
        $res->hasEditAccess = true;
        $response->getBody()->write(json_encode($res));
        return $response;
    }

    // Check CompetitionAccesses
    try {
        $compAccessCls = CompetitionAccess::class;
        $sql = "SELECT accessRole FROM {$compAccessCls::$tableName} WHERE competitionId = :competitionId AND UserId = :UserId";
        $access = $this->get('helper')->GetBySql($compAccessCls, $sql, [
            'competitionId' => $competitionId,
            'UserId' => $_SESSION['userId']
        ]);
        if ($access) {
            $res->hasViewAccess = true;
            $res->hasEditAccess = ($access->accessRole === 'edit');
        }
    } catch (\PDOException $e) {}

    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("getCompetitionAccessLevel");


/**
 * @SWG\Post(
 *     path="/api/v1/CompetitionAccess/grant",
 *     description="Grant competition access to a user. Only the competition creator can grant access.",
 *     operationId="postCompetitionAccessGrant",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         name="body",
 *         in="body",
 *         description="Grant request",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/CompetitionAccessGrantRequest"),
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="command response",
 *         @SWG\Schema(ref="#/definitions/CommandResponse")
 *     ),
 *     security={
 *       {}
 *     }
 * )
 */
$app->post('/api/v1/CompetitionAccess/grant', function (Request $request, Response $response) {
    $objectArray = json_decode($request->getBody(), true);

    if (!isset($objectArray['competitionId'], $objectArray['UserEmail'])) {
        $res = new CommandResponse();
        $res->code = 1;
        $res->message = "Missing required fields: competitionId, UserEmail";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }

    // Verify the current user is the creator of the competition or admin
    $compCls = Competition::class;
    $competition = $this->get('helper')->Get($compCls, $compCls::$tableName, $objectArray['competitionId']);
    if (!$competition) {
        $res = new CommandResponse();
        $res->code = 2;
        $res->message = "Competition not found";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(404);
    }
    if ($competition->createdByUserId != $_SESSION['userId'] && empty($_SESSION['userIsAdmin'])) {
        return $response->withStatus(403);
    }

    // Find the user by email
    $userCls = User::class;
    $sql = "SELECT * FROM {$userCls::$tableName} WHERE Email = :Email";
    $user = $this->get('helper')->GetBySql($userCls, $sql, ['Email' => $objectArray['UserEmail']]);
    if ($user == null) {
        $res = new CommandResponse();
        $res->code = 3;
        $res->message = "User not found";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(404);
    }

    // Check if access already granted
    $compAccessCls = CompetitionAccess::class;
    $checkSql = "SELECT * FROM {$compAccessCls::$tableName} WHERE competitionId = :competitionId AND UserId = :UserId";
    $existing = $this->get('helper')->GetBySql($compAccessCls, $checkSql, [
        'competitionId' => $objectArray['competitionId'],
        'UserId' => $user->id
    ]);
    if ($existing) {
        $res = new CommandResponse();
        $res->code = 0;
        $res->message = "Access already granted";
        $response->getBody()->write(json_encode($res));
        return $response;
    }

    $role = $objectArray['accessRole'] ?? 'edit';
    if (!in_array($role, ['view', 'edit'])) $role = 'view';

    $insertSql = "INSERT INTO {$compAccessCls::$tableName} (competitionId, UserId, GrantedAt, GrantedByUserId, accessRole, createdTime) VALUES (:competitionId, :UserId, NOW(), :GrantedByUserId, :accessRole, NOW())";
    $this->get('helper')->RunSql($insertSql, [
        'competitionId' => $objectArray['competitionId'],
        'UserId' => $user->id,
        'GrantedByUserId' => $_SESSION['userId'],
        'accessRole' => $role
    ]);

    $res = new CommandResponse();
    $res->code = 0;
    $res->message = "Competition access granted";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("postCompetitionAccessGrant");

/**
 * @SWG\Post(
 *     path="/api/v1/CompetitionAccess/revoke",
 *     description="Revoke competition access from a user. Only the competition creator can revoke access.",
 *     operationId="postCompetitionAccessRevoke",
 *     produces={"application/json"},
 *     @SWG\Parameter(
 *         name="body",
 *         in="body",
 *         description="Revoke request",
 *         required=true,
 *         @SWG\Schema(ref="#/definitions/CompetitionAccessRevokeRequest"),
 *     ),
 *     @SWG\Response(
 *         response=200,
 *         description="command response",
 *         @SWG\Schema(ref="#/definitions/CommandResponse")
 *     ),
 *     security={
 *       {}
 *     }
 * )
 */
$app->post('/api/v1/CompetitionAccess/revoke', function (Request $request, Response $response) {
    $objectArray = json_decode($request->getBody(), true);

    if (!isset($objectArray['competitionId'], $objectArray['UserId'])) {
        $res = new CommandResponse();
        $res->code = 1;
        $res->message = "Missing required fields: competitionId, UserId";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(400);
    }

    // Verify the current user is the creator of the competition or admin
    $compCls = Competition::class;
    $competition = $this->get('helper')->Get($compCls, $compCls::$tableName, $objectArray['competitionId']);
    if (!$competition) {
        $res = new CommandResponse();
        $res->code = 2;
        $res->message = "Competition not found";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(404);
    }
    if ($competition->createdByUserId != $_SESSION['userId'] && empty($_SESSION['userIsAdmin'])) {
        return $response->withStatus(403);
    }

    $compAccessCls = CompetitionAccess::class;
    $deleteSql = "DELETE FROM {$compAccessCls::$tableName} WHERE competitionId = :competitionId AND UserId = :UserId";
    $this->get('helper')->RunSql($deleteSql, [
        'competitionId' => $objectArray['competitionId'],
        'UserId' => $objectArray['UserId']
    ]);

    $res = new CommandResponse();
    $res->code = 0;
    $res->message = "Competition access revoked";
    $response->getBody()->write(json_encode($res));
    return $response;
})->setName("postCompetitionAccessRevoke");




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

