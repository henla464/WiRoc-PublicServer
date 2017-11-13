<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Doctrine\Common\Annotations\AnnotationReader;

require '../vendor/autoload.php';
#require_once('../classes/Device.php');
#require_once('../classes/ErrorModel.php');
\Doctrine\Common\Annotations\AnnotationRegistry::registerLoader('class_exists');

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['host']   = "localhost";
$config['db']['user']   = "wiroc";
$config['db']['pass']   = "wiroc";
$config['db']['dbname'] = "wiroc";

$app = new \Slim\App(["settings" => $config]);
$container = $app->getContainer();
$annotationReader = new AnnotationReader();

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

/**
 * @SWG\Info(title="WiRoc Monitor API", version="1")
 */

/**
 * @SWG\Get(
 *     path="/api/v1",
 *     @SWG\Response(response="200", description="An example resource")
 * )
 */
$app->get('/api/v1', function($request, $response, $args) use ($app) {
    $swagger = \Swagger\scan(['.', '../classes']);
    header('Content-Type: application/json');
    echo $swagger;
});

/**
 * @SWG\Get(
 *     path="/swagger/docs",
 *     @SWG\Response(response="200", description="Redirects to the swagger for the latest API version")
 * )
 */
$app->get('/swagger/docs', function($request, $response, $args) {
    return $response->withStatus(303)->withHeader('Location', 'http://localhost/api/v1');
});


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
});

