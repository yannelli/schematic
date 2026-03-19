<?php

namespace Yannelli\Schematic\Contracts;

interface Renderable
{
    /**
     * Render the template/section content with the given data.
     */
    public function render(array $data = []): string;

    /**
     * Render using example data for preview purposes.
     */
    public function preview(): string;
}
