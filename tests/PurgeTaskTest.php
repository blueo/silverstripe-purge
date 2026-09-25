<?php

namespace Blueo\Purge\Tests;

use Blueo\Purge\Task\PurgeTask;
use SilverStripe\Dev\SapphireTest;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use SilverStripe\PolyExecution\PolyOutput;

class PurgeTaskTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testTheTaskOffersTheOptionsTheReadmeDocuments(): void
    {
        $names = array_map(
            fn (InputOption $option): string => $option->getName(),
            PurgeTask::create()->getOptions()
        );

        $this->assertSame(['url', 'tag', 'all', 'queue', 'capabilities'], $names);
    }

    public function testTheCapabilitiesReportNamesTheProviderAndEveryCapability(): void
    {
        $buffer = new BufferedOutput();
        $output = new PolyOutput(PolyOutput::FORMAT_ANSI, wrappedOutput: $buffer);

        $task = PurgeTask::create();
        $input = new ArrayInput(['--capabilities' => true], new InputDefinition($task->getOptions()));
        $task->run($input, $output);

        $printed = $buffer->fetch();

        $this->assertStringContainsString('NullPurgeAdaptor', $printed);
        $this->assertStringContainsString('Purge by tag: no', $printed);
        $this->assertStringContainsString('queuedjobs is not installed', $printed);
    }
}
