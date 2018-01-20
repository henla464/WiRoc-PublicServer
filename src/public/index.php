<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Doctrine\Common\Annotations\AnnotationReader;

require '../vendor/autoload.php';
#require_once('../classes/Device.php');
#require_once('../classes/ErrorModel.php');
\Doctrine\Common\Annotations\AnnotationRegistry::registerLoader('class_exists');

session_start();

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$ini_array = parse_ini_file("../config/config.ini", true);

$config['db']['host']   = $ini_array['database']['database_hostname'];
$config['db']['user']   = $ini_array['database']['database_username'];
$config['db']['pass']   = $ini_array['database']['database_password'];
$config['db']['dbname'] = $ini_array['database']['database_name'];

$app = new \Slim\App(["settings" => $config]);
$container = $app->getContainer();
$app->add( new AuthMiddleware($container) );
$annotationReader = new AnnotationReader();

#echo('asdf');


$container['logger'] = function($c) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler("../logs/app.log");
    $logger->pushHandler($file_handler);
    return $logger;
};

$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'] .";charset=utf8mb4",
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

$container['helper'] = function ($container) {
    return new Helper($container);
};

#$a = new Helper($container);
#$a->CreateTables();
#echo('END');

/**
 * @SWG\Info(title="WiRoc Monitor API", version="1")
 */

/**
 * @SWG\Get(
 *     path="/api/v1",
 *     @SWG\Response(response="200", description="V1 of the api")
 * )
 */
$app->get('/api/v1', function($request, $response, $args) use ($app) {
    $swagger = \Swagger\scan(['.', '../classes']);
    header('Content-Type: application/json');
    echo $swagger;
})->setName("getApiV1");

/**
 * @SWG\Get(
 *     path="/swagger/docs",
 *     @SWG\Response(response="200", description="Redirects to the swagger for the latest API version")
 * )
 */
$app->get('/swagger/docs', function($request, $response, $args) {
	$url = $this->get('router')->pathFor('getApiV1', []);
    return $response->withStatus(303)->withHeader('Location', $url);
})->setName("getDocs");

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
    return $response->withJson($res);
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
    return $response->withJson($res);
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
    return $response->withJson($res);
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
     *     )
     * )
     */
$app->post('/api/v1/CreateTables', function (Request $request, Response $response) {
	return $response->withJson($this->helper->CreateTables());
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
     *     )
     * )
     */
$app->get('/api/v1/Users', function (Request $request, Response $response) {
	$cls = User::class;
    return $response->withJson($this->helper->GetAll($cls, $cls::$tableName, $request));
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
     *     )
     * )
     */
$app->get('/api/v1/Devices', function (Request $request, Response $response) {
	$cls = Device::class;
	$user = $request->getAttribute('user');
	$userId = $user->id;
	if ($request->getQueryParam('limitToUser') == 'true') {
		$sql = 'SELECT Devices.* FROM Devices JOIN UserDevices ON Devices.id = UserDevices.deviceId WHERE UserDevices.userId = :userId';
		return $response->withJson($this->helper->GetAllBySql($cls, $sql, ['userId'=>$userId], $request));
	} else {
		$sql = 'SELECT Devices.*, CASE WHEN UserDevices.id IS NOT NULL THEN true ELSE false END AS connectedToUser FROM Devices LEFT JOIN UserDevices ON Devices.id = UserDevices.deviceId and UserDevices.userId = :userId';
		return $response->withJson($this->helper->GetAllBySql($cls, $sql, ['userId'=>$userId], $request));
	}
})->setName("getDevices");

/**
     * @SWG\Get(
     *     path="/api/v1/Devices/{deviceId}",
     *     description="Returns a device",
     *     operationId="getDevice",
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
     *     )
     * )
     */
$app->get('/api/v1/Devices/{deviceId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('deviceId');
    $cls = Device::class;
    return $response->withJson($this->helper->Get($cls, $cls::$tableName, $id));
})->setName('getDevice');

/**
     * @SWG\Put(
     *     path="/api/v1/Devices/{deviceId}",
     *     description="Updates a device",
     *     operationId="putDevice",
     *     @SWG\Parameter(
     *         description="ID of device to update",
     *         format="int64",
     *         in="path",
     *         name="deviceId",
     *         required=true,
     *         type="integer"
     *     ), 
     *     @SWG\Parameter(
     *         name="device",
     *         in="body",
     *         description="Device to update in the store",
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
     *     )
     * )
     */
