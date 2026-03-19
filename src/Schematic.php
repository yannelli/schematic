<?php

namespace Yannelli\Schematic;

use Closure;
use Yannelli\Schematic\Models\Section;
use Yannelli\Schematic\Models\Template;

class Schematic
{
    public function __construct(
        protected Compiler $compiler,
    ) {}

    // ---------------------------------------------------------------
    // Template CRUD
    // ---------------------------------------------------------------

    /**
     * Create a new template.
     */
    public function create(string $slug, string $name, ?string $description = null, array $metadata = []): Template
    {
        $modelClass = config('schematic.models.template', Template::class);

        return $modelClass::create([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);
    }

    /**
     * Find a template by slug.
     */
    public function find(string $slug): ?Template
    {
        $modelClass = config('schematic.models.template', Template::class);

        return $modelClass::findBySlug($slug);
    }

    /**
     * Find a template by slug or throw.
     */
    public function findOrFail(string $slug): Template
    {
        $modelClass = config('schematic.models.template', Template::class);

        return $modelClass::findBySlugOrFail($slug);
    }

    // ---------------------------------------------------------------
    // Macro Registration (proxy to Compiler)
    // ---------------------------------------------------------------

    /**
     * Register a custom macro.
     *
     * Schematic::macro('component', fn ($name) => Component::render($name));
     */
    public function macro(string $name, Closure $handler): static
    {
        $this->compiler->macro($name, $handler);

        return $this;
    }

    /**
     * Check if a macro exists.
     */
    public function hasMacro(string $name): bool
    {
        return $this->compiler->hasMacro($name);
    }

    // ---------------------------------------------------------------
    // Schema Shortcuts
    // ---------------------------------------------------------------

    /**
     * Generate JSON Schema for a template by slug.
     */
    public function schema(string $slug): array
    {
        return $this->findOrFail($slug)->toJsonSchema();
    }

    /**
     * Generate JSON Schema for a specific section within a template.
     */
    public function sectionSchema(string $templateSlug, string $sectionSlug): ?array
    {
        return $this->findOrFail($templateSlug)->sectionSchema($sectionSlug);
    }

    /**
     * Generate full JSON Schema document (with $schema header) for a template.
     */
    public function schemaDocument(string $slug): array
    {
        return $this->findOrFail($slug)->toJsonSchemaDocument();
    }

    // ---------------------------------------------------------------
    // Render Shortcuts
    // ---------------------------------------------------------------

    /**
     * Render a template by slug with data.
     */
    public function render(string $slug, array $data = []): string
    {
        return $this->findOrFail($slug)->render($data);
    }

    /**
     * Preview a template using its example data.
     */
    public function preview(string $slug): string
    {
        return $this->findOrFail($slug)->preview();
    }

    /**
     * Get the underlying compiler instance.
     */
    public function compiler(): Compiler
    {
        return $this->compiler;
    }
}
