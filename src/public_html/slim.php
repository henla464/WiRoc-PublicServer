<?php
session_start();

use DI\Container;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
//use \Interop\Container\ContainerInterface as ContainerInterface;
//use Doctrine\Common\Annotations\AnnotationReader;
use Slim\Factory\AppFactory;

require '../vendor/autoload.php';
//\Doctrine\Common\Annotations\AnnotationRegistry::registerLoader('class_exists');

//require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI == 'cli-server') {

    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
	// check the file types, only serve standard files
    if (preg_match('/\.(?:png|js|jpg|jpeg|gif|css|html|css|js|htm)$/', $file)) {
        // does the file exist? If so, return it
        if (is_file($file))
            return false;

        // file does not exist. return a 404
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
        printf('"%s" does not exist', $_SERVER['REQUEST_URI']);
        return false;
    }
}



//$containerBuilder = new ContainerBuilder();
$container = new DI\Container();
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addErrorMiddleware(true, false, false);

$container->set('config', function () {
    $config['displayErrorDetails'] = true;
	$config['addContentLengthHeader'] = false;
	$ini_array = parse_ini_file("../config/config.ini", true);
	$config['db']['host']   = $ini_array['database']['database_hostname'];
	$config['db']['user']   = $ini_array['database']['database_username'];
	$config['db']['pass']   = $ini_array['database']['database_password'];
	$config['db']['dbname'] = $ini_array['database']['database_name'];
	$config['upload']['log_archive_upload_directory'] = $ini_array['upload']['log_archive_upload_directory'];
    return $config;
});

//$annotationReader = new AnnotationReader();


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
 *     name="Authorization"
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
    $swagger = \Swagger\scan(['.', '../classes']);
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
 *             ref="#/definitions/CommandResponse"
 *         )
 *     )
 * )
 */
$app->get('/api/v1/login', function($request, $response, $args) use ($app) {
    $res = new CommandResponse();
	$res->code = 0;
	$res->message = "Login OK";
	$response->getBody()->write(json_encode($res));
    return $response;
})->setName("login");

/**
 * @SWG\Post(
 *     path="/api/v1/login",
 * 	   description="Login method",
 *     @SWG\Response(
 *         response="200", 
 *         description="CommandResponse code=0 is success",
 *         @SWG\Schema(
 *             ref="#/definitions/CommandResponse"
 *         )
 *     )
 * )
 */
$app->post('/api/v1/login', function($request, $response, $args) use ($app) {
    $res = new CommandResponse();
	$res->code = 0;
	$res->message = "Login OK";
	$response->getBody()->write(json_encode($res));
    return $response;
})->setName("postLogin");



