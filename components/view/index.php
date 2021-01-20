<?php

require_once 'vendor/autoload.php';
require_once '../../src/App.php';

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Zeuxisoo\Whoops\Slim\WhoopsMiddleware;

/**
 * Illuminate/view
 *
 * Requires: illuminate/filesystem
 *
 * @source https://github.com/illuminate/view
 */

// we have to bind our app class to the interface
// as the blade compiler needs the `getNamespace()` method to guess Blade component FQCNs
App::getInstance()->instance(\Illuminate\Contracts\Foundation\Application::class, App::getInstance());

// Instantiate App
$app = AppFactory::create();

// Middleware
$app->add(new WhoopsMiddleware(['enable' => true]));

$app->get('/', function (Request $request, Response $response) {
    // Configuration
    // Note that you can set several directories where your templates are located
    $pathsToTemplates = [__DIR__ . '/templates'];
    $pathToCompiledTemplates = __DIR__ . '/compiled';

    // Dependencies
    $filesystem = new Filesystem;
    App::getInstance()->instance('files', $filesystem);
    $eventDispatcher = new Dispatcher(new Container);

    // Create View Factory capable of rendering PHP and Blade templates
    $viewResolver = new EngineResolver;
    $bladeCompiler = new BladeCompiler($filesystem, $pathToCompiledTemplates);

    $viewResolver->register('blade', function () use ($bladeCompiler) {
        return new CompilerEngine($bladeCompiler);
    });

    $viewResolver->register('php', function () {
        return App::getInstance()->make(PhpEngine::class);
    });

    $viewFinder = new FileViewFinder($filesystem, $pathsToTemplates);
    $viewFactory = new Factory($viewResolver, $viewFinder, $eventDispatcher);
    $viewFactory->setContainer(App::getInstance());

    App::getInstance()->instance(\Illuminate\Contracts\View\Factory::class, $viewFactory);
    App::getInstance()->alias(\Illuminate\Contracts\View\Factory::class, 'view');

    // Render template with page.blade.php
    $templateData = [
        'title' => 'Title',
        'text' => 'This is my text!',
    ];

    $response->getBody()->write(
        $viewFactory->make('page', $templateData)->render()
    );

    return $response;
});

$app->run();
