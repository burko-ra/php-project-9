<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;
use Carbon\Carbon;

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

//$databaseUrl = parse_url($_ENV['DATABASE_URL']);
//dump($databaseUrl);
//phpinfo();
//print_r(PDO::getAvailableDrivers());

$app->get('/', function ($request, $response) {
    $params = [
        'url' => ['name' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'index.html', $params);
});

$app->post('/urls', function ($request, $response) {
    $url = $request->getParsedBodyParam('url');
    $urlName = $url['name'];
    $urlParts = parse_url($urlName);
    var_dump($urlParts);
    $validator = new Valitron\Validator($urlParts);
    $validator->rule('required', ['scheme', 'host']);
    if(!$validator->validate()) {
        echo "Yay! We're all bad :(";
        $params = [
            'url' => ['name' => $url['name']],
            'errors' => $validator->errors()
        ];
        return $this->get('renderer')->render($response, 'index.html', $params);
    }
    $dbh = new PDO('postgresql://ephemeris:123@localhost:8000/project-3');
    // $sqlInsertUrl = 'INSERT INTO urls (name, created_at) VALUES
    //     (:name, :created_at)';
    // $queryInsertUrl = $dbh->prepare($sqlInsertUrl);
    // $queryInsertUrl->execute([':name' => $urlName, ':created_at' => Carbon::now()->toDateTimeString()]);

    return $response->withRedirect('/urls', 302);
});

$app->get('/urls', function ($request, $response) {
    $params = [];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
});

$app->run();
