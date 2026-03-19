<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Converts typed Atlas messages into a provider's format.
 */
interface MessageFactory
{
    /**
     * @return array<string, mixed>
     */
    public function system(SystemMessage $message): array;

    /**
     * @return array<string, mixed>
     */
    public function user(UserMessage $message, MediaResolver $media): array;

    /**
     * @return array<string, mixed>
     */
    public function assistant(AssistantMessage $message): array;

    /**
     * @return array<string, mixed>
     */
    public function toolResult(ToolResultMessage $message): array;

    /**
     * @return array<string, mixed>
     */
    public function buildAll(TextRequest $request, MediaResolver $media): array;
}
