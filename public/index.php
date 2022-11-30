<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use GuzzleHttp\Client;
use Slim\Flash\Messages;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Twig\Extra\Html\HtmlExtension;
use Twig\Loader\FilesystemLoader;

use Carbon\Carbon;
use DiDom\Document;
use PageAnalyzer\Database;
use PageAnalyzer\Repositories\UrlCheckRepository;
use PageAnalyzer\Repositories\UrlRepository;
use Valitron\Validator;

use function PageAnalyzer\UrlNormalizer\normalizeUrl;

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

$container->set('urlConnection', function () {
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

$container->set('view', function (\Psr\Container\ContainerInterface $c) {
    $loader = new FilesystemLoader(__DIR__ . '/../templates');
    $twig = new Twig($loader, ['cache' => false]);
    $twig->getEnvironment()->addGlobal('flash', $c->get('flash'));
    $twig->addExtension(new HtmlExtension());
    return $twig;
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);
$app->add(TwigMiddleware::createFromContainer($app));

$router = $app->getRouteCollector()->getRouteParser();

/**
 * @var Container $this
 */
$app->get('/', function ($request, $response) {
    return $this->get('view')->render($response, 'index.twig');
})->setName('index');

$app->post('/urls', function ($request, $response) use ($router) {
    $url = $request->getParsedBodyParam('url');
    $urlName = $url['name'];
    $validator = new Validator($url);
    $validator->rule('required', 'name')->message('URL не должен быть пустым');
    $validator->rule('url', 'name')->message('Некорректный URL');

    if (!$validator->validate()) {
        $params = [
            'url' => ['name' => $urlName],
            'errors' => $validator->errors('name'),
        ];
        return $this->get('view')->render($response->withStatus(422), 'index.twig', $params);
    }

    $normalizedUrlName = normalizeUrl($urlName);

    $id = $this->get('urlRepository')->getIdByName($normalizedUrlName);
    if ($id === false) {
        $this->get('urlRepository')->add($normalizedUrlName);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        $id = $this->get('urlRepository')->getIdByName($normalizedUrlName);
    } else {
        $this->get('flash')->addMessage('info', 'Страница уже существует');
    }

    return $response->withRedirect($router->urlFor('urls.show', ['id' => (string) $id]), 302);
})->setName('urls.store');

$app->get('/urls/{id}', function ($request, $response, array $args) {
    $urlId = $args['id'];
    $url = $this->get('urlRepository')->getById($urlId);
    if (is_null($url)) {
        throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $urlChecks = $this->get('urlCheckRepository')->getById($urlId);
    $params = [
        'url' => $url,
        'urlChecks' => $urlChecks,
    ];
    return $this->get('view')->render($response, 'urls/show.twig', $params);
})->setName('urls.show');

$app->get('/urls', function ($request, $response) {
    $urls = $this->get('urlRepository')->all();
    $params = [
        'urls' => $urls,
    ];
    return $this->get('view')->render($response, 'urls/index.twig', $params);
})->setName('urls.index');

$app->post('/urls/{id}/checks', function ($request, $response, array $args) use ($router) {
    $urlId = $args['id'];
    $url = $this->get('urlRepository')->getById($urlId);
    if (is_null($url)) {
        throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $urlName = $url['name'];
    try {
        $pageResponse = $this->get('urlConnection')->get($urlName);
        $statusCode = $pageResponse->getStatusCode();
        $contents = $pageResponse
            ->getBody()
            ->getContents();

        $check = [
            'urlId' => $urlId,
            'statusCode' => $statusCode,
            'createdAt' => Carbon::now()->toDateTimeString(),
        ];

        if ($statusCode === 200) {
            $document = new Document($contents);
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
