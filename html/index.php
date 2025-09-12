<?php
require_once('../setup.php');

use AuthRelay\AuthRelayController;
use AuthRelay\Setup;
use Slim\Factory\AppFactory;
use Slim\Handlers\Strategies\RequestResponseArgs;
use Slim\Middleware\Session;

$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);

$app->add(new Session([
    'name' => 'omada_session',
    'autorefresh' => true,
    'lifetime' => '24 hour'
]));

$routeCollector = $app->getRouteCollector();
$routeCollector->setDefaultInvocationStrategy(new RequestResponseArgs());
$app->get('/test', function ($request, $response) {
    $response->getBody()->write('Hello World');
    return $response;
});
$app->get('/', AuthRelayController::index(...));
$app->get(Setup::getCallbackPath(), AuthRelayController::callbackRoute(...));
$app->run();
