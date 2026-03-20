<?php

declare(strict_types=1);

use Atlasphp\Atlas\Console\MakeToolCommand;
use Illuminate\Filesystem\Filesystem;

it('derives snake_case tool names', function (string $className, string $expected) {
    $command = new MakeToolCommand(new Filesystem);

    $reflection = new ReflectionMethod($command, 'deriveToolName');

    expect($reflection->invoke($command, $className))->toBe($expected);
})->with([
    ['LookupOrderTool', 'lookup_order'],
    ['SearchKnowledgeBaseTool', 'search_knowledge_base'],
    ['ProcessRefundTool', 'process_refund'],
    ['SendNotificationTool', 'send_notification'],
    ['CreateTool', 'create'],
    ['SearchTool', 'search'],
    // Without Tool suffix
    ['LookupOrder', 'lookup_order'],
    ['ProcessRefund', 'process_refund'],
    // Single word
    ['Search', 'search'],
]);
