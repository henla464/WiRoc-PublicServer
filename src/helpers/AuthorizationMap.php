<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Slim\Exception\HttpNotFoundException;

class AuthorizationMap {
    private $app;
    private $publicRoutesArray;
    private $apiKeyRoutesArray; 
    private $adminRoutesArray;
    private $loggedInRoutesArray;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->publicRoutesArray = array(
            'ApiV1',
            'getDocs',
            'getPing',
            'getLogin',
            'postLogin',
            'postUser',
            'getDevices',
            'getDevicesView',
            'getDevice',
            'getDeviceStatusesByBTAddress',
            'getDeviceStatuses',
            'getDeviceStatus',
            'getMessageStatsOfADevice',
            'getMessageStats',
            'getMessageStat',
            'getCompetitions',
            'getCompetition');

        
        
            //'getDeviceUpdateDeviceName',
            //'getDeviceSetBatteryIsLow', '', '', 'postLogArchives', 
            //'getWiRocPython2Releases', 'getReleaseStatuses', 'getReleaseStatus',
            //'getWiRocPython2Release', 'getWiRocBLEAPIReleases', 'getWiRocBLEAPIRelease',

        $this->apiKeyRoutesArray = array(
            'postCreateTables',
            'getDeviceUpdateDeviceName',
            'getDeviceSetBatteryIsLow',
            'getDeviceSetBatteryIsNormal',
            'getDeviceSetBatteryIsLowReceived',
            'getDeviceSetBatteryIsNormalReceived',
            'postDevices',
            'postDeviceSetConnectedToInternetTime',
            'postDeviceStatus',
            'postMessageStat',
            'postLogArchives',
            'getWiRocPython2Releases',
            'getWiRocBLEAPIReleases',
            'getWiRocBLEAPIReleaseUpgradeScripts',
            'getWiRocPython2ReleaseUpgradeScripts');

            //'postCreateTables', 'postReleaseStatus', 
            //'deleteReleaseStatus', 'postWiRocPython2Release', 
            //'deleteWiRocPython2Release', 'postWiRocBLEAPIRelease', 'deleteWiRocBLEAPIRelease',
            
        $this->adminRoutesArray = array(
            
            'getUsers',
            'patchUser',
            'getDeviceUpdateDeviceName',
            'getDeviceSetBatteryIsLow',
            'getDeviceSetBatteryIsLowReceived',
            'deleteDevice',
            'postDevices',
            'postDeviceSetConnectedToInternetTime',
            'postDeviceStatus',
            'postMessageStat',
            'postLogArchives',
            'getWiRocPython2Releases',
            'getWiRocPython2Release',
            'postWiRocPython2Release',
            'deleteWiRocPython2Release',
            'getWiRocBLEAPIReleases',
            'getWiRocBLEAPIRelease',
            'postWiRocBLEAPIRelease',
            'deleteWiRocBLEAPIRelease',
            'getReleaseStatuses',
            'getReleaseStatus',
            'postReleaseStatus',
            'deleteReleaseStatus',
            'getWiRocBLEAPIReleaseUpgradeScripts',
            'getWiRocBLEAPIReleaseUpgradeScript',
            'postWiRocBLEAPIReleaseUpgradeScript',
            'deleteWiRocBLEAPIReleaseUpgradeScript',
            'getWiRocPython2ReleaseUpgradeScripts',
            'getWiRocPython2ReleaseUpgradeScript',
            'postWiRocPython2ReleaseUpgradeScript',
            'deleteWiRocPython2ReleaseUpgradeScript');
        
        $this->loggedInRoutesArray = array(
            'getLogout',
            'postDeviceSetCompetition',
            'deleteDeviceStatusesByBTAddress',
            'deleteMessageStatsByBTAddress',
            'postCompetition',
            'deleteCompetition');
    }

    public function needsAuthorization(ServerRequestInterface $request) {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        if(empty($route)) { 
            $response = $this->app->getResponseFactory()->createResponse(404);
            throw new HttpNotFoundException($request, $response); 
        }
        $routeName = $route->getName();
        return !in_array($routeName, $this->publicRoutesArray);
    }
    
    public function isAuthorized(ServerRequestInterface $request) {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        if(empty($route)) { 
            $response = $this->app->getResponseFactory()->createResponse(404);
            throw new HttpNotFoundException($request, $response); 
        }
        
        $routeName = $route->getName();
        
        if (in_array($routeName, $this->apiKeyRoutesArray)) {
            $apiKey = $request->getHeaderLine("X-Authorization");
            $container = $this->app->getContainer();
            $correctApiKey = $container->get('config')['api_key'];
            if ($apiKey == $correctApiKey) {
                return True;
            }
        }

        if (!empty($_SESSION['userId'])) {
            if (in_array($routeName, $this->adminRoutesArray)) {
                if ($_SESSION['userIsAdmin']) {
                    return True;
                }
            }
            
            if (in_array($routeName, $this->loggedInRoutesArray)) {
                return True;
            }
        }
        
        return False;
    }

    public function prepareUnauthorizedResponse() {
        $response = $this->app->getResponseFactory()->createResponse(401);
        return $response;
    }

    
    public function signResponse(ResponseInterface $response, ServerRequestInterface $request) {
	return $response;
    }
}
