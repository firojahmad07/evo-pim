<?php

declare(strict_types=1);

namespace Specification\Akeneo\Platform\Bundle\CatalogVolumeMonitoringBundle\Persistence\Query\Sql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Result;
use PhpSpec\ObjectBehavior;
use Akeneo\Platform\Bundle\CatalogVolumeMonitoringBundle\Persistence\Query\Sql\CountCategories;
use Akeneo\Platform\Component\CatalogVolumeMonitoring\Volume\Query\CountQuery;
use Akeneo\Platform\Component\CatalogVolumeMonitoring\Volume\ReadModel\CountVolume;
use Prophecy\Argument;

class CountCategoriesSpec extends ObjectBehavior
{
    function let(Connection $connection)
    {
        $this->beConstructedWith($connection, 12);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(CountCategories::class);
    }

    function it_is_a_count_query()
    {
        $this->shouldImplement(CountQuery::class);
    }

    function it_gets_count_volume(Connection $connection, \Doctrine\DBAL\Driver\Result $driverResult)
    {
        $driverResult->fetchAssociative()->willReturn(['count' => '4']);
        $result = new Result($driverResult->getWrappedObject(), $connection->getWrappedObject());

        $connection->executeQuery(Argument::type('string'))->willReturn($result);
        $this->fetch()->shouldBeLike(new CountVolume(4, 'count_categories'));
    }
}
