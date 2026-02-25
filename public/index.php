<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

session_start(); // session

$data = file_get_contents("user.json") ?: '{}';
$users = json_decode($data, true);

$container = new Container();

// ПРАВИЛЬНАЯ настройка рендерера
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) use ($router) {
    $args = ['url' => $router->urlFor('users')];
    return $this->get('renderer')->render($response, 'index.phtml', $args);
});

$app->get('/users', function ($request, $response) use ($users, $router) {
    $term = $request->getQueryParam('term');
    $filtered = array_filter($users, function($user) use ($term) {
        return str_contains($user['nickname'], $term);
    });
    $flash = $this->get('flash')->getMessages();
    $params = [
        'users' => $filtered,
        'term' => $term,
        'urlForUsers' => $router->urlFor('users'),
        'urlForNew' => $router->urlFor('newUser'),
        'flash' => $flash
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/users/{id:[0-9]+}', function ($request, $response, $args) use ($users, $router) {
    $id = (int) $args['id'];
    $user = null;

    foreach ($users as $item) {
        if ($item['id'] === $id) {
            $user = $item;
            break;
        }
    }

    if ($user === null) {
        return $this->get('renderer')->render($response->withStatus(404), 'errors/404.phtml', [
            'message' => '404: Пользователь не найден',
            'url' => $router->urlFor('users')
        ]);
    }

    $params = ['user' => $user];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->get('/users/new', function ($request, $response) use ($router) {
    $args = ['url' => $router->urlFor('users')];
    return $this->get('renderer')->render($response, 'users/new.phtml', $args);
})->setName('newUser');

$app->post('/users', function ($request, $response) use ($users, $router) {
    $user = $request->getParsedBodyParam('user');
    $user['id'] = count($users) + 1;
    $users[] = $user;
    file_put_contents('user.json', json_encode($users));
    $this->get('flash')->addMessage('success', 'User was added successfully');
    return $response->withRedirect($router->urlFor('users'));
})->setName('addUser');

$app->run();

