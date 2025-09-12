<?php

namespace AuthRelay;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthRelayController extends Setup
{
    const AP_FIELDS = ['clientMac', 'ssidName', 'apMac', 'radioId', 'site', 'time', 'authType'];
    const GATEWAY_FIELDS = ['clientMac', 'gatewayMac', 'vid', 'site', 'time', 'authType'];
    const AUTH_TYPE_EXTERNAL = '4';
    const ERROR_CLIENT_AUTH_FAILED = 'Client Authorisierung an Omada Controller fehlgeschlagen';
    const ERROR_ADMIN_LOGIN_FAILED = 'Admin Login an Omada Controller fehlgeschlagen';

    public static function index(Request $request, Response $response): Response
    {
        $getParams = $request->getQueryParams();
        self::log("GET params: " . json_encode($getParams));
        self::getSession()->set('request', json_encode($getParams));

        return self::handleOidcAuthentic($request, $response);

    }

    private static function handleOidcAuthentic(Request $request, Response $response): Response
    {
        $keycloak = self::getKeyCloakClient($request);
        $authUrl = $keycloak->getAuthorizationUrl([
            'scope' => ['openid', 'profile', 'email'],
        ]);
        self::getSession()->set('keycloak_state', $keycloak->getState());
        return $response->withHeader('Location', $authUrl)->withStatus(302);
    }

    public static function callbackRoute(Request $request, Response $response): Response
    {
        $getParams = $request->getQueryParams();
        self::log("GET params: " . json_encode($getParams));
        if (empty($getParams['state']) || empty($getParams['code']) || $getParams['state'] !== self::getSession()->get('keycloak_state')) {
            self::getSession()->delete('keycloak_state');
            $response->getBody()->write('Invalid state');
            return $response->withStatus(400);
        }
        $keycloak = self::getKeyCloakClient($request);

        try {
            $token = $keycloak->getAccessToken('authorization_code', [
                    'code' => $getParams['code']
                ]
            );
            $userInfo = $keycloak->getResourceOwner($token)->toArray();
            self::log("UserInfo: " . json_encode($userInfo));
            self::getSession()->set('userinfo', $userInfo);
            $username = $userInfo['preferred_username'] ?? '';
            $omadaClient = self::getOmadaClient();
            if (!$omadaClient->login()) {
                self::log(self::ERROR_ADMIN_LOGIN_FAILED);
                return self::createErrorResponse($response, self::ERROR_ADMIN_LOGIN_FAILED);
            }

            self::log("Admin Login an Omada Controller erfolgreich");

            $clientData = self::prepareClientData();
            if (!$omadaClient->authorizeClient($clientData)) {
                self::log(self::ERROR_CLIENT_AUTH_FAILED);
                return self::createErrorResponse($response, self::ERROR_CLIENT_AUTH_FAILED);
            }

            return self::createSuccessRedirect($response, $clientData, $username);

        } catch (Exception $e) {
            self::log($e->getMessage());
            return self::createErrorResponse($response, $e->getMessage());
        } catch (GuzzleException $e) {
            self::log($e->getMessage());
            return self::createErrorResponse($response, $e->getMessage());
        }
    }

    private static function createErrorResponse(Response $response, string $errorMessage): Response
    {
        $response->getBody()->write($errorMessage);
        return $response;
    }

    private static function prepareClientData(): array
    {
        $requestData = json_decode($_SESSION['request'], true);
        $requestData['time'] = (self::getLifeTime()) * 1000;
        $requestData['authType'] = self::AUTH_TYPE_EXTERNAL;

        $fieldKeys = array_key_exists('apMac', $requestData) ? self::AP_FIELDS : self::GATEWAY_FIELDS;

        $clientData = [];
        foreach ($fieldKeys as $key) {
            if (isset($requestData[$key])) {
                $clientData[$key] = $requestData[$key];
            }
        }

        return $clientData;
    }

    private static function createSuccessRedirect(Response $response, array $clientData, string $username): Response
    {
        $requestData = json_decode($_SESSION['request'], true);
        self::log("Session: " . json_encode($_SESSION));
        return $response->withHeader('Location', $requestData['redirectUrl'])->withStatus(302);
    }
}
