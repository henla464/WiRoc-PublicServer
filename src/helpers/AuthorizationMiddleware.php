<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;




class AuthorizationMiddleware implements MiddlewareInterface
{
    private $authorizationMap;

    public function __construct(AuthorizationMap $authorizationMap)
    {
        $this->authorizationMap = $authorizationMap;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->authorizationMap->needsAuthorization($request)) {
            return $handler->handle($request);
        }

        if (! $this->authorizationMap->isAuthorized($request)) {
            return $this->authorizationMap->prepareUnauthorizedResponse();
        }

        $response = $handler->handle($request);
        return $this->authorizationMap->signResponse($response, $request);
    }
}
