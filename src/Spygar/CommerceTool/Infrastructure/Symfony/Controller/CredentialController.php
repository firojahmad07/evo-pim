<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Infrastructure\Symfony\Controller;

use Akeneo\Tool\Component\StorageUtils\Exception\DuplicateObjectException;
use Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Spygar\CommerceTool\Application\CommerceToolClient;
use Spygar\CommerceTool\Application\CommerceToolConnectionException;
use Spygar\CommerceTool\Application\CredentialCatalogStore;
use Spygar\CommerceTool\Domain\Model\Credential;
use Spygar\CommerceTool\Infrastructure\Persistence\CredentialRepository;
use Spygar\CommerceTool\Infrastructure\Symfony\Normalizer\CredentialNormalizer;
use Spygar\CommerceTool\Infrastructure\Symfony\Normalizer\ViolationNormalizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CredentialController
{
    public function __construct(
        private SecurityFacade $security,
        private CredentialRepository $repository,
        private SimpleFactoryInterface $factory,
        private ObjectUpdaterInterface $updater,
        private SaverInterface $saver,
        private RemoverInterface $remover,
        private ValidatorInterface $validator,
        private CredentialNormalizer $normalizer,
        private ViolationNormalizer $violationNormalizer,
        private CommerceToolClient $client,
        private CredentialCatalogStore $catalog,
    ) {
    }

    public function optionsAction(): JsonResponse
    {
        if (!$this->security->isGranted('spygar_commercetool_credential_index')
            && !$this->security->isGranted('pim_importexport_export_profile_edit')
            && !$this->security->isGranted('pim_importexport_import_profile_edit')
            && !$this->security->isGranted('pim_importexport_export_profile_show')
            && !$this->security->isGranted('pim_importexport_import_profile_show')
        ) {
            throw new AccessDeniedException();
        }

        $options = [];
        foreach ($this->repository->findBy([], ['label' => 'ASC']) as $credential) {
            $options[] = [
                'code' => $credential->getCode(),
                'label' => $credential->getLabel(),
                'active' => $credential->isActive(),
            ];
        }

        return new JsonResponse($options);
    }

    public function getAction(string $identifier): JsonResponse
    {
        $this->denyUnlessGranted('spygar_commercetool_credential_edit');

        return new JsonResponse($this->present($this->findOr404($identifier)));
    }

    public function createAction(Request $request): Response
    {
        $this->denyUnlessGranted('spygar_commercetool_credential_create');
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'An error occurred.'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->payload($request);
        $credential = $this->factory->create();
        $this->updater->update($credential, $payload);
        $codeError = $this->assignCode($credential);
        if (null !== $codeError) {
            return new JsonResponse(['values' => [[
                'path' => 'label',
                'attribute' => 'label',
                'message' => $codeError,
                'global' => false,
            ]]], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->validator->validate($credential, null, ['creation']);
        if (0 < $violations->count()) {
            return new JsonResponse(['values' => $this->violations($violations)], Response::HTTP_BAD_REQUEST);
        }

        try {
            $fetched = $this->client->fetch($credential);
        } catch (CommerceToolConnectionException $exception) {
            return $this->connectionError($exception, true);
        }

        return $this->save($credential, true, $fetched, $payload);
    }

    public function postAction(Request $request, string $identifier): Response
    {
        $this->denyUnlessGranted('spygar_commercetool_credential_edit');
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'An error occurred.'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->payload($request);
        $credential = $this->findOr404($identifier);
        $connectionChanged = $this->connectionChanged($credential, $payload);
        $this->updater->update($credential, $payload);

        $violations = $this->validator->validate($credential);
        if (0 < $violations->count()) {
            return new JsonResponse($this->violations($violations), Response::HTTP_BAD_REQUEST);
        }

        $fetched = null;
        if ($connectionChanged) {
            try {
                $fetched = $this->client->fetch($credential);
            } catch (CommerceToolConnectionException $exception) {
                return $this->connectionError($exception, false);
            }
        }

        return $this->save($credential, false, $fetched, $payload);
    }

    public function refreshAction(Request $request, string $identifier): Response
    {
        $this->denyUnlessGranted('spygar_commercetool_credential_edit');
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'An error occurred.'], Response::HTTP_BAD_REQUEST);
        }

        $credential = $this->findOr404($identifier);
        try {
            $fetched = $this->client->fetch($credential);
        } catch (CommerceToolConnectionException $exception) {
            return $this->connectionError($exception, false);
        }

        $this->catalog->saveFetched($credential->getCode(), $fetched);

        return new JsonResponse($this->present($credential));
    }

    public function toggleAction(Request $request, string $identifier): Response
    {
        $this->denyUnlessGranted('spygar_commercetool_credential_edit');
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['successful' => false, 'message' => 'An error occurred.'], Response::HTTP_BAD_REQUEST);
        }

        $credential = $this->findOr404($identifier);
        $credential->setActive(!$credential->isActive());
        $credential->touch();
        $this->saver->save($credential);

        return new JsonResponse([
            'successful' => true,
            'message' => $credential->isActive() ? 'Credential enabled.' : 'Credential disabled.',
        ]);
    }

    public function removeAction(Request $request, string $identifier): Response
    {
        $this->denyUnlessGranted('spygar_commercetool_credential_remove');
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'An error occurred.'], Response::HTTP_BAD_REQUEST);
        }

        $credential = $this->findOr404($identifier);
        $code = $credential->getCode();
        $this->remover->remove($credential);
        $this->catalog->delete($code);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array{
     *     locales: list<string>,
     *     currencies: list<string>,
     *     channels: list<array{id: string, key: string, label: string, roles: list<string>}>
     * }|null $fetched
     * @param array<string, mixed> $payload
     */
    private function save(Credential $credential, bool $wrappedViolations, ?array $fetched, array $payload): Response
    {
        try {
            $this->saver->save($credential);
        } catch (DuplicateObjectException) {
            $error = [
                'path' => 'code',
                'attribute' => 'code',
                'message' => 'This code is already used.',
                'global' => false,
            ];

            return new JsonResponse($wrappedViolations ? ['values' => [$error]] : [$error], Response::HTTP_BAD_REQUEST);
        }

        if (null !== $fetched) {
            $this->catalog->saveFetched($credential->getCode(), $fetched);
        }
        $this->storeMappings($credential->getCode(), $payload);

        return new JsonResponse($this->present($credential));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function storeMappings(string $code, array $payload): void
    {
        $locales = \array_key_exists('locale_mapping', $payload) ? $payload['locale_mapping'] : null;
        $currencies = \array_key_exists('currency_mapping', $payload) ? $payload['currency_mapping'] : null;
        $channels = \array_key_exists('channel_mapping', $payload) ? $payload['channel_mapping'] : null;
        $this->catalog->saveMappings(
            $code,
            \is_array($locales) ? $locales : null,
            \is_array($currencies) ? $currencies : null,
            \is_array($channels) ? $channels : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Credential $credential): array
    {
        return array_merge(
            $this->normalizer->normalize($credential),
            $this->catalog->present($credential->getCode()),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function connectionChanged(Credential $credential, array $payload): bool
    {
        $secret = trim((string) ($payload['client_secret'] ?? ''));
        if ('' !== $secret && !hash_equals($credential->getClientSecret(), $secret)) {
            return true;
        }

        $current = [
            'project_key' => $credential->getProjectKey(),
            'client_id' => $credential->getClientId(),
            'region' => strtolower((string) $credential->getRegion()),
            'auth_url' => (string) $credential->getAuthUrl(),
            'api_url' => (string) $credential->getApiUrl(),
            'scope' => (string) $credential->getScope(),
        ];

        foreach ($current as $field => $value) {
            if (!\array_key_exists($field, $payload)) {
                continue;
            }
            $next = trim((string) $payload[$field]);
            if ('region' === $field) {
                $next = strtolower($next);
            }
            if ($next !== $value) {
                return true;
            }
        }

        return false;
    }

    private function assignCode(Credential $credential): ?string
    {
        if ('' !== $credential->getCode()) {
            return null;
        }

        $name = trim($credential->getLabel());
        if ('' === $name) {
            return null;
        }

        $base = strtolower($name);
        $base = preg_replace('/[^a-z0-9]+/', '_', $base) ?? '';
        $base = trim($base, '_');
        if ('' === $base) {
            return 'The name needs at least one letter or number.';
        }

        $base = rtrim(substr($base, 0, 100), '_');
        $code = $base;
        $suffix = 2;
        while ($this->repository->findOneByIdentifier($code) instanceof Credential) {
            $extra = '_'.$suffix;
            $trimmed = rtrim(substr($base, 0, 100 - strlen($extra)), '_');
            $code = ('' === $trimmed ? 'credential' : $trimmed).$extra;
            ++$suffix;
            if ($suffix > 100) {
                return 'A credential with this name already exists.';
            }
        }

        $credential->setCode($code);

        return null;
    }

    private function findOr404(string $identifier): Credential
    {
        $credential = $this->repository->findOneByIdentifier($identifier);
        if (!$credential instanceof Credential) {
            throw new NotFoundHttpException(sprintf('Credential with code "%s" was not found.', $identifier));
        }

        return $credential;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) ? $data : [];
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

    private function connectionError(CommerceToolConnectionException $exception, bool $wrapped): JsonResponse
    {
        $error = [
            'path' => $exception->field(),
            'attribute' => $exception->field(),
            'message' => $exception->getMessage(),
            'global' => false,
        ];

        return new JsonResponse($wrapped ? ['values' => [$error]] : [$error], Response::HTTP_BAD_REQUEST);
    }

    private function denyUnlessGranted(string $acl): void
    {
        if (!$this->security->isGranted($acl)) {
            throw new AccessDeniedException();
        }
    }
}
