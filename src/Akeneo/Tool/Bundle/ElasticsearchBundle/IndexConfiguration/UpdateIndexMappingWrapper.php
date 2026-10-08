<?php
declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\IndexConfiguration;

use Akeneo\Tool\Bundle\ElasticsearchBundle\Client;

/**
 * The UpdateIndexMapping class needs some private services to work, we cannot use it for a migration for instance.
 * Using this wrapper allows to make only one service public.
 *
 * @author    Nicolas Marniesse <nicolas.marniesse@akeneo.com>
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class UpdateIndexMappingWrapper
{
    /** @var object OpenSearch\Client or ElasticsearchClientAdapter */
    private $nativeClient;
    private Client $client;

    public function __construct($clientBuilder, $hosts, Client $client)
    {
        $hosts = is_string($hosts) ? [$hosts] : $hosts;
        $this->nativeClient = $clientBuilder->setHosts($hosts)->build();
        $this->client = $client;
    }

    public function updateIndexMapping(): void
    {
        $updateIndexMapping = new UpdateIndexMapping();
        $updateIndexMapping->updateIndexMapping(
            $this->nativeClient,
            $this->client->getIndexName(),
            $this->client->getConfigurationLoader()
        );
    }
}
