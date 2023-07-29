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
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->publicRoutesArray = array('postLogin', 'getDevicesView', 
            'getMessageStats', 'getMessageStat', 'getMessageStatsOfADevice', 
            'ApiV1', 'postUser', 'getDeviceUpdateDeviceName',
            'getDeviceSetBatteryIsLow', 'getDevice', 'getPing', 'postLogArchives', 
            'getWiRocPython2Releases', 'getReleaseStatuses', 'getReleaseStatus',
            'getWiRocPython2Release', 'getWiRocBLEAPIReleases', 'getWiRocBLEAPIRelease',
            'getDeviceStatuses', 'getDeviceStatusesByBTAddress', 'getDeviceStatus',
            'getCompetition', 'getCompetitions', 'getLogin');
        $this->apiKeyRoutesArray = array('postMessageStat', 'postDeviceSetConnectedToInternetTime',
            'postDevices', 'postDeviceStatus', 'postLogArchives', 'postCreateTables', 'postReleaseStatus', 
            'deleteReleaseStatus', 'postWiRocPython2Release', 
            'deleteWiRocPython2Release', 'postWiRocBLEAPIRelease', 'deleteWiRocBLEAPIRelease',
            'getWiRocPython2ReleaseUpgradeScripts', 'getWiRocBLEAPIReleaseUpgradeScripts');
        $this->adminRoutesArray = array('postReleaseStatus', 'deleteReleaseStatus');
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
        
        if (!empty($_SESSION['userId'])) {
            if (in_array($routeName, $this->adminRoutesArray)) {
                if ($_SESSION['userIsAdmin']) {
                    return True;
                } else {
                    return False;
                }
            }
            # Logged in uses can access all routes (incl public and api key routes) 
            # except the admin routes, for that admin permission is required
            return True;
        }
        
        
        if (in_array($routeName, $this->apiKeyRoutesArray)) {
            $apiKey = $request->getHeaderLine("X-Authorization");
            $container = $this->app->getContainer();
            $correctApiKey = $container->get('config')['api_key'];
            if ($apiKey == $correctApiKey) {
                return True;
            } else {
                return False;
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