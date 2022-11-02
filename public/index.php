<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;

use Carbon\Carbon;
use PageAnalyzer\CheckRepo;
use PageAnalyzer\Connection;
use PageAnalyzer\Parser;
use PageAnalyzer\UrlRepo;
use PageAnalyzer\Validator;
use PageAnalyzer\WebPage;

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$router = $app->getRouteCollector()->getRouteParser();

$connection = new Connection();
$urlRepo = new UrlRepo($connection);
$checkRepo = new CheckRepo($connection);
$validator = new Validator();

session_start();

/**
 * @var Container $this
 */

$app->get('/', function ($request, $response) {
    $params = [
        'url' => ['name' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('root');

$app->post('/urls', function ($request, $response) use ($router, $urlRepo, $validator) {
    $url = $request->getParsedBodyParam('url');
    $urlName = htmlspecialchars($url['name']);

    $errors = $validator->validate($urlName);
    if (count($errors) !== 0) {
        $params = [
            'url' => ['name' => $urlName],
            'errors' => $errors
        ];
        return $this->get('renderer')->render($response, 'index.phtml', $params);
    }

    $normalizedUrlName = $validator->normalize($urlName);

    $duplicate = $urlRepo->findByName($normalizedUrlName);
    if ($duplicate === false) {
        $urlRepo->save($normalizedUrlName);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    } else {
        $this->get('flash')->addMessage('info', 'Страница уже существует');
    }

    $newUrl = $urlRepo->findByName($normalizedUrlName);
    if ($newUrl === false) {
        throw new \Exception('Cannot access to Url');
    }
    $id = (string) $newUrl['id'];
    // $params = [
    //     'url' => ['name' => $urlName],
    //     'errors' => $errors
    // ];
    // return $this->get('renderer')->render($response, 'index.phtml', $params);
    return $response->withRedirect($router->urlFor('url', ['id' => $id]), 302);
});

$app->get('/urls/{id}', function ($request, $response, array $args) use ($urlRepo, $checkRepo) {
    $flash = $this->get('flash')->getMessages();
    $urlId = htmlspecialchars($args['id']);
    $url = $urlRepo->findById($urlId);
    $urlChecks = $checkRepo->getById($urlId);
    $params = [
        'url' => $url,
        'urlChecks' => $urlChecks,
        'flash' => $flash
    ];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) use ($urlRepo) {
    $urls = $urlRepo->all();
    $params = ['urls' => $urls];
    return $this->get('renderer')->render($response, 'urls/index.phtml', $params);
})->setName('urls');

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) use ($router, $urlRepo, $checkRepo) {
    $urlId = htmlspecialchars($args['url_id']);

    $url = $urlRepo->findById($urlId);
    if ($url === false) {
        throw new \Exception('Cannot access to Url');
    }

    $urlName = $url['name'];
    $parser = new Parser($urlName);
    try {
        $statusCode = $parser->getStatusCode();

        $html = $parser->getHtml();
        $webPage = new WebPage($html);

        $check = [
            'urlId' => $urlId,
            'statusCode' => $statusCode,
            'createdAt' => Carbon::now()->toDateTimeString(),
        ];

        if ($statusCode === 200) {
            $check['h1'] = $webPage->getFirstTagInnerText('h1') ?? '';
            $check['title'] = $webPage->getFirstTagInnerText('title') ?? '';
            $check['description'] = $webPage->getDescription() ?? '';
        }

        $checkRepo->save($check);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке');
    }

    return $response->withRedirect($router->urlFor('url', ['id' => $urlId]), 302);
});

$app->run();
