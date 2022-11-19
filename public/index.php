<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use Carbon\Carbon;
use PageAnalyzer\CheckRepository;
use PageAnalyzer\Database;
use PageAnalyzer\Parser;
use PageAnalyzer\UrlRepository;
use PageAnalyzer\WebPage;
use Valitron\Validator;

use function PageAnalyzer\UrlNormalizer\normalize;

$container = new Container();

$container->set('view', function () {
    return Twig::create(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set('database', function () {
    return new Database();
});

$container->set('urlRepository', function (\Psr\Container\ContainerInterface $c) {
    return new UrlRepository($c->get('database'));
});

$container->set('checkRepository', function (\Psr\Container\ContainerInterface $c) {
    return new CheckRepository($c->get('database'));
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);
$app->add(TwigMiddleware::createFromContainer($app));

$router = $app->getRouteCollector()->getRouteParser();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * @var Container $this
 */
$app->get('/', function ($request, $response) {
    $flash = $this->get('flash')->getMessages();
    $params = [
        'url' => ['name' => ''],
        'errors' => [],
        'flash' => $flash,
    ];
    return $this->get('view')->render($response, 'index.twig', $params);
})->setName('root');

$app->post('/urls', function ($request, $response) use ($router) {
    $flash = $this->get('flash')->getMessages();
    $url = $request->getParsedBodyParam('url');
    $urlName = $url['name'];
    $validator = new Validator(['name' => $urlName]);
    $validator->rule('required', 'name')->message('URL не должен быть пустым');
    $validator->rule('url', 'name');

    if (!$validator->validate()) {
        $errors = $validator->errors('name');
        $params = [
            'url' => ['name' => $urlName],
            'errors' => $errors,
            'flash' => $flash,
        ];
        return $this->get('view')->render($response->withStatus(422), 'index.twig', $params);
    }

    $normalizedUrlName = normalize($urlName);

    $duplicate = $this->get('urlRepository')->getIdByName($normalizedUrlName);
    if ($duplicate === false) {
        $this->get('urlRepository')->save($normalizedUrlName);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    } else {
        $this->get('flash')->addMessage('info', 'Страница уже существует');
    }

    $id = $this->get('urlRepository')->getIdByName($normalizedUrlName);
    if ($id === false) {
        throw new \Exception('Cannot access to Url');
    }
    return $response->withRedirect($router->urlFor('url', ['id' => (string) $id]), 302);
})->setName('urlsPost');

$app->get('/urls/{id}', function ($request, $response, array $args) {
    $flash = $this->get('flash')->getMessages();
    $urlId = $args['id'];
    $url = $this->get('urlRepository')->getById($urlId);
    $urlChecks = $this->get('checkRepository')->getById($urlId);
    $params = [
        'url' => $url,
        'urlChecks' => $urlChecks,
        'flash' => $flash,
    ];
    return $this->get('view')->render($response, 'urls/show.twig', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) {
    $flash = $this->get('flash')->getMessages();
    $urls = $this->get('urlRepository')->all();
    $params = [
        'urls' => $urls,
        'flash' => $flash,
    ];
    return $this->get('view')->render($response, 'urls/index.twig', $params);
})->setName('urls');

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

        $this->get('checkRepository')->save($check);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке');
    }

    return $response->withRedirect($router->urlFor('url', ['id' => $urlId]), 302);
})->setName('checksPost');

$app->run();