$app->put('/api/v1/Devices/{deviceId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('deviceId');
    $cls = Device::class;
    $objectArray = json_decode($request->getBody(), true);
    $this->helper->Update($cls, $objectArray, $cls::$tableName, $id);
    $url = $this->get('router')->pathFor('getDevice', ['deviceId' => $id]);
    return $response->withStatus(303)->withHeader('Location', $url);
})->setName('putDevice');

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
     *     )
     * )
     */
$app->post('/api/v1/Devices', function (Request $request, Response $response) {
    $objectArray = json_decode($request->getBody(), true);
	$cls = Device::class;
    $id = $this->helper->Insert($cls, $objectArray, $cls::$tableName);
    $url = $this->get('router')->pathFor('getDevice', ['deviceId' => $id]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postDevices");


/**
     * @SWG\get(
     *     path="/api/v1/Devices/LookupDeviceByBTAddress/{BTAddress}",
     *     description="Given BTAddress redirects to the path gets the device",
     *     operationId="getLookupDeviceByBTAddress",
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
     *     )
     * )
     */
$app->get('/api/v1/Devices/LookupDeviceByBTAddress/{BTAddress}', function (Request $request, Response $response) {
    $BTAddress = $request->getAttribute('BTAddress');
    $cls = Device::class;
    $sql = "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress";
    $device = $this->helper->GetBySql($cls, $sql, ['BTAddress'=>$BTAddress]);
    if ($device) {
		$url = $this->get('router')->pathFor('getDevice', ['deviceId' => $device->id]);
		return $response->withStatus(303)->withHeader('Location', $url);
	} else {
		return $response->withStatus(404);
	}
})->setName('getLookupDeviceByBTAddress');


#SUBDEVICE
/**
     * @SWG\Get(
     *     path="/api/v1/Devices/{deviceId}/SubDevices",
     *     description="Returns all subDevices of a device",
     *     operationId="getSubDevicesOfADevice",
     *     @SWG\Parameter(
     *         description="ID of device to get subDevices for",
     *         format="int64",
     *         in="path",
     *         name="deviceId",
     *         required=true,
     *         type="integer"
     *     ), 
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/SubDevice")
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
$app->get('/api/v1/Devices/{deviceId}/SubDevices', function (Request $request, Response $response) {
    $deviceId = $request->getAttribute('deviceId');
    $cls = SubDevice::class;
    $sql = "SELECT SubDevices.* FROM SubDevices JOIN Devices ON SubDevices.HeadBTAddress = Devices.BTAddress WHERE Devices.id = :deviceId";
    $subDevices = $this->helper->GetAllBySql($cls, $sql, ['deviceId'=>$deviceId], $request);
    return $response->withJson($subDevices);
})->setName("getSubDevicesOfADevice");

/**
     * @SWG\Get(
     *     path="/api/v1/SubDevices?sort={sort}",
     *     description="Returns all subDevices",
     *     operationId="getSubDevices",
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         description="columns to sort on",
     *         in="path",
     *         name="sort",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="subdevices response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/SubDevice")
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
$app->get('/api/v1/SubDevices', function (Request $request, Response $response) {
    $cls = SubDevice::class;
    return $response->withJson($this->helper->GetAll($cls, $cls::$tableName, $request));
})->setName("getSubDevices");

/**
     * @SWG\Get(
     *     path="/api/v1/SubDevices/{subDeviceId}",
     *     description="Returns a device",
     *     operationId="getSubDevice",
     *     @SWG\Parameter(
     *         description="ID of subDevice to fetch",
     *         format="int64",
     *         in="path",
     *         name="subDeviceId",
     *         required=true,
     *         type="integer"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="subDevice response",
     *         @SWG\Schema(
     *             ref="#/definitions/SubDevice"
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
$app->get('/api/v1/SubDevices/{subDeviceId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('subDeviceId');
    $cls = SubDevice::class;
    return $response->withJson($this->helper->Get($cls, $cls::$tableName, $id));
})->setName("getSubDevice");

/**
     * @SWG\Put(
     *     path="/api/v1/SubDevices/{subDeviceId}",
     *     description="Updates a subDevice",
     *     operationId="putSubDevice",
     *     @SWG\Parameter(
     *         description="ID of subDevice to update",
     *         format="int64",
     *         in="path",
     *         name="subDeviceId",
     *         required=true,
     *         type="integer"
     *     ), 
     *     @SWG\Parameter(
     *         name="subDevice",
     *         in="body",
     *         description="SubDevice to update in the store",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewSubDevice"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="device response",
     *         @SWG\Schema(
     *             ref="#/definitions/SubDevice"
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
$app->put('/api/v1/SubDevices/{subDeviceId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('subDeviceId');
    $cls = SubDevice::class;
    $objectArray = json_decode($request->getBody(), true);
    $this->helper->Update($cls, $objectArray, $cls::$tableName, $id);
	$url = $this->get('router')->pathFor('getSubDevice', ['subDeviceId' => $id]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("putSubDevice");

/**
     * @SWG\Put(
     *     path="/api/v1/SubDevices",
     *     description="Uses the given headBTAddress and distanceToHead in the json body to inserts the subdevice or redirects to the path that updates the subdevice",
     *     operationId="putSubDevices",
     *     @SWG\Parameter(
     *         name="subDevice",
     *         in="body",
     *         description="SubDevice to add to the store",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewSubDevice"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="subDevice response",
     *         @SWG\Schema(
     *             ref="#/definitions/SubDevice"
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
$app->put('/api/v1/SubDevices', function (Request $request, Response $response) {
	$objectArray = json_decode($request->getBody(), true);
    $headBTAddress = $objectArray['headBTAddress'];
    $distanceToHead = $objectArray['distanceToHead'];
    $cls = SubDevice::class;
    $sql = "SELECT * FROM {$cls::$tableName} WHERE headBTAddress = :headBTAddress AND distanceToHead = :distanceToHead";
    $subDevice = $this->helper->GetBySql($cls, $sql, ['headBTAddress'=>$headBTAddress, 'distanceToHead'=>$distanceToHead]);
    if ($subDevice == False) {
		# Not found so insert it
		$objectArray = json_decode($request->getBody(), true);
		$id = $this->helper->Insert($cls, $objectArray, $cls::$tableName);
		$url = $this->get('router')->pathFor('getSubDevice', ['subDeviceId' => $id]);
    	return $response->withStatus(303)->withHeader('Location', "http://localhost/api/v1/{$cls::$tableName}/$id");
	}
	$url = $this->get('router')->pathFor('putSubDevice', ['subDeviceId' => $subDevice->id]);
	return $response->withStatus(307)->withHeader('Location', $url);
})->setName("putSubDevices");

/**
     * @SWG\Post(
     *     path="/api/v1/Devices/{deviceId}/SubDevices",
     *     description="Adds a new subdevice",
     *     operationId="postSubDevice",
     *     @SWG\Parameter(
     *         description="ID of device to add the SubDevice to",
     *         format="int64",
     *         in="path",
     *         name="deviceId",
     *         required=true,
     *         type="integer"
     *     ), 
     *     @SWG\Parameter(
     *         name="subDevice",
     *         in="body",
     *         description="SubDevice to add to the store",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewSubDevice2"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="subDevice response",
     *         @SWG\Schema(
     *             ref="#/definitions/SubDevice"
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
$app->post('/api/v1/Devices/{deviceId}/SubDevices', function (Request $request, Response $response) {
    $deviceId = $request->getAttribute('deviceId');
    $cls = Device::class;
    $device = $this->helper->Get($cls, $cls::$tableName, $deviceId);
	$objectArray = json_decode($request->getBody(), true);
	$objectArray['headBTAddress'] = $device->BTAddress;
	$cls = SubDevice::class;
    $id = $this->helper->Insert($cls, $objectArray, $cls::$tableName);
    $url = $this->get('router')->pathFor('getSubDevice', ['subDeviceId' => $id]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postSubDevice");


# SUBDEVICESTATUS 
/**
     * @SWG\Get(
     *     path="/api/v1/SubDevices/{subDeviceId}/SubDeviceStatuses?sort={sort}&limit={limit}",
     *     description="Returns all statues of a subDevice",
     *     operationId="getSubDeviceStatuesOfASubDevice",
     *     @SWG\Parameter(
     *         description="ID of sub device to get statuses for",
     *         format="int64",
     *         in="path",
     *         name="subDeviceId",
     *         required=true,
     *         type="integer"
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
     *             @SWG\Items(ref="#/definitions/SubDeviceStatus")
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
$app->get('/api/v1/SubDevices/{subDeviceId}/SubDeviceStatuses', function (Request $request, Response $response) {
    $subDeviceId = $request->getAttribute('subDeviceId');
    $cls = SubDeviceStatus::class;
    $sort = $this->helper->getSort($cls, $request);
    $limit = $this->helper->getLimit($request);
    $sql = "SELECT SubDeviceStatuses.* FROM SubDeviceStatuses WHERE SubDeviceStatuses.SubDeviceId = :subDeviceId " . $sort . " " . $limit;
    $subDeviceStatuses = $this->helper->GetAllBySql($cls, $sql, ['subDeviceId'=>$subDeviceId], $request);
    return $response->withJson($subDeviceStatuses);
})->setName("getSubDeviceStatuesOfASubDevice");


/**
     * @SWG\Get(
     *     path="/api/v1/SubDeviceStatuses",
     *     description="Returns subDeviceStatuses",
     *     operationId="getSubDeviceStatuses",
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
     *         description="subDeviceStatuses response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/SubDeviceStatus")
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
$app->get('/api/v1/SubDeviceStatuses', function (Request $request, Response $response) {
    #sort=created&dir=asc&limit=1
	$cls = SubDeviceStatus::class;
    return $response->withJson($this->helper->GetAll($cls, $cls::$tableName, $request));
})->setName("getSubDeviceStatuses");


/**
     * @SWG\Get(
     *     path="/api/v1/SubDeviceStatuses/{subDeviceStatusId}",
     *     description="Returns a subDeviceStatus",
     *     operationId="getSubDeviceStatus",
     *     @SWG\Parameter(
     *         description="ID of the subDeviceStatus",
     *         format="int64",
     *         in="path",
     *         name="subDeviceStatusId",
     *         required=true,
     *         type="integer"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="subDeviceStatuses response",
     *         @SWG\Schema(
     *             ref="#/definitions/SubDeviceStatus"
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
$app->get('/api/v1/SubDeviceStatuses/{subDeviceStatusId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('subDeviceStatusId');
    $cls = SubDeviceStatus::class;
    return $response->withJson($this->helper->Get($cls, $cls::$tableName, $id));
})->setName("getSubDeviceStatus");


/**
     * @SWG\Post(
     *     path="/api/v1/SubDeviceStatuses",
     *     description="Adds a new subdeviceStatus",
     *     operationId="postSubDeviceStatus",
     *     @SWG\Parameter(
     *         name="subDeviceStatus",
     *         in="body",
     *         description="SubDevice to add to the store",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewSubDeviceStatus"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="subDevice response",
     *         @SWG\Schema(
     *             ref="#/definitions/SubDeviceStatus"
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
$app->post('/api/v1/SubDeviceStatuses', function (Request $request, Response $response) {
	$objectArray = json_decode($request->getBody(), true);
	$cls = SubDeviceStatus::class;
    $id = $this->helper->Insert($cls, $objectArray, $cls::$tableName);
    $url = $this->get('router')->pathFor('getSubDeviceStatus', ['subDeviceStatusId' => $id]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postSubDeviceStatus");



# MESSAGESTATS
/**
     * @SWG\Get(
     *     path="/api/v1/Devices/{deviceId}/MessageStats?sort={sort}&limit={limit}&outputType={outputType}",
     *     description="Returns MessageStats",
     *     operationId="getMessageStats",
     *     @SWG\Parameter(
     *         description="ID of device to get stats for",
     *         format="int64",
     *         in="path",
     *         name="deviceId",
     *         required=true,
     *         type="integer"
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
     *     )
     * )
     */
$app->get('/api/v1/Devices/{deviceId}/MessageStats', function (Request $request, Response $response) {
	$cls = MessageStat::class;
    $deviceId = $request->getAttribute('deviceId');
    $sort = $this->helper->getSort($cls, $request);
    $limit = $this->helper->getLimit($request);
    $outputType = $request->getQueryParam('outputType');
    $sql = '';
    if (strtolower($outputType) == 'aggregated') {
		$sql = "SELECT MessageStats.deviceId, MessageStats.adapterInstance, MessageStats.messageType, 
			(SELECT MessageStats.status FROM MessageStats ims WHERE ims.deviceId = MessageStats.deviceId 
			and ims.adapterInstance = MessageStats.adapterInstance and ims.messageType = MessageStats.messageType ORDER BY ims.createdTime desc LIMIT 1) as status, 
			sum(MessageStats.noOfMessages) as noOfMessages, max(MessageStats.updateTime) as updateTime, max(MessageStats.createdTime) as createdTime 
			FROM MessageStats WHERE MessageStats.deviceId = :deviceId GROUP BY MessageStats.deviceId, MessageStats.adapterInstance, 
			MessageStats.messageType, status " . $sort . " " . $limit;
	} else {
		$sql = "SELECT MessageStats.* FROM MessageStats WHERE MessageStats.DeviceId = :deviceId " . $sort . " " . $limit;
	}
    $deviceStats = $this->helper->GetAllBySql($cls, $sql, ['deviceId'=>$deviceId], $request);
    return $response->withJson($deviceStats);
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
     *     )
     * )
     */
$app->get('/api/v1/MessageStats', function (Request $request, Response $response) {
    #sort=created&dir=asc&limit=1&DeviceId=1
	$cls = MessageStat::class;
    return $response->withJson($this->helper->GetAll($cls, $cls::$tableName, $request));
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
     *     )
     * )
     */
$app->get('/api/v1/MessageStats/{statId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('statId');
    $cls = MessageStat::class;
    return $response->withJson($this->helper->Get($cls, $cls::$tableName, $id));
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
     *     )
     * )
     */
$app->post('/api/v1/MessageStats', function (Request $request, Response $response) {
	$objectArray = json_decode($request->getBody(), true);
	$cls = MessageStat::class;
    $id = $this->helper->Insert($cls, $objectArray, $cls::$tableName);
    $url = $this->get('router')->pathFor('getMessageStat', ['statId' => $id]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postMessageStat");

/**
     * @SWG\Post(
     *     path="/api/v1/MessageStats/{btAddress}",
     *     description="Adds a new MessageStat",
     *     operationId="postMessageStatByBTAddress",
     *     @SWG\Parameter(
     *         description="bt address",
     *         in="path",
     *         name="btAddress",
     *         required=true,
     *         type="string"
     *     ),
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
     *     )
     * )
     */
$app->post('/api/v1/MessageStats/{BTAddress}', function (Request $request, Response $response) {
	$BTAddress = $request->getAttribute('BTAddress');
    $cls = Device::class;
    $sql = "SELECT * FROM {$cls::$tableName} WHERE BTAddress = :BTAddress";
    $device = $this->helper->GetBySql($cls, $sql, ['BTAddress'=>$BTAddress]);
    
	$objectArray = json_decode($request->getBody(), true);
	$objectArray['deviceId'] = $device->id;
	$cls2 = MessageStat::class;
    $id = $this->helper->Insert($cls2, $objectArray, $cls2::$tableName);
    $url = $this->get('router')->pathFor('getMessageStat', ['statId' => $id]);
	return $response->withStatus(303)->withHeader('Location', $url);
})->setName("postMessageStatByBTAddress");


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
     *     )
     * )
     */
$app->get('/api/v1/UserDevices/{userDeviceId}', function (Request $request, Response $response) {
    $cls = UserDevice::class;
    $id = $request->getAttribute('userDeviceId');
	return $response->withJson($this->helper->Get($cls, $cls::$tableName, $id));
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
     *     )
     * )
     */
$app->get('/api/v1/UserDevices', function (Request $request, Response $response) {
    $cls = UserDevice::class;
    return $response->withJson($this->helper->GetAll($cls, $cls::$tableName, $request));
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
     *     )
     * )
     */
$app->post('/api/v1/UserDevices', function (Request $request, Response $response) {
    $cls = UserDevice::class;
    $objectArray = json_decode($request->getBody(), true);
    if (!array_key_exists("userId", $objectArray)) {
		$user = $request->getAttribute('user');
		$objectArray['userId'] = $user->id;
	}
    $id = $this->helper->Insert($cls, $objectArray, $cls::$tableName);
    $url = $this->get('router')->pathFor('getUserDevice', ['userDeviceId' => $id]);
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
     *     produces={"application/json"},
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
     *     )
     * )
     */
$app->delete('/api/v1/UserDevices/{userDeviceId}', function (Request $request, Response $response) {
	$id = $request->getAttribute('userDeviceId');
    $cls = UserDevice::class;
    $this->helper->Delete($id, $cls::$tableName);
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
     *     produces={"application/json"},
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
     *     )
     * )
     */
$app->delete('/api/v1/Devices/{deviceId}/UserDevices', function (Request $request, Response $response) {
	$deviceId = $request->getAttribute('deviceId');
    $cls = UserDevice::class;
    $sql = "DELETE FROM {$cls::$tableName} WHERE deviceId = :deviceId AND userId = :userId";
    $user = $request->getAttribute('user');
	$values = ['deviceId'=>$deviceId, 'userId'=>$user->id];
    $this->helper->DeleteBySql($sql, $values);
	return $response->withStatus(204);
})->setName("deleteUserDeviceByDevice");

$app->run();