/**
     * @SWG\Post(
     *     path="/api/v1/CreateTables",
     *     description="Create the tables in the database if they don't exist",
     *     operationId="createTables",
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

# DEVICES
/**
     * @SWG\Get(
     *     path="/api/v1/Devices?sort={sort}&limit={limit}&limitToUser={limitToUser}",
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
     *     @SWG\Parameter(
     *         description="limit to user",
     *         in="path",
     *         name="limitToUser",
     *         required=false,
     *         type="boolean"
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
	$user = $request->getAttribute('user'); //todo:
	$userId = 0; //$user->id;
	$queryParams = $request->getQueryParams();
	if ($queryParams['limitToUser'] == 'true') {
		$sql = 'SELECT Devices.* FROM Devices JOIN UserDevices ON Devices.id = UserDevices.deviceId WHERE UserDevices.userId = :userId';
		$response->getBody()->write(json_encode($this->get('helper')->GetAllBySql($cls, $sql, ['userId'=>$userId], $request)));
		return $response;
	} else {
		$sql = 'SELECT Devices.*, CASE WHEN UserDevices.id IS NOT NULL THEN true ELSE false END AS connectedToUser FROM Devices LEFT JOIN UserDevices ON Devices.id = UserDevices.deviceId and UserDevices.userId = :userId';
		$response->getBody()->write(json_encode($this->get('helper')->GetAllBySql($cls, $sql, ['userId'=>$userId], $request)));
		return $response;
	}
})->setName("getDevices");


/**
     * @SWG\Get(
     *     path="/api/v1/DevicesView?sort={sort}&limit={limit}&limitToUser={limitToUser}",
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
     *         description="limit to user",
     *         in="path",
     *         name="limitToUser",
     *         required=false,
     *         type="boolean"
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
	$user = $request->getAttribute('user'); //todo:
	$userId = 0; //$user->id;
	$queryParams = $request->getQueryParams();
	if ($queryParams['limitToUser'] == 'true') {
		$sql = 'SELECT CASE WHEN Devices.reportTime > DATE_ADD(SYSDATE(), INTERVAL -5 MINUTE) THEN true ELSE false END recentlyReported, 
		CASE WHEN Devices.connectedToInternetTime > DATE_ADD(SYSDATE(), INTERVAL -5 MINUTE) THEN true ELSE false END connectedToInternet, 
		Devices.* FROM Devices JOIN UserDevices ON Devices.id = UserDevices.deviceId WHERE UserDevices.userId = :userId';
		$response->getBody()->write(json_encode($this->get('helper')->GetAllBySql($cls, $sql, ['userId'=>$userId], $request)));
		return $response;
	} else {
		$sql = 'SELECT CASE WHEN Devices.reportTime > DATE_ADD(SYSDATE(), INTERVAL -5 MINUTE) THEN true ELSE false END recentlyReported, 
			CASE WHEN Devices.connectedToInternetTime > DATE_ADD(SYSDATE(), INTERVAL -5 MINUTE) THEN true ELSE false END connectedToInternet, 
			Devices.*, CASE WHEN UserDevices.id IS NOT NULL THEN true ELSE false END AS connectedToUser FROM Devices LEFT JOIN UserDevices ON Devices.id = UserDevices.deviceId and UserDevices.userId = :userId';
		$response->getBody()->write(json_encode($this->get('helper')->GetAllBySql($cls, $sql, ['userId'=>$userId], $request)));
		return $response;
	}
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
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $objectArray['BTAddress']]);
	return $response->withStatus(302)->withHeader('Location', $url);
})->setName('getDeviceUpdateDeviceName');




/**
     * @SWG\Delete(
     *     path="/api/v1/Devices/{BTAddress}",
     *     description="Deletes a device",
     *     operationId="deleteDevice",
     *     @SWG\Parameter(
     *         description="ID of device to fetch",
     *         format="int64",
     *         in="path",
     *         name="deviceId",
     *         required=true,
     *         type="integer"
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
	$this->get('helper')->DeleteBySql("DELETE FROM $cls WHERE BTAddress = :BTAddress", $objectArray);
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
    $objectArrayForSelect['BTAddress'] = $objectArray['BTAddress'];
	$cls = Device::class;
    $id = $this->get('helper')->InsertOrUpdate($cls, $objectArray, $cls::$tableName, "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress", $objectArrayForSelect);
    
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $objectArray['BTAddress']]);
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
	$sql = "UPDATE {$cls::$tableName} SET `connectedToInternetTime` = NOW(), `updateTime` = NOW() WHERE BTAddress = :BTAddress";
    $this->get('helper')->RunSql($sql, $objectArray);
  	
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $url = $routeParser->relativeUrlFor('getDevice', ['BTAddress' => $objectArray['BTAddress']]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postDeviceSetConnectedToInternetTime");



# DEVICESTATUS 
/**
     * @SWG\Get(
     *     path="/api/v1/Devices/{BTAddress}/DeviceStatuses?sort={sort}&limit={limit}",
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
    $sql = "SELECT DeviceStatuses.* FROM DeviceStatuses WHERE DeviceStatuses.BTAddress = :BTAddress " . $sort . " " . $limit;
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
    $response->getBody()->write(json_encode($this->get('helper')->Get($cls, $cls::$tableName, $id)));
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
     * @SWG\Get(
     *     path="/api/v1/UserDevices/{userDeviceId}",
     *     description="Gets a UserDevice",
     *     operationId="getUserDevice",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         description="ID of the UserDevice",
     *         format="int64",
     *         in="path",
     *         name="userDeviceId",
     *         required=true,
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="MessageStat response",
     *         @SWG\Schema(
     *             ref="#/definitions/UserDevice"
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
$app->get('/api/v1/UserDevices/{userDeviceId}', function (Request $request, Response $response) {
    $cls = UserDevice::class;
    $id = $request->getAttribute('userDeviceId');
	$response->getBody()->write(json_encode($this->get('helper')->Get($cls, $cls::$tableName, $id)));
	return $response;
})->setName("getUserDevice");

/**
     * @SWG\Get(
     *     path="/api/v1/UserDevices",
     *     description="Gets UserDevices",
     *     operationId="getUserDevices",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="Userdevices response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/UserDevice")
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
$app->get('/api/v1/UserDevices', function (Request $request, Response $response) {
    $cls = UserDevice::class;
    $response->getBody()->write(json_encode($this->get('helper')->GetAll($cls, $cls::$tableName, $request)));
    return $response;
})->setName("getUserDevices");


/**
     * @SWG\Post(
     *     path="/api/v1/UserDevices",
     *     description="Adds a new UserDevice",
     *     operationId="postUserDevice",
     *     @SWG\Parameter(
     *         name="UserDevice",
     *         in="body",
     *         description="UserDevice to add to the store",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewUserDevice"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="UserDevice response",
     *         @SWG\Schema(
     *             ref="#/definitions/UserDevice"
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
$app->post('/api/v1/UserDevices', function (Request $request, Response $response) {
    $cls = UserDevice::class;
    $objectArray = json_decode($request->getBody(), true);
    if (!array_key_exists("userId", $objectArray)) {
		$user = $request->getAttribute('user');
		$objectArray['userId'] = $user->id;
	}
    $id = $this->get('helper')->Insert($cls, $objectArray, $cls::$tableName);
    $routeContext = RouteContext::fromRequest($request);
    $routeParser = $routeContext->getRouteParser();
    $url = $routeParser->relativeUrlFor('getUserDevice', ['userDeviceId' => $id]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postUserDevice");


/**
     * @SWG\Delete(
     *     path="/api/v1/UserDevices/{userDeviceId}",
     *     description="Delete a new UserDevice",
     *     operationId="deleteUserDevice",
     *     @SWG\Parameter(
     *         description="ID of the userDevice",
     *         format="int64",
     *         in="path",
     *         name="userDeviceId",
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
$app->delete('/api/v1/UserDevices/{userDeviceId}', function (Request $request, Response $response) {
	$id = $request->getAttribute('userDeviceId');
    $cls = UserDevice::class;
    $this->get('helper')->Delete($id, $cls::$tableName);
	return $response->withStatus(204);
})->setName("deleteUserDevice");


/**
     * @SWG\Delete(
     *     path="/api/v1/Devices/{deviceId}/UserDevices",
     *     description="Delete a the UserDevice of of the device and logged in user",
     *     operationId="deleteUserDeviceByDevice",
     *     @SWG\Parameter(
     *         description="ID of the device",
     *         format="int64",
     *         in="path",
     *         name="deviceId",
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
$app->delete('/api/v1/Devices/{deviceId}/UserDevices', function (Request $request, Response $response) {
	$deviceId = $request->getAttribute('deviceId');
    $cls = UserDevice::class;
    $sql = "DELETE FROM {$cls::$tableName} WHERE deviceId = :deviceId AND userId = :userId";
    $user = $request->getAttribute('user');
	$values = ['deviceId'=>$deviceId, 'userId'=>$user->id];
    $this->get('helper')->DeleteBySql($sql, $values);
	return $response->withStatus(204);
})->setName("deleteUserDeviceByDevice");


/**
     * @SWG\Post(
     *     path="/api/v1/LogArchives",
     *     description="Upload a logarchive (zip file with logs and database): curl -X POST ""http://monitor.wiroc.se/api/v1/LogArchives"" -H ""accept: application/json"" -H ""Authorization: <apikey>"" -F ""newfile=@/path/to/zipfile.zip""",
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
    if ($newfile->getSize() > 5000000) {
		throw new Exception('File is too large!');
	}
	$uploadFileName = $newfile->getClientFilename();
	$imageFileType = strtolower(pathinfo($uploadFileName,PATHINFO_EXTENSION));
	if ($imageFileType !== 'zip') {
		throw new Exception('Not a zip file!');
	}
    if ($newfile->getError() === UPLOAD_ERR_OK) {
		$newfile->moveTo("$target_dir$uploadFileName");
	}
	return $response;
})->setName("postLogArchives");

$app->run();

