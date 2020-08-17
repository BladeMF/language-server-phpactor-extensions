<?php

namespace Phpactor\Extension\LanguageServerCodeTransform;

use Microsoft\PhpParser\Parser;
use Phpactor\CodeTransform\Domain\Helper\UnresolvableClassNameFinder;
use Phpactor\CodeTransform\Domain\Refactor\ImportName;
use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServerCodeTransform\CodeAction\ImportClassProvider;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportNameCommand;
use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImportCandidateProvider;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Indexer\Model\SearchClient;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\MapResolver\Resolver;

class LanguageServerCodeTransformExtension implements Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container)
    {
        $this->registerCommands($container);
        $this->registerCodeActions($container);
        $this->registerModel($container);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema)
    {
    }

    private function registerCommands(ContainerBuilder $container): void
    {
        $container->register(ImportNameCommand::class, function (Container $container) {
            return new ImportNameCommand(
                $container->get(ImportName::class),
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get(TextEditConverter::class),
                $container->get(ClientApi::class)
            );
        }, [
            LanguageServerExtension::TAG_COMMAND => [
                'name' => ImportNameCommand::NAME
            ],
        ]);
    }

    private function registerCodeActions(ContainerBuilder $container): void
    {
        $container->register(ImportClassProvider::class, function (Container $container) {
            return new ImportClassProvider(
                $container->get(UnresolvableClassNameFinder::class)
            );
        }, [
            LanguageServerExtension::TAG_CODE_ACTION_PROVIDER => []
        ]);
    }

    private function registerModel(ContainerBuilder $container): void
    {
        $container->register(NameImportCandidateProvider::class, function (Container $container) {
            return new NameImportCandidateProvider(
                $container->get(SearchClient::class),
                new Parser()
            );
        });
    }
}
