<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Infrastructure\Symfony\Controller;

use Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Spygar\CommerceTool\Domain\Model\Settings;
use Spygar\CommerceTool\Infrastructure\Persistence\SettingsRepository;
use Spygar\CommerceTool\Infrastructure\Symfony\Normalizer\SettingsNormalizer;
use Spygar\CommerceTool\Infrastructure\Symfony\Normalizer\ViolationNormalizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SettingsController
{
    public function __construct(
        private SecurityFacade $security,
        private SettingsRepository $repository,
        private SimpleFactoryInterface $factory,
        private ObjectUpdaterInterface $updater,
        private SaverInterface $saver,
        private ValidatorInterface $validator,
        private SettingsNormalizer $normalizer,
        private ViolationNormalizer $violationNormalizer,
    ) {
    }

    public function getAction(string $identifier): JsonResponse
    {
        $this->denyUnlessGranted('spygar_commercetool_settings_index');
        $this->assertIdentifier($identifier);

        $settings = $this->repository->findOneByIdentifier(Settings::CODE) ?? $this->factory->create();

        return new JsonResponse($this->normalizer->normalize($settings));
    }

    public function postAction(Request $request, string $identifier): Response
    {
        $this->denyUnlessGranted('spygar_commercetool_settings_edit');
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'An error occurred.'], Response::HTTP_BAD_REQUEST);
        }
        $this->assertIdentifier($identifier);

        $settings = $this->repository->findOneByIdentifier(Settings::CODE) ?? $this->factory->create();
        $this->updater->update($settings, $this->payload($request));

        $violations = $this->validator->validate($settings);
        if (0 < $violations->count()) {
            $normalized = [];
            foreach ($violations as $violation) {
                $normalized[] = $this->violationNormalizer->normalize($violation);
            }

            return new JsonResponse($normalized, Response::HTTP_BAD_REQUEST);
        }

        $this->saver->save($settings);

        return new JsonResponse($this->normalizer->normalize($settings));
    }

    private function assertIdentifier(string $identifier): void
    {
        if (Settings::CODE !== $identifier) {
            throw new NotFoundHttpException(sprintf('Settings "%s" were not found.', $identifier));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    private function denyUnlessGranted(string $acl): void
    {
        if (!$this->security->isGranted($acl)) {
            throw new AccessDeniedException();
        }
    }
}
