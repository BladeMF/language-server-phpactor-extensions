<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Amp\Delayed;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\ConstElement;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Node\Statement\InterfaceDeclaration;
use Microsoft\PhpParser\Node\UseVariableName;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\Token;
use Phpactor\CodeTransform\Domain\Refactor\RenameVariable;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentEdit;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\LanguageServerProtocol\VersionedTextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentBuilder;

class Renamer
{
    /**
     * @var Parser
     */
    private $parser;
    /**
     * @var DefinitionLocator
     */
    private $definitionLocator;
    /**
     * @var ReferenceFinder
     */
    private $finder;
    /**
     * @var ClientApi
     */
    private $clientApi;
    /**
     * @var int
     */
    private $timeoutSeconds = 10;
    /**
     * @var Workspace
     */
    private $workspace;
    /**
     * @var RenameVariable
     */
    private $renameVariable;
    /**
     * @var NodeUtils
     */
    private $nodeUtils;

    public function __construct(
        Workspace $workspace,
        Parser $parser,
        ReferenceFinder $finder,
        DefinitionLocator $definitionLocator,
        ClientApi $clientApi,
        RenameVariable $renameVariable,
        NodeUtils $nodeUtils
    ) {
        $this->parser = $parser;
        $this->definitionLocator = $definitionLocator;
        $this->finder = $finder;
        $this->clientApi = $clientApi;
        $this->workspace = $workspace;
        $this->renameVariable = $renameVariable;
        $this->nodeUtils = $nodeUtils;
    }

    public function prepareRename(TextDocumentItem $textDocument, Position $position): ?Range
    {
        [$offset, $node] = $this->documentAndPositionToNodeAndOffset($textDocument, $position);
        
        if ($this->canRenameNode($node)) {
            return $this->nodeUtils->getNodeNameRange($node);
        }

        return null;
    }

    public function rename(TextDocumentItem $textDocument, Position $position, string $newName): ?WorkspaceEdit
    {
        [$offset, $node] = $this->documentAndPositionToNodeAndOffset($textDocument, $position);
        
        $phpactorDocument = TextDocumentBuilder::create($textDocument->text)
            ->uri($textDocument->uri)
            ->language($textDocument->languageId ?? 'php')
            ->build();

        if ($this->canRenameNode($node))
            return $this->renameNode($phpactorDocument, $offset, $node, $this->nodeUtils->getNodeNameText($node, $textDocument->text), $newName);
        
        return null;
    }
    
    private function renameNode(TextDocument $phpactorDocument, ByteOffset $offset, Node $node, string $oldName, string $newName): ?WorkspaceEdit
    {
        if (empty($oldName)) {
            return null;
        }
        $locations = [];
        try {
            $potentialLocation = $this->definitionLocator->locateDefinition($phpactorDocument, $offset);
            $locations[] = new Location($potentialLocation->uri(), $potentialLocation->offset());
        } catch (CouldNotLocateDefinition $notFound) {
            // ignore the missing definition
        }
        
        $start = microtime(true);
        $count = 0;
        foreach ($this->finder->findReferences($phpactorDocument, $offset) as $potentialLocation) {
            if (!$potentialLocation->isSurely()) {
                continue;
            }
            
            $locations[] = $potentialLocation->location();

            if ($count++ % 100 === 0 && $count > 0) {
                $this->clientApi->window()->showMessage()->info(sprintf(
                    '... scanned %s references confirmed %s ...',
                    $count - 1,
                    count($locations)
                ));
            }

            if (microtime(true) - $start > $this->timeoutSeconds) {
                $this->clientApi->window()->showMessage()->info(sprintf(
                    'Reference find stopped, %s/%s references confirmed but took too long (%s/%s seconds).',
                    count($locations),
                    $count,
                    number_format(microtime(true) - $start, 2),
                    $this->timeoutSeconds
                ));
                return $this->locationsToWorkspaceEdit($locations, $oldName, $newName);
            }

            // if ($count++ % 10) {
            //     // give other co-routines a chance
            //     yield new Delayed(0);
            // }
        }
        
        $this->clientApi->window()->showMessage()->info(sprintf(
            'Found %s reference(s) to be renamed.',
            count($locations)
        ));

        return $this->locationsToWorkspaceEdit($locations, $oldName, $newName);
    }

    private function documentAndPositionToNodeAndOffset(TextDocumentItem $textDocument, Position $position): array
    {
        $offset = PositionConverter::positionToByteOffset($position, $textDocument->text);
        
        $rootNode = $this->parser->parseSourceFile($textDocument->text);
        $node = $rootNode->getDescendantNodeAtPosition($offset->toInt());
        return [$offset, $node];
    }

    private function locationsToWorkspaceEdit(array $locations, string $oldName, string $newName): WorkspaceEdit
    {
        // group locations by uri
        $locationsByUri = [];
        foreach ($locations as $location) {
            /** @var Location $location */
            $uri = (string)$location->uri();
            if (!isset($locationsByUri[$uri])) {
                $locationsByUri[$uri] = [];
            }
            $locationsByUri[$uri][] = $location;
        }

        $documentEdits = [];
        foreach ($locationsByUri as $uri => $locations) {
            $documentEdits[] = new TextDocumentEdit(
                new VersionedTextDocumentIdentifier($uri, $this->getDocumentVersion($uri)),
                $this->documentLocationsToTextEdits($uri, $locations, $oldName, $newName)
            );
        }

        return new WorkspaceEdit(null, $documentEdits);
    }

    private function documentLocationsToTextEdits(string $documentUri, array $locations, string $oldName, string $newName): array
    {
        $edits = [];
        $documentContent = $this->getDocumentText($documentUri);
        $rootNode = $this->parser->parseSourceFile($documentContent);
        foreach ($locations as $location) {
            /** @var Location $location */
            $node = $rootNode->getDescendantNodeAtPosition($location->offset()->toInt());
            $nodeNameText = $this->nodeUtils->getNodeNameText($node, $documentContent);
            if ($nodeNameText !== $oldName) {
                continue;
            }
            
            $position = $this->nodeUtils->getNodeNameStartPosition($node, $oldName);
            
            if ($position !== null) {
                $edits[] = new TextEdit(
                    new Range(
                        $position,
                        new Position($position->line, $position->character + mb_strlen($oldName))
                    ),
                    $newName
                );
            }
        }
        return $edits;
    }

    private function getDocumentVersion(string $uri): int
    {
        return $this->workspace->has($uri) ? $this->workspace->get($uri)->version : 0;
    }

    private function getDocumentText(string $uri): string
    {
        if ($this->workspace->has($uri)) {
            return $this->workspace->get($uri)->text;
        }

        $contents = @file_get_contents($uri);

        if (false === $contents) {
            return "";
        }

        return $contents;
    }

    private function canRenameNode(Node $node): bool
    {
        return
            $node instanceof MethodDeclaration 
            || $node instanceof ClassDeclaration 
            || $node instanceof QualifiedName 
            || $node instanceof InterfaceDeclaration 
            || $node instanceof ConstElement 
            || ($node instanceof ScopedPropertyAccessExpression && $node->memberName instanceof Token)
            || ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class)) 
            || ($node instanceof MemberAccessExpression && $node->memberName instanceof Token) 
            || ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class) === null)
            || $node instanceof Parameter
            ;
    }
}
