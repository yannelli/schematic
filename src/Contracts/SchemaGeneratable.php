<?php

namespace Yannelli\Schematic\Contracts;

interface SchemaGeneratable
{
    /**
     * Generate a JSON Schema representation.
     */
    public function toJsonSchema(): array;
}
