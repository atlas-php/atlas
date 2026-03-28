<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Atlasphp\Atlas\Middleware\MiddlewareResolver;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Displays all registered Atlas middleware grouped by execution layer.
 *
 * Shows which middleware classes are active at each layer (agent, step, tool,
 * provider, voice HTTP), including modality targeting for provider middleware.
 */
#[AsCommand(name: 'atlas:middleware')]
class MiddlewareCommand extends Command
{
    /** @var string */
    protected $signature = 'atlas:middleware';

    /** @var string */
    protected $description = 'List all registered Atlas middleware by layer';

    public function handle(MiddlewareResolver $resolver): int
    {
        $all = $resolver->all();

        $this->components->info('Atlas Middleware');
        $this->newLine();

        $layers = [
            'agent' => 'Agent',
            'step' => 'Step',
            'tool' => 'Tool',
            'provider' => 'Provider',
            'voice_http' => 'Voice HTTP',
        ];

        foreach ($layers as $key => $label) {
            $entries = $all[$key] ?? [];

            if ($entries === []) {
                $this->components->twoColumnDetail("<fg=yellow>{$label}</>", '<fg=gray>(none)</>');

                continue;
            }

            $this->components->twoColumnDetail("<fg=yellow>{$label}</>");

            foreach ($entries as $entry) {
                $class = $this->shortName($entry['class']);
                $modalities = $entry['modalities'];

                $suffix = match (true) {
                    $modalities === [] => '',
                    $modalities === ['*'] => ' <fg=gray>→ all modalities</>',
                    default => ' <fg=gray>→ '.implode(', ', $modalities).'</>',
                };

                $this->components->twoColumnDetail("  {$class}{$suffix}");
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Get the short class name without namespace.
     */
    private function shortName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }
}
