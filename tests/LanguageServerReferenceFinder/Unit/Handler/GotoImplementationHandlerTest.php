<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Tests\Unit\Handler;

use Phpactor\LanguageServerProtocol\Location as LspLocation;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServerReferenceFinder\Handler\GotoImplementationHandler;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\LanguageServer\Test\HandlerTester;
use Phpactor\LanguageServer\Test\ProtocolFactory;
use Phpactor\ReferenceFinder\ClassImplementationFinder;
use Phpactor\TestUtils\PHPUnit\TestCase;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\Locations;
use Phpactor\TextDocument\TextDocumentBuilder;

class GotoImplementationHandlerTest extends TestCase
{
    const EXAMPLE_URI = '/test';
    const EXAMPLE_TEXT = 'hello';

    /**
     * @var TextDocumentItem
     */
    private $document;

    /**
     * @var Position
     */
    private $position;

    /**
     * @var TextDocumentIdentifier
     */
    private $identifier;

    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var ObjectProphecy|ClassImplementationFinder
     */
    private $finder;

    protected function setUp(): void
    {
        $this->finder = $this->prophesize(ClassImplementationFinder::class);
        $this->workspace = new Workspace();


        $this->document = ProtocolFactory::textDocumentItem(__FILE__, self::EXAMPLE_TEXT);
        $this->workspace->open($this->document);
        $this->identifier = ProtocolFactory::textDocumentIdentifier(__FILE__);
        $this->position = new Position(0, 0);
    }

    public function testGoesToImplementation()
    {
        $document = TextDocumentBuilder::create(self::EXAMPLE_TEXT)
            ->language('php')
            ->uri(__FILE__)
            ->build()
        ;

        $this->finder->findImplementations(
            $document,
            ByteOffset::fromInt(0)
        )->willReturn(new Locations([
            new Location($document->uri(), ByteOffset::fromInt(2))
        ]));

        $tester = new HandlerTester(new GotoImplementationHandler(
            $this->workspace,
            $this->finder->reveal(),
            new LocationConverter($this->workspace)
        ));

        $response = $tester->dispatchAndWait('textDocument/implementation', [
            'textDocument' => $this->identifier,
            'position' => $this->position,
        ]);
        $locations = $response->result;
        $this->assertIsArray($locations);
        $this->assertCount(1, $locations);
        $lspLocation = reset($locations);
        $this->assertInstanceOf(LspLocation::class, $lspLocation);
    }
}
