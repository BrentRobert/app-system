<?php declare(strict_types=1);

namespace Swag\SaasConnect\Core\Content\App\Lifecycle;

use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\SaasConnect\Core\Content\App\Manifest\Manifest;

class AppLifecycle implements AppLifecycleInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var ActionButtonPersister
     */
    private $actionButtonPersister;

    /**
     * @var PermissionPersister
     */
    private $permissionPersister;

    public function __construct(
        EntityRepositoryInterface $appRepository,
        ActionButtonPersister $actionButtonPersister,
        PermissionPersister $permissionPersister
    ) {
        $this->appRepository = $appRepository;
        $this->actionButtonPersister = $actionButtonPersister;
        $this->permissionPersister = $permissionPersister;
    }

    public function install(Manifest $manifest, Context $context): void
    {
        $metadata = $manifest->getMetadata()->toArray();
        $appId = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $metadata = $this->enrichInstallMetadata($manifest, $metadata, $roleId);

        $this->updateApp($manifest, $metadata, $appId, $roleId, $context);
    }

    /**
     * @param array<string, string> $app
     */
    public function update(Manifest $manifest, array $app, Context $context): void
    {
        $metadata = $manifest->getMetadata()->toArray();
        $this->updateApp($manifest, $metadata, $app['id'], $app['roleId'], $context);
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     *
     * @param array<string, string> $app
     */
    public function delete(string $appName, array $app, Context $context): void
    {
        $this->appRepository->delete([['id' => $app['id']]], $context);
    }

    /**
     * @param array<string, string|array<string, string|bool>> $metadata
     */
    private function updateApp(Manifest $manifest, array $metadata, string $id, string $roleId, Context $context): void
    {
        $metadata['path'] = $manifest->getPath();
        $metadata['id'] = $id;

        $this->updateMetadata($metadata, $context);
        $this->actionButtonPersister->updateActions($manifest, $id, $context);
        $this->permissionPersister->updatePrivileges($manifest->getPermissions(), $roleId);
    }

    /**
     * @param array<string, string|array<string, string|bool>> $metadata
     */
    private function updateMetadata(array $metadata, Context $context): void
    {
        // ToDo handle import and saving of icons
        unset($metadata['icon']);

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($metadata): void {
            $this->appRepository->upsert([$metadata], $context);
        });
    }

    /**
     * @param  array<string, string|array<string, string>> $metadata
     * @return array<string, string|array<string, string|bool>>
     */
    private function enrichInstallMetadata(Manifest $manifest, array $metadata, string $roleId): array
    {
        $secret = AccessKeyHelper::generateSecretAccessKey();

        $metadata['integration'] = [
            'label' => $manifest->getMetadata()->getName(),
            'writeAccess' => true,
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => $secret,
        ];
        $metadata['aclRole'] = [
            'id' => $roleId,
            'name' => $manifest->getMetadata()->getName(),
        ];
        $metadata['accessToken'] = $secret;

        return $metadata;
    }
}