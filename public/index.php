<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$data = file_get_contents("user.json") ?: '{}';
$users = json_decode($data, true);

$container = new Container();

// ПРАВИЛЬНАЯ настройка рендерера
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($response) use ($router) {
    $args = ['url' => $router->urlFor('users')];
    return $this->get('renderer')->render($response, 'index.phtml', $args);
});

$app->get('/users', function ($request, $response) use ($users, $router) {
    $term = $request->getQueryParam('term');
    $filtered = array_filter($users, function($user) use ($term) {
        return str_contains($user['nickname'], $term);
    });
    $params = [
        'users' => $filtered,
        'term' => $term,
        'urlForUsers' => $router->urlFor('users'),
        'urlForNew' => $router->urlFor('newUser')
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/users/{id:[0-9]+}', function ($response, $args) use ($users, $router) {
    $id = (int) $args['id'];
    $user = null;

    foreach ($users as $item) {
        if ($item['id'] === $id) {
            $user = $item;
            break;
        }
    }

    if ($user === null) {
        return $response->withRedirect($router->urlFor('users'));
    }

    $params = ['user' => $user];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->get('/users/new', function ($response) use ($router) {
    $args = ['url' => $router->urlFor('users')];
    return $this->get('renderer')->render($response, 'users/new.phtml', $args);
})->setName('newUser');

$app->post('/users', function ($request, $response) use ($users, $router) {
    $user = $request->getParsedBodyParam('user');
    $user['id'] = count($users) + 1;
    $users[] = $user;
    file_put_contents('user.json', json_encode($users));
    return $response->withRedirect($router->urlFor('users'));
})->setName('addUser');

$app->run();
