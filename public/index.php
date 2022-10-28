<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;
use Carbon\Carbon;
use Valitron\Validator;

use function PageAnalyzer\Engine\getUrlErrors;
use function PageAnalyzer\Engine\normalizeUrl;
use function PageAnalyzer\Engine\isUrlUnique;
use function PageAnalyzer\Engine\insertUrl;
use function PageAnalyzer\Engine\getUrlId;
use function PageAnalyzer\Engine\getUrlInfo;
use function PageAnalyzer\Engine\getUrls;

/**
 * @var Container $this
 */

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$app->get('/', function ($request, $response) {
    $params = [
        'url' => ['name' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
});

$test = Carbon::now()->toDateTimeString();
$test2 = new Validator($urlParts);
$app->post('/urls', function ($request, $response) {
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
    }

    $id = getUrlId($normalizedUrlName);
    return $response->withRedirect("/urls/{$id}", 302);
});

$app->get('/urls/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    $urlInfo = getUrlInfo($id);
    $params = [
        'url' => [
            'id' => $urlInfo['id'],
            'name' => $urlInfo['name'],
            'date' => $urlInfo['created_at']
        ],
    ];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
});

$app->get('/urls', function ($request, $response) {
    $urls = getUrls();
    $params = ['urls' => $urls];
    return $this->get('renderer')->render($response, 'urls/index.phtml', $params);
});

$app->run();
