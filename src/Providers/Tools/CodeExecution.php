<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * Code execution provider tool for Gemini.
 */
class CodeExecution extends ProviderTool
{
    public function type(): string
    {
        return 'code_execution';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['code_execution' => (object) []];
    }
}
