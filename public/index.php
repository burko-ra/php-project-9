<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Flash\Messages;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use Carbon\Carbon;

use PageAnalyzer\Database;
use PageAnalyzer\Parser;
use PageAnalyzer\Repositories\UrlCheckRepository;
use PageAnalyzer\Repositories\UrlRepository;
use PageAnalyzer\WebPage;
use Valitron\Validator;

use function PageAnalyzer\UrlNormalizer\normalize;

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

    $normalizedUrlName = normalize($urlName);

    $duplicateId = $this->get('urlRepository')->getIdByName($normalizedUrlName);
    if ($duplicateId === false) {
        $this->get('urlRepository')->save($normalizedUrlName);
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

        $this->get('urlCheckRepository')->save($check);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке');
    }

    return $response->withRedirect($router->urlFor('urls.show', ['id' => $urlId]), 302);
})->setName('urls.checks.store');

$app->run();
