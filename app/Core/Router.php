<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, mixed>> */
    private array $routes = [
        'GET'  => [],
        'POST' => [],
    ];

    /**
     * Registra rota GET.
     * Aceita: \Closure, [$obj, 'metodo'], [Classe::class, 'metodo(static|instance)'].
     */
    public function get(string $path, mixed $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    /**
     * Registra rota POST.
     * Aceita: \Closure, [$obj, 'metodo'], [Classe::class, 'metodo(static|instance)'].
     */
    public function post(string $path, mixed $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    /**
     * Despacha a rota atual.
     */
    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = $this->normalize($path);

        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        $this->invoke($handler);
    }

    /* ================== Internos ================== */

    private function normalize(string $path): string
    {
        // Normaliza barra final (/foo/ -> /foo), mantendo a raiz "/"
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    /**
     * Invoca o handler suportando diferentes formatos.
     *
     * @param mixed $handler
     */
    private function invoke(mixed $handler): void
    {
        // 1) Closure direto
        if ($handler instanceof \Closure) {
            $handler();
            return;
        }

        // 2) [$obj, 'metodo'] ou [Classe::class, 'metodo']
        if (is_array($handler) && count($handler) === 2) {
            [$target, $method] = $handler;

            // 2.1) [$obj, 'metodo'] (instância)
            if (is_object($target) && is_string($method) && method_exists($target, $method)) {
                $target->{$method}();
                return;
            }

            // 2.2) [Classe::class, 'metodo'] (estático OU tentar instanciar sem args)
            if (is_string($target) && is_string($method) && class_exists($target) && method_exists($target, $method)) {
                try {
                    $refMethod = new \ReflectionMethod($target, $method);
                    if ($refMethod->isStatic()) {
                        $target::$method();
                        return;
                    }

                    $refClass = new \ReflectionClass($target);
                    $ctor     = $refClass->getConstructor();
                    if ($ctor === null || $ctor->getNumberOfRequiredParameters() === 0) {
                        $instance = $refClass->newInstance();
                        $instance->{$method}();
                        return;
                    }

                    http_response_code(500);
                    echo '500 Handler inválido: o método "' . $target . '::' . $method
                        . "\" não é estático e a classe exige dependências no construtor.\n"
                        . "Registre a rota com a instância: [\$controller, '{$method}']";
                    return;

                } catch (\ReflectionException $e) {
                    // cair no fallback
                }
            }
        }

        // 3) call_user_func em qualquer outro callable
        if (is_callable($handler)) {
            call_user_func($handler);
            return;
        }

        // 4) Handler inválido
        http_response_code(500);
        echo '500 Handler inválido para a rota.';
    }
}