# DEVICES
/**
     * @SWG\Get(
     *     path="/api/v1/Devices",
     *     description="Returns all devices",
     *     operationId="getDevices",
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
    return $response->withJson($this->helper->GetAll($cls, $cls::$tableName));
});

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
});

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
    return $response->withStatus(303)->withHeader('Location', "http://localhost/api/v1/{$cls::$tableName}/$id");
});

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
	return $response->withStatus(303)->withHeader('Location', "http://localhost/api/v1/{$cls::$tableName}/$id");
});

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
    $subDevices = $this->helper->GetAllBySql($cls, $sql, ['deviceId'=>$deviceId]);
    return $response->withJson($subDevices);
});

/**
     * @SWG\Get(
     *     path="/api/v1/SubDevices",
     *     description="Returns all subDevices",
     *     operationId="getSubDevices",
     *     produces={"application/json"},
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
    return $response->withJson($this->helper->GetAll($cls, $cls::$tableName));
});

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
});

/**
     * @SWG\Put(
     *     path="/api/v1/SubDevices/{subDeviceId}",
     *     description="Updates a device",
     *     operationId="putDevice",
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
	return $response->withStatus(303)->withHeader('Location', "http://localhost/api/v1/{$cls::$tableName}/$id"); 
});

/**
     * @SWG\Put(
     *     path="/api/v1/SubDevices/LookupUpdateSubDeviceByHeadBTAddressAndDistanceToHead/{headBTAddress}/{distanceToHead}",
     *     description="Given headBTAddress and distanceToHead redirects to the path that updates the subdevice",
     *     operationId="putSubDeviceLookupUpdateSubDeviceByHeadBTAddressAndDistanceToHead",
     *     @SWG\Parameter(
     *         description="BT Address of head Device",
     *         format="int64",
     *         in="path",
     *         name="headBTAddress",
     *         required=true,
     *         type="string"
     *     ), 
     *     @SWG\Parameter(
     *         description="No of hops to the head",
     *         format="int64",
     *         in="path",
     *         name="distanceToHead",
     *         required=true,
     *         type="integer"
     *     ), 
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
$app->put('/api/v1/SubDevices/LookupUpdateSubDeviceByHeadBTAddressAndDistanceToHead/{headBTAddress}/{distanceToHead}', function (Request $request, Response $response) {
    $headBTAddress = $request->getAttribute('headBTAddress');
    $distanceToHead = $request->getAttribute('distanceToHead');
    $cls = SubDevice::class;
    $sql = "SELECT * FROM {$cls::$tableName} WHERE headBTAddress = :headBTAddress AND distanceToHead = :distanceToHead";
    $subDevice = $this->helper->GetBySql($cls, $sql, ['headBTAddress'=>$headBTAddress, 'distanceToHead'=>$distanceToHead]);
	return $response->withStatus(307)->withHeader('Location', "http://localhost/api/v1/{$cls::$tableName}/{$subDevice->id}");
});

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
	return $response->withStatus(303)->withHeader('Location', "http://localhost/api/v1/{$cls::$tableName}/$id");
});


# SUBDEVICESTATUS

/**
     * @SWG\Get(
     *     path="/api/v1/SubDeviceStatuses",
     *     description="Returns subDeviceStatuses",
     *     operationId="getSubDeviceStatuses",
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
    #sort=created&dir=asc&limit=1&SubDeviceId=1
	$cls = SubDeviceStatus::class;
    return $response->withJson($this->helper->GetAll($cls, $cls::$tableName));
});


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
});


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
	return $response->withStatus(303)->withHeader('Location', "http://localhost/api/v1/{$cls::$tableName}/$id");
});


# INPUTMESSAGESTATS
/**
     * @SWG\Get(
     *     path="/api/v1/InputMessageStats",
     *     description="Returns InputMessageStats",
     *     operationId="getInputMessageStats",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="InputMessageStats response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/InputMessageStat")
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
$app->get('/api/v1/InputMessageStats', function (Request $request, Response $response) {
    #sort=created&dir=asc&limit=1&DeviceId=1
	$cls = InputMessageStat::class;
    return $response->withJson($this->helper->GetAll($cls, $cls::$tableName));
});

/**
     * @SWG\Get(
     *     path="/api/v1/InputMessageStats/{statId}",
     *     description="Returns an InputMessageStat",
     *     operationId="getInputMessageStat",
     *     @SWG\Parameter(
     *         description="ID of the InputMessageStat",
     *         format="int64",
     *         in="path",
     *         name="statId",
     *         required=true,
     *         type="integer"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="InputMessageStat response",
     *         @SWG\Schema(
     *             ref="#/definitions/InputMessageStat"
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
$app->get('/api/v1/InputMessageStats/{statId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('statId');
    $cls = InputMessageStat::class;
    return $response->withJson($this->helper->Get($cls, $cls::$tableName, $id));
});

/**
     * @SWG\Post(
     *     path="/api/v1/InputMessageStats",
     *     description="Adds a new InputMessageStat",
     *     operationId="postInputMessageStats",
     *     @SWG\Parameter(
     *         name="inputMessageStat",
     *         in="body",
     *         description="InputMessageStat to add to the store",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewInputMessageStat"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="inputMessageStat response",
     *         @SWG\Schema(
     *             ref="#/definitions/InputMessageStat"
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
$app->post('/api/v1/InputMessageStats', function (Request $request, Response $response) {
	$objectArray = json_decode($request->getBody(), true);
	$cls = InputMessageStat::class;
    $id = $this->helper->Insert($cls, $objectArray, $cls::$tableName);
	return $response->withStatus(303)->withHeader('Location', "http://localhost/api/v1/{$cls::$tableName}/$id");
});

# OUTPUTMESSAGESTATS
/**
     * @SWG\Get(
     *     path="/api/v1/OutputMessageStats",
     *     description="Returns OutputMessageStats",
     *     operationId="getOutputMessageStats",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="OutputMessageStats response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/OutputMessageStat")
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
$app->get('/api/v1/OutputMessageStats', function (Request $request, Response $response) {
    #sort=created&dir=asc&limit=1&DeviceId=1
	$cls = OutputMessageStat::class;
    return $response->withJson($this->helper->GetAll($cls, $cls::$tableName));
});

/**
     * @SWG\Get(
     *     path="/api/v1/OutputMessageStats/{statId}",
     *     description="Returns an OutputMessageStat",
     *     operationId="getOutputMessageStat",
     *     @SWG\Parameter(
     *         description="ID of the OutputMessageStat",
     *         format="int64",
     *         in="path",
     *         name="statId",
     *         required=true,
     *         type="integer"
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="OutputMessageStat response",
     *         @SWG\Schema(
     *             ref="#/definitions/OutputMessageStat"
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
$app->get('/api/v1/OutputMessageStats/{statId}', function (Request $request, Response $response) {
    $id = $request->getAttribute('statId');
    $cls = OutputMessageStat::class;
    return $response->withJson($this->helper->Get($cls, $cls::$tableName, $id));
});

/**
     * @SWG\Post(
     *     path="/api/v1/OutputMessageStats",
     *     description="Adds a new OutputMessageStat",
     *     operationId="postOutputMessageStat",
     *     @SWG\Parameter(
     *         name="outputMessageStat",
     *         in="body",
     *         description="OutputMessageStat to add to the store",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewOutputMessageStat"),
     *     ),
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=200,
     *         description="outputMessageStat response",
     *         @SWG\Schema(
     *             ref="#/definitions/OutputMessageStat"
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
$app->post('/api/v1/OutputMessageStats', function (Request $request, Response $response) {
	$objectArray = json_decode($request->getBody(), true);
	$cls = OutputMessageStat::class;
    $id = $this->helper->Insert($cls, $objectArray, $cls::$tableName);
	return $response->withStatus(303)->withHeader('Location', "http://localhost/api/v1/{$cls::$tableName}/$id");
});



$app->run();
