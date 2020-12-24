<?php

namespace Phpactor\Extension\LanguageServerRename;

use Microsoft\PhpParser\Parser;
use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerRename\Handler\RenameHandler;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\MapResolver\Resolver;

class LanguageServerRenameExtension implements Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container)
    {
        $container->register(RenameHandler::class, function (Container $container) {
            return new RenameHandler(
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                new Parser()
                // $container->get(ReferenceFinderExtension::SERVICE_DEFINITION_LOCATOR),
                // $container->get(LocationConverter::class)
            );
        }, [ LanguageServerExtension::TAG_METHOD_HANDLER => [] ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema)
    {
    }
}
