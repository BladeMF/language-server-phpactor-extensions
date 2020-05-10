<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder;

use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerReferenceFinder\Handler\GotoDefinitionHandler;
use Phpactor\Extension\LanguageServerReferenceFinder\Handler\GotoImplementationHandler;
use Phpactor\Extension\LanguageServerReferenceFinder\Handler\ReferencesHandler;
use Phpactor\Extension\LanguageServerReferenceFinder\Handler\TypeDefinitionHandler;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\ReferenceFinder\ReferenceFinderExtension;
use Phpactor\MapResolver\Resolver;
use Phpactor\ReferenceFinder\ReferenceFinder;

class LanguageServerReferenceFinderExtension implements Extension
{
    const PARAM_REFERENCE_TIMEOUT = 'language_server_reference_reference_finder.reference_timeout';

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container)
    {
        $container->register(GotoDefinitionHandler::class, function (Container $container) {
            return new GotoDefinitionHandler(
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get(ReferenceFinderExtension::SERVICE_DEFINITION_LOCATOR)
            );
        }, [ LanguageServerExtension::TAG_SESSION_HANDLER => [] ]);

        $container->register(TypeDefinitionHandler::class, function (Container $container) {
            return new TypeDefinitionHandler(
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get(ReferenceFinderExtension::SERVICE_TYPE_LOCATOR)
            );
        }, [ LanguageServerExtension::TAG_SESSION_HANDLER => [] ]);

        $container->register(ReferencesHandler::class, function (Container $container) {
            return new ReferencesHandler(
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get(ReferenceFinder::class),
                $container->get(ReferenceFinderExtension::SERVICE_DEFINITION_LOCATOR),
                $container->getParameter(self::PARAM_REFERENCE_TIMEOUT)
            );
        }, [ LanguageServerExtension::TAG_SESSION_HANDLER => [] ]);

        $container->register(GotoImplementationHandler::class, function (Container $container) {
            return new GotoImplementationHandler(
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get(ReferenceFinderExtension::SERVICE_IMPLEMENTATION_FINDER)
            );
        }, [ LanguageServerExtension::TAG_SESSION_HANDLER => [] ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema)
    {
        $schema->setDefaults([
            self::PARAM_REFERENCE_TIMEOUT => 10
        ]);
    }
}
