<?php

namespace Phpactor\Extension\LanguageServerIndexer\Tests\Unit\Watcher;

use PHPUnit\Framework\TestCase;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\Extension\LanguageServerIndexer\Watcher\LanguageServerWatcher;
use Phpactor\LanguageServerProtocol\ClientCapabilities;
use Phpactor\LanguageServerProtocol\DidChangeConfigurationClientCapabilities;
use Phpactor\LanguageServerProtocol\DidChangeWatchedFilesParams;
use Phpactor\LanguageServerProtocol\FileChangeType;
use Phpactor\LanguageServerProtocol\FileEvent;
use Phpactor\LanguageServer\Handler\Workspace\DidChangeWatchedFilesHandler;
use Phpactor\LanguageServer\LanguageServerTesterBuilder;
use function Amp\Promise\wait;

class LanguageServerWatcherTest extends TestCase
{
    public function testSupported(): void
    {
        $capabiltiies = ClientCapabilities::fromArray([
            'workspace' => [
                'didChangeWatchedFiles' => new DidChangeConfigurationClientCapabilities(true),
            ]
        ]);
        $watcher = new LanguageServerWatcher($capabiltiies);

        self::assertTrue(wait($watcher->isSupported()));
    }

    public function testNotSupported(): void
    {
        $capabiltiies = ClientCapabilities::fromArray([
            'workspace' => [
                'didChangeWatchedFiles' => null
            ]
        ]);
        $watcher = new LanguageServerWatcher($capabiltiies);

        self::assertFalse(wait($watcher->isSupported()));
    }

    public function testWatch(): void
    {
        $watcher = new LanguageServerWatcher(new ClientCapabilities());
        $server = LanguageServerTesterBuilder::create()
            ->addListenerProvider($watcher)
            ->enableFileEvents()
            ->build();

        $server->notify(DidChangeWatchedFilesHandler::METHOD, new DidChangeWatchedFilesParams([
            new FileEvent('file:///foobar', FileChangeType::CREATED)
        ]));

        $event = wait($watcher->wait());
        self::assertInstanceOf(ModifiedFile::class, $event);
    }
}
