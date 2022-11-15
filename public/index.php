<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;

use Carbon\Carbon;
use PageAnalyzer\CheckRepo;
use PageAnalyzer\Database;
use PageAnalyzer\Parser;
use PageAnalyzer\UrlNormalizer;
use PageAnalyzer\UrlRepo;
use PageAnalyzer\WebPage;
use Valitron\Validator;

$container = new Container();

$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set('database', function () {
    return new Database();
});

$container->set('urlRepo', function (\Psr\Container\ContainerInterface $c) {
    return new UrlRepo($c->get('database'));
});

$container->set('checkRepo', function (\Psr\Container\ContainerInterface $c) {
    return new CheckRepo($c->get('database'));
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$router = $app->getRouteCollector()->getRouteParser();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * @var Container $this
 */
$app->get('/', function ($request, $response) use ($router) {
    $params = [
        'url' => ['name' => ''],
        'errors' => [],
        'router' => $router
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('root');

$app->post('/urls', function ($request, $response) use ($router) {
    $url = $request->getParsedBodyParam('url');
    $urlName = htmlspecialchars($url['name']);
    $validator = new Validator(['name' => $urlName]);
    $validator->rule('required', 'name')->message('URL не должен быть пустым');
    $validator->rule('lengthMax', 'name', 255)->message('Некорректный URL');
    $validator->rule('url', 'name')->message('Некорректный URL');
    $validator->validate();
    $errors = $validator->errors('name');

    if (is_array($errors) && count($errors) > 0) {
        $params = [
            'url' => ['name' => $urlName],
            'errors' => array_slice($errors, 0, 1),
            'router' => $router
        ];
        return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', $params);
    }

    $normalizer = new UrlNormalizer();
    $normalizedUrlName = $normalizer->normalize($urlName);

    $duplicate = $this->get('urlRepo')->getByName($normalizedUrlName);
    if ($duplicate === false) {
        $this->get('urlRepo')->save($normalizedUrlName);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    } else {
        $this->get('flash')->addMessage('info', 'Страница уже существует');
    }

    $newUrl = $this->get('urlRepo')->getByName($normalizedUrlName);
    if ($newUrl === false) {
        throw new \Exception('Cannot access to Url');
    }
    $id = (string) $newUrl['id'];
    return $response->withRedirect($router->urlFor('url', ['id' => $id]), 302);
})->setName('urlsPost');

$app->get('/urls/{id}', function ($request, $response, array $args) use ($router) {
    $flash = $this->get('flash')->getMessages();
    $urlId = htmlspecialchars($args['id']);
    $url = $this->get('urlRepo')->getById($urlId);
    $urlChecks = $this->get('checkRepo')->getById($urlId);
    $params = [
        'url' => $url,
        'urlChecks' => $urlChecks,
        'flash' => $flash,
        'router' => $router
    ];
    return $this->get('renderer')->render($response, 'urls/show.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) use ($router) {
    $urls = $this->get('urlRepo')->all();
    $params = [
        'urls' => $urls,
        'router' => $router
    ];
    return $this->get('renderer')->render($response, 'urls/index.phtml', $params);
})->setName('urls');

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) use ($router) {
    $urlId = htmlspecialchars($args['url_id']);

    $url = $this->get('urlRepo')->getById($urlId);
    if (is_null($url)) {
        throw new \Exception('Cannot access to Url');
    }

    $urlName = $url['name'];
    $parser = new Parser();
    try {
        $statusCode = $parser->getStatusCode($urlName);

        $check = [
            'urlId' => $urlId,
            'statusCode' => $statusCode,
            'createdAt' => Carbon::now()->toDateTimeString(),
        ];

        if ($statusCode === 200) {
            $html = $parser->getHtml($urlName);
            $webPage = new WebPage($html);
            $check['h1'] = $webPage->getFirstTagInnerText('h1') ?? '';
            $check['title'] = $webPage->getFirstTagInnerText('title') ?? '';
            $check['description'] = $webPage->getDescription() ?? '';
        }

        $this->get('checkRepo')->save($check);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке');
    }

    return $response->withRedirect($router->urlFor('url', ['id' => $urlId]), 302);
})->setName('checksPost');

$app->run();
