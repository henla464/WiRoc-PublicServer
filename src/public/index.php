<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';
require_once('../classes/Device.php');
require_once('../classes/ErrorModel.php');
\Doctrine\Common\Annotations\AnnotationRegistry::registerLoader('class_exists');

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['host']   = "localhost";
$config['db']['user']   = "wiroc";
$config['db']['pass']   = "wiroc";
$config['db']['dbname'] = "wiroc";

$app = new \Slim\App(["settings" => $config]);
$container = $app->getContainer();

$container['logger'] = function($c) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler("../logs/app.log");
    $logger->pushHandler($file_handler);
    return $logger;
};

$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
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
    #return $app->response->redirect($app->urlFor('swagger'), 303);
    return $response->withStatus(303)->withHeader('Location', 'http://localhost/api/v1');
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
    #$name = $request->getAttribute('name');
    $response->getBody()->write("Hello");

    return $response;
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
    $deviceId = $request->getAttribute('deviceId');
    #$response->getBody()->write("Hello, $name");

    return $response;
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
    #$deviceId = $request->getAttribute('deviceId');
    #$response->getBody()->write("Hello, $name");

    return $response;
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
    #$deviceId = $request->getAttribute('id');
    #$response->getBody()->write("Hello, $name");

    return $response;
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
    #$response->getBody()->write("Hello, $name");

    return $response;
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
    #$deviceId = $request->getAttribute('deviceId');
    #$response->getBody()->write("Hello, $name");

    return $response;
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
    $subDeviceId = $request->getAttribute('subDeviceId');
    #$response->getBody()->write("Hello, $name");

    return $response;
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
    $subDeviceId = $request->getAttribute('subDeviceId');
    #$response->getBody()->write("Hello, $name");

    return $response;
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
    #$response->getBody()->write("Hello, $name");
# redirect
    return $response;
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
$app->post('/api/v1/Devices/{deviceId}/SubDevices', function (Request $request, Response $response) {
    $deviceId = $request->getAttribute('deviceId');
    #$response->getBody()->write("Hello, $name");

    return $response;
});

/**
     * @SWG\Post(
     *     path="/api/v1/SubDevices/LookupAddSubDeviceByHeadBTAddress/{headBTAddress}",
     *     description="Adds a new subdevice to a device given headBTAddress",
     *     operationId="postSubDevice",
     *     @SWG\Parameter(
     *         description="BT Address of head Device",
     *         format="int64",
     *         in="path",
     *         name="headBTAddress",
     *         required=true,
     *         type="string"
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
$app->post('/api/v1/SubDevices/LookupAddSubDeviceByHeadBTAddress/{headBTAddress}', function (Request $request, Response $response) {
    $headBTAddress = $request->getAttribute('headBTAddress');
    #$response->getBody()->write("Hello, $name");
# redirect
    return $response;
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
    #$response->getBody()->write("Hello, $name");
# redirect
    return $response;
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
    $subDeviceStatusId = $request->getAttribute('subDeviceStatusId');
    #$response->getBody()->write("Hello, $name");

    return $response;
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
    #$response->getBody()->write("Hello, $name");
# redirect
    return $response;
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
    #$response->getBody()->write("Hello, $name");
# redirect
    return $response;
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
    $statId = $request->getAttribute('statId');
    #$response->getBody()->write("Hello, $name");

    return $response;
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
    #$response->getBody()->write("Hello, $name");
# redirect
    return $response;
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
    #$response->getBody()->write("Hello, $name");
# redirect
    return $response;
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
    $statId = $request->getAttribute('statId');
    #$response->getBody()->write("Hello, $name");

    return $response;
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
    #$response->getBody()->write("Hello, $name");
# redirect
    return $response;
});



$app->run();
