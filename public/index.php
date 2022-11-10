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

$container->set('urlRepo', function () {
    return new UrlRepo(new Database());
});

$container->set('checkRepo', function () {
    return new CheckRepo(new Database());
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$router = $app->getRouteCollector()->getRouteParser();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$app->get('/', function ($request, $response) {
    $params = [
        'url' => ['name' => ''],
        'errors' => []
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
    $errors = $validator->errors();

    if (!empty($errors)) {
        $params = [
            'url' => ['name' => $urlName],
            'errors' => array_slice($errors['name'], 0, 1)
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
});

$app->get('/urls/{id}', function ($request, $response, array $args) {
    $flash = $this->get('flash')->getMessages();
    $urlId = htmlspecialchars($args['id']);
    $url = $this->get('urlRepo')->getById($urlId);
    $urlChecks = $this->get('checkRepo')->getById($urlId);
    $params = [
        'url' => $url,
        'urlChecks' => $urlChecks,
        'flash' => $flash
    ];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) {
    $urls = $this->get('urlRepo')->all();
    $params = ['urls' => $urls];
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
});

$app->run();
