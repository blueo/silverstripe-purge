<?php

namespace Blueo\Purge\Task;

use Blueo\Purge\Service\PurgeCapability;
use Blueo\Purge\Service\PurgeDispatcher;
use Blueo\Purge\Service\PurgeResult;
use Blueo\Purge\Service\PurgeService;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Purges by hand.
 *
 * Use it after a deployment that changed a template, after a config change
 * that changed every page, or to find out what the bound provider can do.
 *
 *     sake tasks:blueo-purge --capabilities
 *     sake tasks:blueo-purge --url=/about-us/ --url=/news/
 *     sake tasks:blueo-purge --all
 *     sake tasks:blueo-purge --tag=news
 *
 * The purge is sent in this process. Add --queue to put it on the queue
 * instead, which matches what a publish does.
 */
class PurgeTask extends BuildTask
{
    protected static string $commandName = 'blueo-purge';

    protected string $title = 'Purge: purge a CDN by URL, by tag, or entirely';

    protected static string $description = 'Sends a purge to the bound provider. '
        . 'Run with --capabilities to see what the provider supports.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $service = PurgeService::singleton();

        if ($input->getOption('capabilities')) {
            return $this->reportCapabilities($service, $output);
        }

        $urls = (array) $input->getOption('url');
        $tags = (array) $input->getOption('tag');
        $all = (bool) $input->getOption('all');
        $queue = (bool) $input->getOption('queue');

        if (!$all && !$urls && !$tags) {
            $output->writeln('Give --url, --tag or --all. Run with --capabilities to see what is supported.');

            return Command::INVALID;
        }

        $results = [];

        if ($all) {
            $results['everything'] = $this->send($queue, PurgeCapability::PurgeAll, []);
        }

        if ($urls) {
            $results[sprintf('%d URL(s)', count($urls))] = $this->send($queue, PurgeCapability::PurgeUrls, $urls);
        }

        if ($tags) {
            $results[sprintf('%d tag(s)', count($tags))] = $this->send($queue, PurgeCapability::PurgeTags, $tags);
        }

        $failed = false;

        foreach ($results as $label => $result) {
            if ($result === null) {
                $output->writeln(sprintf('%s: queued.', $label));

                continue;
            }

            $output->writeln(sprintf('%s: %s', $label, (string) $result));
            $failed = $failed || !$result->isSuccessful();
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    private function send(bool $queue, PurgeCapability $capability, array $items): ?PurgeResult
    {
        if ($queue) {
            return match ($capability) {
                PurgeCapability::PurgeUrls => PurgeDispatcher::singleton()->purgeUrls($items),
                PurgeCapability::PurgeAll => PurgeDispatcher::singleton()->purgeAll(),
                PurgeCapability::PurgeTags => PurgeDispatcher::singleton()->purgeTags($items),
            };
        }

        $service = PurgeService::singleton();

        return match ($capability) {
            PurgeCapability::PurgeUrls => $service->purgeUrls($items),
            PurgeCapability::PurgeAll => $service->purgeAll(),
            PurgeCapability::PurgeTags => $service->purgeTags($items),
        };
    }

    private function reportCapabilities(PurgeService $service, PolyOutput $output): int
    {
        $adaptor = $service->getAdaptor();
        $output->writeln(sprintf('Provider: %s', $adaptor ? get_class($adaptor) : 'none bound'));

        foreach (PurgeCapability::cases() as $capability) {
            $output->writeln(sprintf(
                '  %s: %s',
                $capability->getLabel(),
                $service->supports($capability) ? 'yes' : 'no'
            ));
        }

        $output->writeln(sprintf(
            'Queue: %s',
            PurgeDispatcher::singleton()->canQueue() ? 'queuedjobs' : 'inline, queuedjobs is not installed'
        ));

        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption(
                'url',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'A path or URL to purge. Repeat for more than one.'
            ),
            new InputOption(
                'tag',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'A surrogate key to purge. Repeat for more than one. Not every provider supports tags.'
            ),
            new InputOption('all', null, InputOption::VALUE_NONE, 'Purge everything the CDN holds for this site.'),
            new InputOption('queue', null, InputOption::VALUE_NONE, 'Queue the purge rather than send it now.'),
            new InputOption(
                'capabilities',
                null,
                InputOption::VALUE_NONE,
                'Print the bound provider and what it supports, and purge nothing.'
            ),
        ];
    }
}
