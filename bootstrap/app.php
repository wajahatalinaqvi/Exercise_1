<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        
    )
  // Source - https://stackoverflow.com/a/78686097
// Posted by reza_qsr
// Retrieved 2026-04-27, License - CC BY-SA 4.0

->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        
        'exercise-1-artwork-version',   
    ]);
})

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
