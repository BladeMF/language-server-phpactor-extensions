<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests;

use PHPUnit\Framework\TestCase;
use Phpactor\Container\Container;
use Phpactor\Container\PhpactorContainer;
use Phpactor\Extension\ClassToFile\ClassToFileExtension;
use Phpactor\Extension\CodeTransform\CodeTransformExtension;
use Phpactor\Extension\ComposerAutoloader\ComposerAutoloaderExtension;
use Phpactor\Extension\LanguageServerBridge\LanguageServerBridgeExtension;
use Phpactor\Extension\LanguageServerCodeTransform\LanguageServerCodeTransformExtension;
use Phpactor\Extension\LanguageServerIndexer\LanguageServerIndexerExtension;
use Phpactor\Extension\LanguageServerWorseReflection\LanguageServerWorseReflectionExtension;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\Logger\LoggingExtension;
use Phpactor\Extension\Php\PhpExtension;
use Phpactor\Extension\WorseReflection\WorseReflectionExtension;
use Phpactor\FilePathResolverExtension\FilePathResolverExtension;
use Phpactor\Indexer\Extension\IndexerExtension;
use Phpactor\LanguageServer\LanguageServerBuilder;
use Phpactor\LanguageServer\Test\ServerTester;
use Phpactor\TestUtils\Workspace;

class IntegrationTestCase extends TestCase
{
    protected function workspace(): Workspace
    {
        return Workspace::create(__DIR__ . '/Workspace');
    }

    protected function createTester(): ServerTester
    {
        $container = $this->container();
        
        $builder = $container->get(LanguageServerExtension::SERVICE_LANGUAGE_SERVER_BUILDER);
        $this->assertInstanceOf(LanguageServerBuilder::class, $builder);

        return $builder->buildServerTester();
    }

    public function container(): Container
    {
        $container = PhpactorContainer::fromExtensions([
            LoggingExtension::class,
            LanguageServerExtension::class,
            FilePathResolverExtension::class,
            ClassToFileExtension::class,
            ComposerAutoloaderExtension::class,
            CodeTransformExtension::class,
            LanguageServerCodeTransformExtension::class,
            WorseReflectionExtension::class,
            IndexerExtension::class,
            LanguageServerIndexerExtension::class,
            LanguageServerWorseReflectionExtension::class,
            PhpExtension::class,
            LanguageServerBridgeExtension::class,
            TestLanguageServerSessionExtension::class,
        ], [
            FilePathResolverExtension::PARAM_APPLICATION_ROOT => __DIR__ .'/../../',
            CodeTransformExtension::PARAM_TEMPLATE_PATHS => [],
        ]);
        return $container;
    }
}
