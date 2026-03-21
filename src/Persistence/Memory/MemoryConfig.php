<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Memory;

/**
 * Class MemoryConfig
 *
 * Value object declaring which memory tools and variables an agent wants.
 * Returned by the HasMemory trait's memory() method.
 */
class MemoryConfig
{
    /** @var array<int, string> Document types to register as VariableRegistry entries */
    protected array $variableDocuments = [];

    protected bool $searchTool = false;

    protected bool $recallTool = false;

    protected bool $rememberTool = false;

    public static function make(): self
    {
        return new self;
    }

    /**
     * Register document memories as variables for instruction interpolation.
     * Uppercase versions of the type names become variable names.
     *
     * @param  array<int, string>  $documentTypes
     */
    public function variables(array $documentTypes): static
    {
        $this->variableDocuments = $documentTypes;

        return $this;
    }

    /**
     * Give the agent a search_memory tool.
     */
    public function withSearch(): static
    {
        $this->searchTool = true;

        return $this;
    }

    /**
     * Give the agent a recall_memory tool.
     */
    public function withRecall(): static
    {
        $this->recallTool = true;

        return $this;
    }

    /**
     * Give the agent a remember_memory tool.
     */
    public function withRemember(): static
    {
        $this->rememberTool = true;

        return $this;
    }

    /**
     * Give the agent all three memory tools.
     */
    public function withTools(): static
    {
        $this->searchTool = true;
        $this->recallTool = true;
        $this->rememberTool = true;

        return $this;
    }

    // ─── Accessors ──────────────────────────────────────────────

    /** @return array<int, string> */
    public function getVariableDocuments(): array
    {
        return $this->variableDocuments;
    }

    public function hasSearchTool(): bool
    {
        return $this->searchTool;
    }

    public function hasRecallTool(): bool
    {
        return $this->recallTool;
    }

    public function hasRememberTool(): bool
    {
        return $this->rememberTool;
    }
}
