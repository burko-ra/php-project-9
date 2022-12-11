<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Flash\Messages;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Psr7\Response;
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

$container->set('httpClient', function () {
    return new Client([
        'timeout'  => 3.0,
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
    $twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
    $twig->getEnvironment()->addGlobal('flash', $c->get('flash'));
    $twig->addExtension(new HtmlExtension());
    return $twig;
});

$app = AppFactory::createFromContainer($container);
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container) {
    try {
        return $handler->handle($request);
    } catch (HttpNotFoundException $httpException) {
        $response = (new Response())->withStatus(404);
        return $container->get('view')->render($response, 'errors/404.twig');
    }
});
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
    $requestBodyParams = $request->getParsedBody();
    $validator = new Validator($requestBodyParams);
    $validator->rule('required', 'url.name')->message('URL не должен быть пустым');
    $validator->rule('url', 'url.name')->message('Некорректный URL');

    if (!$validator->validate()) {
        $params = [
            'request' => $requestBodyParams,
            'errors' => $validator->errors(),
        ];
        return $this->get('view')->render($response->withStatus(422), 'index.twig', $params);
    }

    $normalizedUrlName = normalizeUrl($requestBodyParams['url']['name']);
    $urlRepository = $this->get('urlRepository');

    $urls = $urlRepository->getBy($normalizedUrlName, 'name');
    if (empty($urls)) {
        $urlRepository->add($normalizedUrlName);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        $urls = $urlRepository->getBy($normalizedUrlName, 'name');
    } else {
        $this->get('flash')->addMessage('info', 'Страница уже существует');
    }

    $id = $urls[0]['id'];
    return $response->withRedirect($router->urlFor('urls.show', ['id' => (string) $id]), 302);
})->setName('urls.store');

$app->get('/urls/{id:[0-9]+}', function ($request, $response, array $args) {
    $urlId = $args['id'];
    $url = $this->get('urlRepository')->getById($urlId);
    if (is_null($url)) {
        throw new HttpNotFoundException($request);
    }

    $urlChecks = $this->get('urlCheckRepository')->getBy($urlId, 'url_id');
    $params = [
        'url' => $url,
        'urlChecks' => $urlChecks,
    ];
    return $this->get('view')->render($response, 'urls/show.twig', $params);
})->setName('urls.show');

$app->get('/urls', function ($request, $response) {
    $urls = $this->get('urlRepository')->all();
    $checks = $this->get('urlCheckRepository')->getDistinct();
    $groupedChecks = collect($checks)->keyBy('url_id')->toArray();

    $params = [
        'urls' => $urls,
        'checks' => $groupedChecks
    ];

    return $this->get('view')->render($response, 'urls/index.twig', $params);
})->setName('urls.index');

$app->post('/urls/{id:[0-9]+}/checks', function ($request, $response, array $args) use ($router) {
    $urlId = $args['id'];
    $url = $this->get('urlRepository')->getById($urlId);
    if (is_null($url)) {
        throw new HttpNotFoundException($request);
    }

    $urlName = $url['name'];
    try {
        $pageResponse = $this->get('httpClient')->get($urlName);
        $statusCode = $pageResponse->getStatusCode();
        $contents = $pageResponse
            ->getBody()
            ->getContents();

        $check = [
            'urlId' => $urlId,
            'statusCode' => $statusCode,
            'createdAt' => Carbon::now()
        ];

        if ($statusCode >= 200 && $statusCode < 300) {
            $document = new Document($contents);
            $check['h1'] = optional($document->first('h1'))->text();
            $check['title'] = optional($document->first('title'))->text();
            $check['description'] = optional($document->first('meta[name=description]'))->getAttribute('content');
        }

        $this->get('urlCheckRepository')->add($check);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (GuzzleException $e) {
        $this->get('flash')->addMessage('danger', 'Страница успешно проверена');
        // $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке');
    }

    return $response->withRedirect($router->urlFor('urls.show', ['id' => $urlId]), 302);
})->setName('urls.checks.store');

$app->run();
