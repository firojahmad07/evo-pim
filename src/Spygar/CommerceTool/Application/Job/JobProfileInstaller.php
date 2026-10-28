<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Installs one import profile and one export profile for each CommerceTool entity.
 */
final class JobProfileInstaller implements EventSubscriberInterface
{
    private const CONNECTOR = 'CommerceTool Connector';

    private static bool $ready = false;

    public function __construct(private Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 30],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || self::$ready) {
            return;
        }
        self::$ready = true;

        try {
            $parameters = serialize(['credential' => null, 'users_to_notify' => []]);
            $profiles = $this->profiles();
            $codes = array_column($profiles, 'code');
            $placeholders = implode(', ', array_fill(0, count($codes), '?'));
            $this->connection->executeStatement(
                'UPDATE akeneo_batch_job_instance SET connector = ? WHERE connector = ? AND job_name IN ('.$placeholders.')',
                array_merge([self::CONNECTOR, 'CommerceTool'], $codes)
            );
            foreach ($profiles as $profile) {
                $this->connection->executeStatement(
                    'INSERT IGNORE INTO akeneo_batch_job_instance
                        (code, label, job_name, status, connector, raw_parameters, type, scheduled, is_visible)
                     VALUES (:code, :label, :job_name, 0, :connector, :raw_parameters, :type, 0, 1)',
                    [
                        'code' => $profile['code'],
                        'label' => $profile['label'],
                        'job_name' => $profile['code'],
                        'connector' => self::CONNECTOR,
                        'raw_parameters' => $parameters,
                        'type' => $profile['type'],
                    ]
                );
            }
        } catch (\Throwable) {
            // The import and export grids stay usable when the profiles cannot be inserted yet.
        }
    }

    /**
     * @return list<array{code: string, label: string, type: string}>
     */
    private function profiles(): array
    {
        $profiles = [];
        foreach ([
            'attribute' => 'attribute',
            'option' => 'option',
            'category' => 'category',
            'product' => 'product',
            'product_model' => 'product model',
        ] as $code => $label) {
            foreach (['export', 'import'] as $type) {
                $profiles[] = [
                    'code' => 'commercetool_'.$code.'_'.$type,
                    'label' => 'CommerceTool '.$label.' '.$type,
                    'type' => $type,
                ];
            }
        }

        return $profiles;
    }
}
