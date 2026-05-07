<?php

namespace App\Core;

use ReflectionClass;
use ReflectionParameter;
use Exception;

class Container
{
    protected array $instances = [];
    protected array $bindings = [];

    /**
     * Registrar um bind manual
     */
    public function bind(string $abstract, callable $resolver)
    {
        $this->bindings[$abstract] = $resolver;
    }

    /**
     * Registrar singleton
     */
    public function singleton(string $abstract, callable $resolver)
    {
        $this->bindings[$abstract] = function ($container) use ($resolver, $abstract) {
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = $resolver($container);
            }
            return $this->instances[$abstract];
        };
    }

    /**
     * Resolver classe automaticamente
     */
    public function get(string $abstract)
    {
        // Se tiver binding manual
        if (isset($this->bindings[$abstract])) {
    return ($this->bindings[$abstract])($this);
}

        return $this->build($abstract);
    }

    /**
     * Construção automática via Reflection
     */
    protected function build(string $class)
    {
        if (!class_exists($class)) {
            throw new Exception("Classe {$class} não existe");
        }

        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new Exception("Classe {$class} não é instanciável");
        }

        $constructor = $reflection->getConstructor();

        // Sem construtor → instancia direto
        if (!$constructor) {
            return new $class;
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $dependencies[] = $this->resolveDependency($parameter);
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolver dependência individual
     */
    protected function resolveDependency(ReflectionParameter $parameter)
    {
        $type = $parameter->getType();

        if (!$type) {
            throw new Exception("Não foi possível resolver parâmetro {$parameter->getName()}");
        }

        $name = $type->getName();

        // Se for classe → resolve recursivamente
        if (!$type->isBuiltin()) {
            return $this->get($name);
        }

        throw new Exception("Tipo primitivo não suportado automaticamente: {$parameter->getName()}");
    }
}