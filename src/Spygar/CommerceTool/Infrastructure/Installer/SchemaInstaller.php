<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Infrastructure\Installer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Oro\Bundle\TranslationBundle\Controller\Controller as JsTranslationController;
use Spygar\CommerceTool\Domain\Model\AttributeMapping;
use Spygar\CommerceTool\Domain\Model\Credential;
use Spygar\CommerceTool\Domain\Model\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Creates the connector tables on first use when the migration has not been executed yet.
 */
final class SchemaInstaller implements EventSubscriberInterface
{
    private static bool $translationsReady = false;

    private static bool $schemaReady = false;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private JsTranslationController $jsTranslationController,
        private string $projectDir,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 32],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!self::$translationsReady) {
            try {
                $this->refreshJsTranslations($event->getRequest()->getLocale());
            } catch (\Throwable) {
                // A failed catalog refresh must not block the rest of the PIM.
            }
            self::$translationsReady = true;
        }

        if (self::$schemaReady || !str_contains($event->getRequest()->getPathInfo(), 'commercetool')) {
            return;
        }

        $this->ensure();
        self::$schemaReady = true;
    }

    private function refreshJsTranslations(string $locale): void
    {
        $path = $this->projectDir.'/public/js/translation/'.$locale.'.js';
        if (is_file($path) && str_contains((string) file_get_contents($path), 'jsmessages:batch_jobs.commercetool_attribute_export.label')) {
            return;
        }

        $content = $this->jsTranslationController->renderJsTranslationContent(
            ['measures', 'jsmessages', 'validators'],
            $locale,
            false
        );
        file_put_contents($path, $content);
    }

    private function ensure(): void
    {
        $connection = $this->entityManager->getConnection();
        $tables = [
            'spygar_commercetool_credential',
            'spygar_commercetool_attribute_mapping',
            'spygar_commercetool_settings',
        ];
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tablesExist($tables)) {
            $tool = new SchemaTool($this->entityManager);
            $metadata = array_map(
                fn (string $class) => $this->entityManager->getClassMetadata($class),
                [Credential::class, AttributeMapping::class, Settings::class]
            );

            foreach ($tool->getUpdateSchemaSql($metadata, true) as $sql) {
                if (str_contains($sql, 'spygar_commercetool')) {
                    $connection->executeStatement($sql);
                }
            }
        }

        $this->ensureAttributeMappingColumns($connection, $schemaManager);
    }

    private function ensureAttributeMappingColumns(Connection $connection, AbstractSchemaManager $schemaManager): void
    {
        if (!$schemaManager->tablesExist(['spygar_commercetool_attribute_mapping'])) {
            return;
        }

        $present = [];
        foreach ($schemaManager->listTableColumns('spygar_commercetool_attribute_mapping') as $column) {
            $present[strtolower($column->getName())] = true;
        }

        if (!isset($present['variant_akeneo_attribute'])) {
            $connection->executeStatement('ALTER TABLE spygar_commercetool_attribute_mapping ADD variant_akeneo_attribute VARCHAR(255) DEFAULT NULL');
        }
        if (!isset($present['mapping_type'])) {
            $connection->executeStatement("ALTER TABLE spygar_commercetool_attribute_mapping ADD mapping_type VARCHAR(20) NOT NULL DEFAULT 'field'");
        }
        if (!isset($present['position'])) {
            $connection->executeStatement('ALTER TABLE spygar_commercetool_attribute_mapping ADD position INT NOT NULL DEFAULT 0');
        }
    }
}
