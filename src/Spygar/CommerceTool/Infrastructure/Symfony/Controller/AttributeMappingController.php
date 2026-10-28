<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Infrastructure\Symfony\Controller;

use Akeneo\Tool\Component\StorageUtils\Exception\DuplicateObjectException;
use Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Spygar\CommerceTool\Application\AttributeMappingForm;
use Spygar\CommerceTool\Domain\Model\AttributeMapping;
use Spygar\CommerceTool\Infrastructure\Persistence\AttributeMappingRepository;
use Spygar\CommerceTool\Infrastructure\Symfony\Normalizer\AttributeMappingNormalizer;
use Spygar\CommerceTool\Infrastructure\Symfony\Normalizer\ViolationNormalizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AttributeMappingController
{
    public function __construct(
        private SecurityFacade $security,
        private AttributeMappingRepository $repository,
        private SimpleFactoryInterface $factory,
        private ObjectUpdaterInterface $updater,
        private SaverInterface $saver,
        private RemoverInterface $remover,
        private ValidatorInterface $validator,
        private AttributeMappingNormalizer $normalizer,
        private ViolationNormalizer $violationNormalizer,
        private AttributeMappingForm $form,
    ) {
    }

    public function formAction(Request $request): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $this->denyUnlessGranted('spygar_commercetool_attribute_mapping_edit');
            if (!$request->isXmlHttpRequest()) {
                return new JsonResponse(['message' => 'An error occurred.'], Response::HTTP_BAD_REQUEST);
            }

            $errors = $this->form->save($this->payload($request));
            if ([] !== $errors) {
                return new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST);
            }

            return new JsonResponse($this->form->read());
        }

        $this->denyUnlessGranted('spygar_commercetool_attribute_mapping_index');

        return new JsonResponse($this->form->read());
    }

    public function getAction(string $identifier): JsonResponse
    {
        $this->denyUnlessGranted('spygar_commercetool_attribute_mapping_edit');

        return new JsonResponse($this->normalizer->normalize($this->findOr404($identifier)));
    }

    public function createAction(Request $request): Response
    {
        $this->denyUnlessGranted('spygar_commercetool_attribute_mapping_create');
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'An error occurred.'], Response::HTTP_BAD_REQUEST);
        }

        $mapping = $this->factory->create();
        $this->updater->update($mapping, $this->payload($request));

        $violations = $this->validator->validate($mapping, null, ['creation']);
        if (0 < $violations->count()) {
            return new JsonResponse(['values' => $this->violations($violations)], Response::HTTP_BAD_REQUEST);
        }

        return $this->save($mapping, true);
    }

    public function postAction(Request $request, string $identifier): Response
    {
        $this->denyUnlessGranted('spygar_commercetool_attribute_mapping_edit');
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'An error occurred.'], Response::HTTP_BAD_REQUEST);
        }

        $mapping = $this->findOr404($identifier);
        $this->updater->update($mapping, $this->payload($request));

        $violations = $this->validator->validate($mapping);
        if (0 < $violations->count()) {
            return new JsonResponse($this->violations($violations), Response::HTTP_BAD_REQUEST);
        }

        return $this->save($mapping, false);
    }

    public function removeAction(Request $request, string $identifier): Response
    {
        $this->denyUnlessGranted('spygar_commercetool_attribute_mapping_remove');
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'An error occurred.'], Response::HTTP_BAD_REQUEST);
        }

        $this->remover->remove($this->findOr404($identifier));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function save(AttributeMapping $mapping, bool $wrappedViolations): Response
    {
        try {
            $this->saver->save($mapping);
        } catch (DuplicateObjectException) {
            $error = [
                'path' => 'code',
                'attribute' => 'code',
                'message' => 'This code is already used.',
                'global' => false,
            ];

            return new JsonResponse($wrappedViolations ? ['values' => [$error]] : [$error], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->normalizer->normalize($mapping));
    }

    private function findOr404(string $identifier): AttributeMapping
    {
        $mapping = $this->repository->findOneByIdentifier($identifier);
        if (!$mapping instanceof AttributeMapping) {
            throw new NotFoundHttpException(sprintf('Attribute mapping with code "%s" was not found.', $identifier));
        }

        return $mapping;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @return list<array{path: string, attribute: string, message: string, global: bool}>
     */
    private function violations(iterable $violations): array
    {
        $normalized = [];
        foreach ($violations as $violation) {
            $normalized[] = $this->violationNormalizer->normalize($violation);
        }

        return $normalized;
    }

    private function denyUnlessGranted(string $acl): void
    {
        if (!$this->security->isGranted($acl)) {
            throw new AccessDeniedException();
        }
    }
}
