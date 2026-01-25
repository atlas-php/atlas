<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Feature;

use Atlasphp\Atlas\Contracts\PipelineContract;
use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\Tests\TestCase;

/**
 * Tests pipeline behavior when globally disabled via config.
 *
 * IMPORTANT: This test class sets config BEFORE boot via defineEnvironment().
 * This is the correct way to test with disabled pipelines.
 */
class DisabledPipelinesTest extends TestCase
{
    /**
     * Set config BEFORE the service provider boots.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Disable pipelines BEFORE boot - this is the key!
        $app['config']->set('atlas.pipelines.enabled', false);
    }

    public function test_all_core_pipelines_are_inactive_when_config_disabled(): void
    {
        $registry = app(PipelineRegistry::class);

        // All agent pipelines should be inactive
        $this->assertFalse($registry->active('agent.before_execute'));
        $this->assertFalse($registry->active('agent.after_execute'));
        $this->assertFalse($registry->active('agent.context.validate'));
        $this->assertFalse($registry->active('agent.tools.merged'));
        $this->assertFalse($registry->active('agent.stream.after'));
        $this->assertFalse($registry->active('agent.system_prompt.before_build'));
        $this->assertFalse($registry->active('agent.system_prompt.after_build'));
        $this->assertFalse($registry->active('agent.on_error'));

        // All tool pipelines should be inactive
        $this->assertFalse($registry->active('tool.before_resolve'));
        $this->assertFalse($registry->active('tool.after_resolve'));
        $this->assertFalse($registry->active('tool.before_execute'));
        $this->assertFalse($registry->active('tool.after_execute'));
        $this->assertFalse($registry->active('tool.on_error'));

        // Prism pipelines should be inactive
        $this->assertFalse($registry->active('text.before_text'));
        $this->assertFalse($registry->active('text.after_text'));
        $this->assertFalse($registry->active('structured.before_structured'));
        $this->assertFalse($registry->active('structured.after_structured'));
    }

    public function test_handlers_do_not_run_when_pipelines_globally_disabled(): void
    {
        $registry = app(PipelineRegistry::class);
        $runner = app(PipelineRunner::class);

        // Register a test handler
        $handlerCalled = false;
        $handler = new class($handlerCalled) implements PipelineContract
        {
            public function __construct(private bool &$called) {}

            public function handle(mixed $data, \Closure $next): mixed
            {
                $this->called = true;

                return $next($data);
            }
        };

        $registry->register('agent.before_execute', $handler);

        // Run the pipeline via runIfActive
        $runner->runIfActive('agent.before_execute', ['test' => 'data']);

        // Handler should NOT be called because pipelines are globally disabled
        $this->assertFalse($handlerCalled);
    }

    public function test_destination_still_called_when_pipelines_disabled(): void
    {
        $runner = app(PipelineRunner::class);

        $destinationCalled = false;
        $result = $runner->runIfActive('agent.before_execute', ['data' => 'value'], function ($data) use (&$destinationCalled) {
            $destinationCalled = true;

            return $data;
        });

        // Destination should still be called
        $this->assertTrue($destinationCalled);
        $this->assertEquals(['data' => 'value'], $result);
    }

    public function test_pipelines_can_be_selectively_re_enabled(): void
    {
        $registry = app(PipelineRegistry::class);
        $runner = app(PipelineRunner::class);

        // All pipelines start disabled
        $this->assertFalse($registry->active('agent.before_execute'));

        // Re-enable a specific pipeline
        $registry->setActive('agent.before_execute', true);
        $this->assertTrue($registry->active('agent.before_execute'));

        // Register a handler
        $handlerCalled = false;
        $handler = new class($handlerCalled) implements PipelineContract
        {
            public function __construct(private bool &$called) {}

            public function handle(mixed $data, \Closure $next): mixed
            {
                $this->called = true;

                return $next($data);
            }
        };

        $registry->register('agent.before_execute', $handler);

        // Now the handler should run
        $runner->runIfActive('agent.before_execute', ['test' => 'data']);
        $this->assertTrue($handlerCalled);
    }

    public function test_config_value_is_correctly_read_as_false(): void
    {
        // Verify the config was actually set to false
        $this->assertFalse(config('atlas.pipelines.enabled'));
    }
}
