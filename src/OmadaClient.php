<?php

namespace AuthRelay;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class OmadaClient
{
    private Client $client;
    private string $baseUri;

    public function __construct(string $schemeHostPort, string $controllerId, protected string $operatorUsername, protected string $operatorPassword)
    {
        $this->baseUri = sprintf("%s/%s/", rtrim($schemeHostPort, '/'), trim($controllerId, '/'));
        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'cookies' => true,
            'verify' => false, // für selbstsignierte Zertifikate
        ]);
    }

    public function login(): bool
    {
        try {
            $response = $this->client->post('api/v2/hotspot/login', [
                'json' => [
                    'name' => $this->operatorUsername,
                    'password' => $this->operatorPassword,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ]
            ]);
        } catch (GuzzleException $e) {
            Setup::log($e->getMessage());
            return false;
        }

        $body = json_decode($response->getBody(), true);

        if ($body['errorCode'] === 0 && isset($body['result']['token'])) {
            Setup::getSession()->set('csrf_token', $body['result']['token']);
            return true;
        }
        Setup::log("Login fehlgeschlagen: " . json_encode($body));
        return false;
    }

    public function authorizeClient(array $clientData): bool
    {
        $csrfToken = Setup::getSession()->get('csrf_token');
        if (!$csrfToken) {
            setup::log("CSRF-Token nicht vorhanden. Bitte zuerst einloggen.");
            return false;
        }

        try {
            Setup::log("Authentifizierungsanfrage: " . json_encode($clientData));
            Setup::log("CSRF-Token: " . $csrfToken);
            $response = $this->client->post('api/v2/hotspot/extPortal/auth', [
                'json' => $clientData,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Csrf-Token' => $csrfToken,
                ]
            ]);
        } catch (GuzzleException $e) {
            Setup::log($e->getMessage());
            return false;
        }

        $body = json_decode($response->getBody(), true);
        if ($body['errorCode'] === 0) {

            return true;
        }
        Setup::log("Login fehlgeschlagen: " . json_encode($body));
        return false;
    }
}
