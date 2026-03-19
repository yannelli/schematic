<?php

namespace Yannelli\Schematic;

use Closure;
use InvalidArgumentException;

class Compiler
{
    /**
     * Registered macros: name => Closure.
     */
    protected array $macros = [];

    /**
     * Register a custom macro.
     *
     * Usage:
     *   $compiler->macro('component', fn (string $name) => Component::render($name));
     *
     * In template:
     *   @component("my-component")
     */
    public function macro(string $name, Closure $handler): static
    {
        $this->macros[$name] = $handler;

        return $this;
    }

    /**
     * Check if a macro is registered.
     */
    public function hasMacro(string $name): bool
    {
        return isset($this->macros[$name]);
    }

    /**
     * Remove a registered macro.
     */
    public function removeMacro(string $name): static
    {
        unset($this->macros[$name]);

        return $this;
    }

    /**
     * Get all registered macro names.
     */
    public function registeredMacros(): array
    {
        return array_keys($this->macros);
    }

    /**
     * Compile a template string with data.
     *
     * Supports:
     *   - Variable substitution: {{ variable_name }}
     *   - Dot notation: {{ patient.name }}
     *   - Macros: @macroName("arg1", "arg2", ...)
     *   - Conditionals: @if(variable) ... @endif
     *   - Loops: @foreach(items as item) ... @endforeach
     */
    public function compile(string $content, array $data = []): string
    {
        $content = $this->compileConditionals($content, $data);
        $content = $this->compileLoops($content, $data);
        $content = $this->compileMacros($content, $data);
        $content = $this->compileVariables($content, $data);

        return trim($content);
    }

    /**
     * Resolve @macroName("arg1", "arg2") patterns.
     */
    protected function compileMacros(string $content, array $data): string
    {
        // Match @macroName(...) but not @if, @endif, @foreach, @endforeach
        $reserved = ['if', 'endif', 'foreach', 'endforeach', 'else'];

        return preg_replace_callback(
            '/@(\w+)\(([^)]*)\)/',
            function (array $matches) use ($data, $reserved) {
                $name = $matches[1];
                $rawArgs = $matches[2];

                if (in_array($name, $reserved, true)) {
                    return $matches[0]; // Don't touch reserved directives
                }

                if (! isset($this->macros[$name])) {
                    return $matches[0]; // Leave unresolved macros as-is
                }

                $args = $this->parseArguments($rawArgs, $data);

                return (string) call_user_func_array($this->macros[$name], $args);
            },
            $content
        );
    }

    /**
     * Resolve {{ variable }} and {{ variable.nested }} patterns.
     */
    protected function compileVariables(string $content, array $data): string
    {
        return preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}/',
            function (array $matches) use ($data) {
                $key = trim($matches[1]);
                $value = data_get($data, $key);

                if ($value === null) {
                    return $matches[0]; // Leave unresolved variables as-is
                }

                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }

                return (string) $value;
            },
            $content
        );
    }

    /**
     * Compile @if(variable) ... @else ... @endif blocks.
     */
    protected function compileConditionals(string $content, array $data): string
    {
        // Handle @if ... @else ... @endif
        $content = preg_replace_callback(
            '/@if\((.+?)\)(.*?)@else(.*?)@endif/s',
            function (array $matches) use ($data) {
                $condition = trim($matches[1]);
                $truthy = $matches[2];
                $falsy = $matches[3];

                $value = data_get($data, $condition);

                return $value ? trim($truthy) : trim($falsy);
            },
            $content
        );

        // Handle @if ... @endif (no else)
        return preg_replace_callback(
            '/@if\((.+?)\)(.*?)@endif/s',
            function (array $matches) use ($data) {
                $condition = trim($matches[1]);
                $body = $matches[2];

                $value = data_get($data, $condition);

                return $value ? trim($body) : '';
            },
            $content
        );
    }

    /**
     * Compile @foreach(items as item) ... @endforeach blocks.
     */
    protected function compileLoops(string $content, array $data): string
    {
        return preg_replace_callback(
            '/@foreach\((\w+)\s+as\s+(\w+)\)(.*?)@endforeach/s',
            function (array $matches) use ($data) {
                $collectionKey = $matches[1];
                $itemAlias = $matches[2];
                $body = $matches[3];

                $collection = data_get($data, $collectionKey);

                if (! is_iterable($collection)) {
                    return '';
                }

                $output = [];
                foreach ($collection as $index => $item) {
                    $loopData = array_merge($data, [
                        $itemAlias => $item,
                        'loop_index' => $index,
                    ]);
                    $output[] = $this->compile($body, $loopData);
                }

                return implode("\n", $output);
            },
            $content
        );
    }

    /**
     * Parse macro arguments, resolving quoted strings and variable references.
     */
    protected function parseArguments(string $rawArgs, array $data): array
    {
        if (trim($rawArgs) === '') {
            return [];
        }

        $args = [];
        $parts = str_getcsv($rawArgs, ',', '"');

        foreach ($parts as $part) {
            $part = trim($part);

            // Quoted string
            if (preg_match('/^(["\'])(.*)\\1$/', $part, $m)) {
                $args[] = $m[2];

                continue;
            }

            // Numeric
            if (is_numeric($part)) {
                $args[] = str_contains($part, '.') ? (float) $part : (int) $part;

                continue;
            }

            // Boolean
            if (in_array(strtolower($part), ['true', 'false'], true)) {
                $args[] = strtolower($part) === 'true';

                continue;
            }

            // Null
            if (strtolower($part) === 'null') {
                $args[] = null;

                continue;
            }

            // Variable reference (resolve from data)
            $resolved = data_get($data, $part);
            $args[] = $resolved ?? $part;
        }

        return $args;
    }
}
