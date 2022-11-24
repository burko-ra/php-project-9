<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Flash\Messages;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use Carbon\Carbon;
use DiDom\Document;
use GuzzleHttp\Client;
use PageAnalyzer\Database;
use PageAnalyzer\Repositories\UrlCheckRepository;
use PageAnalyzer\Repositories\UrlRepository;
use Valitron\Validator;

use function PageAnalyzer\Support\Helpers\optional;
use function PageAnalyzer\Support\Helpers\normalizeUrl;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$container = new Container();

$container->set('flash', function () {
    return new Messages();
});

$container->set('database', function () {
    return new Database();
});

$container->set('client', function () {
    return new Client([
        'timeout'  => 5.0,
        'allow_redirects' => false,
        'http_errors' => false
    ]);
});

$container->set('urlRepository', function (\Psr\Container\ContainerInterface $c) {
    return new UrlRepository($c->get('database'));
});

$container->set('urlCheckRepository', function (\Psr\Container\ContainerInterface $c) {
    return new UrlCheckRepository($c->get('database'));
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$twig->getEnvironment()->addGlobal('flash', $container->get('flash'));
$app->add(TwigMiddleware::create($app, $twig));

$router = $app->getRouteCollector()->getRouteParser();

/**
 * @var Container $this
 */
$app->get('/', function ($request, $response) {
    $twig = Twig::fromRequest($request);
    return $twig->render($response, 'index.twig');
})->setName('index');

$app->post('/urls', function ($request, $response) use ($router) {
    $url = $request->getParsedBodyParam('url');
    $urlName = $url['name'];
    $validator = new Validator($url);
    $validator->rule('required', 'name')->message('URL не должен быть пустым');
    $validator->rule('url', 'name')->message('Некорректный URL');

    if (!$validator->validate()) {
        $errors = $validator->errors('name');
        $params = [
            'url' => ['name' => $urlName],
            'errors' => $errors,
        ];
        $twig = Twig::fromRequest($request);
        return $twig->render($response->withStatus(422), 'index.twig', $params);
    }

    $normalizedUrlName = normalizeUrl($urlName);

    $duplicateId = $this->get('urlRepository')->getIdByName($normalizedUrlName);
    if ($duplicateId === false) {
        $this->get('urlRepository')->add($normalizedUrlName);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        $id = $this->get('urlRepository')->getIdByName($normalizedUrlName);
    } else {
        $this->get('flash')->addMessage('info', 'Страница уже существует');
        $id = $duplicateId;
    }

    return $response->withRedirect($router->urlFor('urls.show', ['id' => (string) $id]), 302);
})->setName('urls.store');

$app->get('/urls/{id}', function ($request, $response, array $args) {
    $urlId = $args['id'];
    $url = $this->get('urlRepository')->getById($urlId);
    $urlChecks = $this->get('urlCheckRepository')->getById($urlId);
    $params = [
        'url' => $url,
        'urlChecks' => $urlChecks,
    ];
    $twig = Twig::fromRequest($request);
    return $twig->render($response, 'urls/show.twig', $params);
})->setName('urls.show');

$app->get('/urls', function ($request, $response) {
    $urls = $this->get('urlRepository')->all();
    $params = [
        'urls' => $urls,
    ];
    $twig = Twig::fromRequest($request);
    return $twig->render($response, 'urls/index.twig', $params);
})->setName('urls.index');

$app->post('/urls/{id}/checks', function ($request, $response, array $args) use ($router) {
    $urlId = $args['id'];

    $url = $this->get('urlRepository')->getById($urlId);
    if (is_null($url)) {
        throw new \Exception('Cannot access to Url');
    }

    $urlName = $url['name'];

    try {
    $pageResponse = $this->get('client')->get($urlName);
    $statusCode = $pageResponse->getStatusCode();
    $contents = $pageResponse
        ->getBody()
        ->getContents();
    $document = new Document($contents);

    $check = [
        'urlId' => $urlId,
        'statusCode' => $statusCode,
        'createdAt' => Carbon::now()->toDateTimeString(),
    ];

    if ($statusCode === 200) {
        $check['h1'] = optional($document->first('h1'))->text() ?? '';
        $check['title'] = optional($document->first('title'))->text() ?? '';
        $check['description'] = optional($document->first('meta[name=description]'))->getAttribute('content') ?? '';
    }

    $this->get('urlCheckRepository')->add($check);
    $this->get('flash')->addMessage('success', 'Страница успешно проверена');

    } catch (\Exception $e) {
        $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке');
    }

    return $response->withRedirect($router->urlFor('urls.show', ['id' => $urlId]), 302);
})->setName('urls.checks.store');

$app->run();
