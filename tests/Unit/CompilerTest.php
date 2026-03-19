<?php

use Yannelli\Schematic\Compiler;

beforeEach(function () {
    $this->compiler = new Compiler();
});

// ---------------------------------------------------------------
// Variable Substitution
// ---------------------------------------------------------------

it('compiles simple variables', function () {
    $result = $this->compiler->compile('Hello {{ name }}!', ['name' => 'World']);

    expect($result)->toBe('Hello World!');
});

it('compiles multiple variables', function () {
    $result = $this->compiler->compile(
        '{{ first }} {{ last }}',
        ['first' => 'John', 'last' => 'Doe']
    );

    expect($result)->toBe('John Doe');
});

it('compiles dot-notation variables', function () {
    $result = $this->compiler->compile(
        '{{ patient.name }}',
        ['patient' => ['name' => 'Jane']]
    );

    expect($result)->toBe('Jane');
});

it('leaves unresolved variables as-is', function () {
    $result = $this->compiler->compile('Hello {{ missing }}!', []);

    expect($result)->toBe('Hello {{ missing }}!');
});

it('json encodes array values in variables', function () {
    $result = $this->compiler->compile('{{ data }}', ['data' => ['a', 'b']]);

    expect($result)->toBe('["a","b"]');
});

it('casts numeric values to string', function () {
    $result = $this->compiler->compile('Count: {{ count }}', ['count' => 42]);

    expect($result)->toBe('Count: 42');
});

// ---------------------------------------------------------------
// Conditionals
// ---------------------------------------------------------------

it('renders truthy @if blocks', function () {
    $result = $this->compiler->compile(
        '@if(show)Visible@endif',
        ['show' => true]
    );

    expect($result)->toBe('Visible');
});

it('hides falsy @if blocks', function () {
    $result = $this->compiler->compile(
        '@if(show)Visible@endif',
        ['show' => false]
    );

    expect($result)->toBe('');
});

it('renders @if/@else blocks correctly when truthy', function () {
    $result = $this->compiler->compile(
        '@if(logged_in)Welcome back@elsePlease log in@endif',
        ['logged_in' => true]
    );

    expect($result)->toBe('Welcome back');
});

it('renders @if/@else blocks correctly when falsy', function () {
    $result = $this->compiler->compile(
        '@if(logged_in)Welcome back@elsePlease log in@endif',
        ['logged_in' => false]
    );

    expect($result)->toBe('Please log in');
});

it('treats missing condition variable as falsy', function () {
    $result = $this->compiler->compile(
        '@if(missing)Content@endif',
        []
    );

    expect($result)->toBe('');
});

// ---------------------------------------------------------------
// Loops
// ---------------------------------------------------------------

it('compiles foreach loops', function () {
    $result = $this->compiler->compile(
        '@foreach(items as item)- {{ item }}@endforeach',
        ['items' => ['Apple', 'Banana', 'Cherry']]
    );

    expect($result)->toContain('- Apple')
        ->toContain('- Banana')
        ->toContain('- Cherry');
});

it('provides loop_index in foreach', function () {
    $result = $this->compiler->compile(
        '@foreach(items as item){{ loop_index }}: {{ item }}@endforeach',
        ['items' => ['a', 'b']]
    );

    expect($result)->toContain('0: a')
        ->toContain('1: b');
});

it('returns empty string for non-iterable collection', function () {
    $result = $this->compiler->compile(
        '@foreach(items as item){{ item }}@endforeach',
        ['items' => 'not-iterable']
    );

    expect($result)->toBe('');
});

it('handles nested data in foreach', function () {
    $result = $this->compiler->compile(
        '@foreach(users as user){{ user.name }}@endforeach',
        ['users' => [['name' => 'Alice'], ['name' => 'Bob']]]
    );

    expect($result)->toContain('Alice')
        ->toContain('Bob');
});

// ---------------------------------------------------------------
// Macros
// ---------------------------------------------------------------

it('registers and invokes macros', function () {
    $this->compiler->macro('shout', fn (string $text) => strtoupper($text));

    $result = $this->compiler->compile('@shout("hello")', []);

    expect($result)->toBe('HELLO');
});

it('passes multiple arguments to macros', function () {
    $this->compiler->macro('concat', fn (string $a, string $b) => $a . $b);

    $result = $this->compiler->compile('@concat("foo", "bar")', []);

    expect($result)->toBe('foobar');
});

it('leaves unregistered macros as-is', function () {
    $result = $this->compiler->compile('@unknown("arg")', []);

    expect($result)->toBe('@unknown("arg")');
});

it('does not treat reserved directives as macros', function () {
    $this->compiler->macro('if', fn () => 'NOPE');

    $result = $this->compiler->compile(
        '@if(show)Content@endif',
        ['show' => true]
    );

    expect($result)->toBe('Content');
});

it('checks macro existence', function () {
    expect($this->compiler->hasMacro('test'))->toBeFalse();

    $this->compiler->macro('test', fn () => 'ok');

    expect($this->compiler->hasMacro('test'))->toBeTrue();
});

it('removes macros', function () {
    $this->compiler->macro('temp', fn () => 'ok');
    $this->compiler->removeMacro('temp');

    expect($this->compiler->hasMacro('temp'))->toBeFalse();
});

it('lists registered macros', function () {
    $this->compiler->macro('a', fn () => '');
    $this->compiler->macro('b', fn () => '');

    expect($this->compiler->registeredMacros())->toBe(['a', 'b']);
});

// ---------------------------------------------------------------
// Argument Parsing
// ---------------------------------------------------------------

it('resolves numeric macro arguments', function () {
    $this->compiler->macro('add', fn (int $a, int $b) => $a + $b);

    $result = $this->compiler->compile('@add(2, 3)', []);

    expect($result)->toBe('5');
});

it('resolves boolean macro arguments', function () {
    $this->compiler->macro('check', fn (bool $v) => $v ? 'yes' : 'no');

    expect($this->compiler->compile('@check(true)', []))->toBe('yes');
    expect($this->compiler->compile('@check(false)', []))->toBe('no');
});

it('resolves null macro arguments', function () {
    $this->compiler->macro('isnull', fn ($v) => $v === null ? 'null' : 'not');

    expect($this->compiler->compile('@isnull(null)', []))->toBe('null');
});

it('resolves variable references in macro arguments', function () {
    $this->compiler->macro('echo', fn ($v) => (string) $v);

    $result = $this->compiler->compile('@echo(name)', ['name' => 'Ryan']);

    expect($result)->toBe('Ryan');
});

it('handles macros with no arguments', function () {
    $this->compiler->macro('now', fn () => '2026-01-01');

    $result = $this->compiler->compile('@now()', []);

    expect($result)->toBe('2026-01-01');
});

// ---------------------------------------------------------------
// Combined Features
// ---------------------------------------------------------------

it('combines variables and conditionals', function () {
    $template = '@if(name)Hello {{ name }}!@elseHello stranger!@endif';

    expect($this->compiler->compile($template, ['name' => 'Ryan']))->toBe('Hello Ryan!');
    expect($this->compiler->compile($template, ['name' => '']))->toBe('Hello stranger!');
});

it('trims output', function () {
    $result = $this->compiler->compile('  hello  ', []);

    expect($result)->toBe('hello');
});
