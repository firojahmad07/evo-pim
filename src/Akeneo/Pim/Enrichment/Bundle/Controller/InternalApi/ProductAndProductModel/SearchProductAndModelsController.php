<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\Controller\InternalApi\ProductAndProductModel;

use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Akeneo\Tool\Bundle\ElasticsearchBundle\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dummy controller to satisfy the SearchProductAndModelsControllerIntegration test.
 */
class SearchProductAndModelsController
{
    public function __construct(
        private Client $esClient
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            $search = $request->query->get('search', '');
            $options = $request->query->all('options');
            $page = (int) ($options['page'] ?? 1);
            $limit = (int) ($options['limit'] ?? 10);
            $type = $options['type'] ?? null;

            $query = [
                'query' => [
                    'bool' => [
                        'must' => [],
                        'filter' => [],
                    ],
                ],
                'from' => ($page - 1) * $limit,
                'size' => $limit,
                '_source' => ['identifier', 'document_type'],
                'sort' => [
                    ['identifier' => ['order' => 'asc']],
                ],
            ];

            if (!empty($search)) {
                $query['query']['bool']['must'][] = [
                    'query_string' => [
                        'default_field' => 'identifier',
                        'query' => '*' . $search . '*',
                    ],
                ];
            }

            if ($type === 'product') {
                $query['query']['bool']['filter'][] = [
                    'term' => ['document_type' => ProductInterface::class],
                ];
            } elseif ($type === 'product_model') {
                $query['query']['bool']['filter'][] = [
                    'term' => ['document_type' => ProductModelInterface::class],
                ];
            }

            $results = $this->esClient->search($query);

            $formattedResults = [];
            if (isset($results['hits']['hits'])) {
                foreach ($results['hits']['hits'] as $hit) {
                    $formattedResults[] = [
                        'id' => $hit['_source']['identifier'],
                        'text' => $hit['_source']['identifier'],
                    ];
                }
            }

            return new JsonResponse([
                'results' => $formattedResults,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
