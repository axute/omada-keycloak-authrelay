<?php

namespace AuthRelay;

use Psr\Http\Message\ServerRequestInterface as Request;
use SlimSession\Helper;
use Stevenmaguire\OAuth2\Client\Provider\Keycloak;


class Setup
{
    public static function getKeyCloakClient(Request $request): Keycloak
    {
        $redirectUri = self::getCurrentHostWithScheme($request) . self::getCallbackPath();
        self::log("Redirect URI: " . $redirectUri);
        return new Keycloak([
            'authServerUrl' => $_ENV['KEYCLOAK_SERVER_URL'],
            'realm' => $_ENV['KEYCLOAK_REALM'],
            'clientId' => $_ENV['KEYCLOAK_CLIENT_ID'],
            'clientSecret' => $_ENV['KEYCLOAK_CLIENT_SECRET'],
            'redirectUri' => $redirectUri,
        ]);
    }

    public static function getCurrentHostWithScheme(Request $request): string
    {
        $uri = $request->getUri();

        // Check for proxy headers to determine the real scheme
        $scheme = $uri->getScheme(); // Default from request

        // Check common proxy headers for the real scheme
        if ($request->hasHeader('X-Forwarded-Proto')) {
            $scheme = $request->getHeaderLine('X-Forwarded-Proto');
        } elseif ($request->hasHeader('X-Forwarded-Ssl') && $request->getHeaderLine('X-Forwarded-Ssl') === 'on') {
            $scheme = 'https';
        } elseif ($request->hasHeader('X-Url-Scheme')) {
            $scheme = $request->getHeaderLine('X-Url-Scheme');
        }

        // Check for forwarded host
        $host = $uri->getAuthority();
        if ($request->hasHeader('X-Forwarded-Host')) {
            $host = $request->getHeaderLine('X-Forwarded-Host');
        } elseif ($request->hasHeader('X-Original-Host')) {
            $host = $request->getHeaderLine('X-Original-Host');
        }

        // Fallback to environment variable if still not HTTPS and we expect it
        if ($scheme === 'http' && isset($_ENV['FORCE_HTTPS']) && $_ENV['FORCE_HTTPS'] === 'true') {
            $scheme = 'https';
        }

        return $scheme . '://' . $host;

    }

    public static function getCallbackPath(): string
    {
        return $_ENV['RELAY_CALLBACK_PATH'] ?? '/callback';
    }

    public static function getOmadaClient(): OmadaClient
    {
        $omadaUrl = $_ENV['OMADA_URL'];
        $omadaSiteId = $_ENV['OMADA_SITE_ID'];
        $omadaHotspotOperatorUsername = $_ENV['OMADA_HOTSPOT_OPERATOR_USERNAME'];
        $omadaHotspotOperatorPassword = $_ENV['OMADA_HOTSPOT_OPERATOR_PASSWORD'];
        return new OmadaClient($omadaUrl, $omadaSiteId, $omadaHotspotOperatorUsername, $omadaHotspotOperatorPassword);
    }

    public static function getSession(): Helper
    {
        return new Helper();
    }

    public static function getLifeTime(): int
    {
        return $_ENV['SESSION_LIFETIME'] ?? 3600 * 10;
    }

    public static function log(string $message): void
    {
        error_log($message);
    }

}
