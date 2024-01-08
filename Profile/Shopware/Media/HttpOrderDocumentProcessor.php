<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Shopware\Media;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\Utils;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\CannotGetFileRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\ExceptionRunLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileProcessorInterface;
use SwagMigrationAssistant\Migration\Media\MediaProcessWorkloadStruct;
use SwagMigrationAssistant\Migration\MessageQueue\Handler\ProcessMediaHandler;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\OrderDocumentDataSet;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Api\ShopwareApiGateway;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Connection\ConnectionFactoryInterface;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

#[Package('services-settings')]
class HttpOrderDocumentProcessor extends BaseMediaService implements MediaFileProcessorInterface
{
    public function __construct(
        private readonly EntityRepository $mediaFileRepo,
        private readonly MediaService $mediaService,
        private readonly LoggingServiceInterface $loggingService,
        private readonly ConnectionFactoryInterface $connectionFactory,
        Connection $dbalConnection
    ) {
        parent::__construct($dbalConnection);
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface
            && $migrationContext->getGateway()->getName() === ShopwareApiGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === OrderDocumentDataSet::getEntity();
    }

    /**
     * @param MediaProcessWorkloadStruct[] $workload
     *
     * @throws InconsistentCriteriaIdsException
     *
     * @return MediaProcessWorkloadStruct[]
     */
    public function process(MigrationContextInterface $migrationContext, Context $context, array $workload, int $fileChunkByteSize): array
    {
        // Map workload with uuids as keys
        /** @var MediaProcessWorkloadStruct[] $mappedWorkload */
        $mappedWorkload = [];
        $runId = $migrationContext->getRunUuid();

        foreach ($workload as $work) {
            $mappedWorkload[$work->getMediaId()] = $work;
        }

        // Fetch media from database
        $media = $this->getMediaFiles(\array_keys($mappedWorkload), $migrationContext->getRunUuid());

        // Do download requests and store the promises
        $client = $this->connectionFactory->createApiClient($migrationContext);

        if ($client === null) {
            $this->loggingService->addLogEntry(new ExceptionRunLog(
                $runId,
                DefaultEntities::ORDER_DOCUMENT,
                new \Exception('Http client can not connect to server.')
            ));
            $this->loggingService->saveLogging($context);

            return $workload;
        }

        $promises = $this->doMediaDownloadRequests($media, $mappedWorkload, $client);

        // Wait for the requests to complete, even if some of them fail
        /** @var array $results */
        $results = Utils::settle($promises)->wait();

        // handle responses
        $failureUuids = [];
        $finishedUuids = [];
        foreach ($results as $uuid => $result) {
            $state = $result['state'];
            $additionalData = $mappedWorkload[$uuid]->getAdditionalData();

            $oldWorkloadSearchResult = \array_filter(
                $workload,
                function (MediaProcessWorkloadStruct $work) use ($uuid) {
                    return $work->getMediaId() === $uuid;
                }
            );

            /** @var MediaProcessWorkloadStruct $oldWorkload */
            $oldWorkload = \array_pop($oldWorkloadSearchResult);

            if ($state !== 'fulfilled') {
                $mappedWorkload[$uuid] = $oldWorkload;
                $mappedWorkload[$uuid]->setAdditionalData($additionalData);
                $mappedWorkload[$uuid]->setErrorCount($mappedWorkload[$uuid]->getErrorCount() + 1);

                if ($mappedWorkload[$uuid]->getErrorCount() > ProcessMediaHandler::MEDIA_ERROR_THRESHOLD) {
                    $failureUuids[] = $uuid;
                    $mappedWorkload[$uuid]->setState(MediaProcessWorkloadStruct::ERROR_STATE);
                    $this->loggingService->addLogEntry(new CannotGetFileRunLog(
                        $mappedWorkload[$uuid]->getRunId(),
                        DefaultEntities::ORDER_DOCUMENT,
                        $mappedWorkload[$uuid]->getMediaId(),
                        $mappedWorkload[$uuid]->getAdditionalData()['uri']
                    ));
                }

                continue;
            }

            $response = $result['value'];
            $filePath = \sprintf('_temp/%s.%s', $uuid, 'pdf');

            $streamContext = \stream_context_create([
                'http' => [
                    'follow_location' => 0,
                    'max_redirects' => 0,
                ],
            ]);
            $fileHandle = \fopen($filePath, 'ab', false, $streamContext);

            if (!\is_resource($fileHandle)) {
                throw new \RuntimeException(\sprintf('Could not open file %s in mode %s.', $filePath, 'ab'));
            }

            \fwrite($fileHandle, $response->getBody()->getContents());
            \fclose($fileHandle);

            if ($mappedWorkload[$uuid]->getState() === MediaProcessWorkloadStruct::FINISH_STATE) {
                // move media to media system
                $filename = $this->getMediaName($media, $uuid);
                $this->persistFileToMedia($filePath, $uuid, $filename, $context);
                \unlink($filePath);
                $finishedUuids[] = $uuid;
            }

            if ($oldWorkload->getErrorCount() === $mappedWorkload[$uuid]->getErrorCount()) {
                $mappedWorkload[$uuid]->setErrorCount(0);
            }
        }

        $this->setProcessedFlag($runId, $context, $finishedUuids, $failureUuids);
        $this->loggingService->saveLogging($context);

        return \array_values($mappedWorkload);
    }

