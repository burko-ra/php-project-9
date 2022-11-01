<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;

use function PageAnalyzer\Engine\getUrlErrors;
use function PageAnalyzer\Engine\normalizeUrl;
use function PageAnalyzer\Engine\isUrlUnique;
use function PageAnalyzer\Engine\insertUrl;
use function PageAnalyzer\Engine\getUrlId;
use function PageAnalyzer\Engine\getUrlInfo;
use function PageAnalyzer\Engine\getUrls;
use function PageAnalyzer\Engine\insertUrlCheck;
use function PageAnalyzer\Engine\getUrlChecks;
use function PageAnalyzer\Engine\getParsedData;
use GuzzleHttp\Client;

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

// $client = new Client([
//     'base_uri' => $url,
//     'timeout'  => 5.0,
//     'allow_redirects' => false
// ]);

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

$app->post('/urls', function ($request, $response) use ($router) {
    $url = $request->getParsedBodyParam('url');
    $urlName = $url['name'];

    $errors = getUrlErrors($urlName);

    if (count($errors) !== 0) {
        $params = [
            'url' => ['name' => $url['name']],
            'errors' => $errors
        ];
        return $this->get('renderer')->render($response, 'index.phtml', $params);
    }

    $normalizedUrlName = normalizeUrl($urlName);

    if (isUrlUnique($normalizedUrlName)) {
        insertUrl($normalizedUrlName);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    } else {
        $this->get('flash')->addMessage('info', 'Страница уже существует');
    }

    $id = (string) getUrlId($normalizedUrlName);
    return $response->withRedirect($router->urlFor('url', ['id' => $id]), 302);
});

$app->get('/urls/{id}', function ($request, $response, array $args) {
    $flash = $this->get('flash')->getMessages();
    $urlId = $args['id'];
    $url = getUrlInfo($urlId);
    $urlChecks = getUrlChecks($urlId);
    $params = [
        'url' => $url,
        'urlChecks' => $urlChecks,
        'flash' => $flash
    ];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) {
    $urls = getUrls();
    $params = ['urls' => $urls];
    return $this->get('renderer')->render($response, 'urls/index.phtml', $params);
})->setName('urls');

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) use ($router) {
    $urlId = $args['url_id'];
    $url = getUrlInfo($urlId);
    $urlName = $url['name'];
    $client = new Client([
        'base_uri' => $urlName,
        'timeout'  => 5.0,
        'allow_redirects' => false
    ]);
    $parsedData = getParsedData($urlName, $client);
    if ($parsedData === false) {
        $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке');
    } else {
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
        insertUrlCheck($urlId, $parsedData);
    }

    // $params = [
    //     'url' => $url,
    //     'urlChecks' => [],
    //     'flash' => []
    // ];
    // return $this->get('renderer')->render($response, 'show.phtml', $params);
    return $response->withRedirect($router->urlFor('url', ['id' => $urlId]), 302);
});

$app->run();