    private function persistFileToMedia(string $filePath, string $uuid, string $name, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($filePath, $uuid, $name): void {
            $fileExtension = \pathinfo($filePath, \PATHINFO_EXTENSION);
            $mimeType = \mime_content_type($filePath);
            $streamContext = \stream_context_create([
                'http' => [
                    'follow_location' => 0,
                    'max_redirects' => 0,
                ],
            ]);
            $fileBlob = \file_get_contents($filePath, false, $streamContext);
            $name = \preg_replace('/[^a-zA-Z0-9_-]+/', '-', \mb_strtolower($name));

            try {
                $this->mediaService->saveFile(
                    $fileBlob,
                    $fileExtension,
                    $mimeType,
                    $name,
                    $context,
                    'document',
                    $uuid
                );
            } catch (MediaException $mediaException) {
                if ($mediaException->getErrorCode() === MediaException::MEDIA_DUPLICATED_FILE_NAME) {
                    $this->mediaService->saveFile(
                        $fileBlob,
                        $fileExtension,
                        $mimeType,
                        $name . \mb_substr(Uuid::randomHex(), 0, 5),
                        $context,
                        'document',
                        $uuid
                    );
                } elseif (\in_array($mediaException->getErrorCode(), [MediaException::MEDIA_ILLEGAL_FILE_NAME, MediaException::MEDIA_EMPTY_FILE_NAME], true)) {
                    $this->mediaService->saveFile(
                        $fileBlob,
                        $fileExtension,
                        $mimeType,
                        $uuid,
                        $context,
                        'document',
                        $uuid
                    );
                }
            }
        });
    }

    /**
     * Start all the download requests for the media in parallel (async) and return the promise array.
     *
     * @param MediaProcessWorkloadStruct[] $mappedWorkload
     */
    private function doMediaDownloadRequests(array $media, array &$mappedWorkload, Client $client): array
    {
        $promises = [];
        foreach ($media as $mediaFile) {
            $uuid = \mb_strtolower($mediaFile['media_id']);
            $additionalData = [];
            $additionalData['file_size'] = $mediaFile['file_size'];
            $additionalData['uri'] = $mediaFile['uri'];
            $mappedWorkload[$uuid]->setAdditionalData($additionalData);

            $promise = $this->doDownloadRequest($mappedWorkload[$uuid], $client);

            if ($promise !== null) {
                $promises[$uuid] = $promise;
            }
        }

        return $promises;
    }

    private function doDownloadRequest(MediaProcessWorkloadStruct $workload, Client $client): ?Promise\PromiseInterface
    {
        $additionalData = $workload->getAdditionalData();

        try {
            $promise = $client->getAsync(
                'SwagMigrationOrderDocuments/' . $additionalData['uri']
            );

            $workload->setCurrentOffset((int) $additionalData['file_size']);
            $workload->setState(MediaProcessWorkloadStruct::FINISH_STATE);
        } catch (\Throwable $exception) {
            $promise = null;
            $workload->setErrorCount($workload->getErrorCount() + 1);
        }

        return $promise;
    }

    private function getMediaName(array $media, string $mediaId): string
    {
        foreach ($media as $mediaFile) {
            if ($mediaFile['media_id'] === $mediaId) {
                return $mediaFile['file_name'];
            }
        }

        return '';
    }

    private function setProcessedFlag(string $runId, Context $context, array $finishedUuids, array $failureUuids): void
    {
        $mediaFiles = $this->getMediaFiles($finishedUuids, $runId);
        $updateProcessedMediaFiles = [];
        foreach ($mediaFiles as $data) {
            $updateProcessedMediaFiles[] = [
                'id' => $data['id'],
                'processed' => true,
            ];
        }

        if (!empty($failureUuids)) {
            $mediaFiles = $this->getMediaFiles($failureUuids, $runId);

            foreach ($mediaFiles as $data) {
                $updateProcessedMediaFiles[] = [
                    'id' => $data['id'],
                    'processFailure' => true,
                ];
            }
        }

        if (empty($updateProcessedMediaFiles)) {
            return;
        }

        $this->mediaFileRepo->update($updateProcessedMediaFiles, $context);
    }
}
