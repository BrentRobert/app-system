<?php declare(strict_types=1);

namespace Swag\SaasConnect\Test\Core\Content\App\Action;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\SaasConnect\Core\Content\App\Action\AppAction;
use Swag\SaasConnect\Core\Content\App\Action\Executor;
use Swag\SaasConnect\Test\AppSystemTestBehaviour;

class ExecutorTest extends \PHPUnit\Framework\TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    const SCHEMA_LOCATION = __DIR__ . '/../../../../../appActionEndpointSchema.json';

    /**
     * @var MockHandler
     */
    private $appServerMock;

    /**
     * @var Executor
     */
    private $executor;

    public function setUp(): void
    {
        $this->appServerMock = $this->getContainer()->get(MockHandler::class);
        $this->executor = $this->getContainer()->get(Executor::class);
    }

    public function testExecutorUsesCorrectSchema(): void
    {
        $action = new AppAction(
            'https://test.com/my-action',
            getenv('APP_URL'),
            '1.0.0',
            'product',
            'detail',
            [Uuid::randomHex()],
            'I am  not secret',
            'I am secret'
        );

        $this->appServerMock->append(new Response(200));
        $this->executor->execute($action);

        /** @var Request $request */
        $request = $this->appServerMock->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $result = $this->validateRequestSchema($body);

        $message = $this->parseSchemaErrors($result);

        static::assertTrue($result->isValid(), $message);
    }

    public function testExecutorIgnoresFailedRequests(): void
    {
        $action = new AppAction(
            'https://brokenServer.com',
            getenv('APP_URL'),
            '1.0.0',
            'product',
            'detail',
            [],
            'I am  not secret',
            'I am secret'
        );

        $this->appServerMock->append(new Response(500));
        $this->executor->execute($action);

        /** @var Request $request */
        $request = $this->appServerMock->getLastRequest();

        static::assertNotNull($request);
    }

    public function testTargetUrlIsCorrect(): void
    {
        $targetUrl = 'https://my-server.com';
        $action = new AppAction(
            $targetUrl,
            getenv('APP_URL'),
            '1.0.0',
            'product',
            'detail',
            [],
            'I am  not secret',
            'I am secret'
        );

        $this->appServerMock->append(new Response(200));
        $this->executor->execute($action);

        /** @var Request $request */
        $request = $this->appServerMock->getLastRequest();

        static::assertEquals($targetUrl, (string) $request->getUri());
    }

    public function testContentIsCorrect(): void
    {
        $targetUrl = 'https://test.com/my-action';
        $shopUrl = getenv('APP_URL');
        $appVersion = '1.0.0';
        $entity = 'product';
        $actionName = 'detail';
        $affectedIds = [Uuid::randomHex(), Uuid::randomHex()];
        $accessKey = 'i am not secret';
        $secretKey = 'i am secret';
        $action = new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $actionName,
            $affectedIds,
            $accessKey,
            $secretKey
        );

        $this->appServerMock->append(new Response(200));
        $this->executor->execute($action);

        /** @var Request $request */
        $request = $this->appServerMock->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);
        $data = json_decode($body, true);

        $expectedSource = [
            'url' => $shopUrl,
            'appVersion' => $appVersion,
            'apiKey' => $accessKey,
            'secretKey' => $secretKey,
        ];
        $expectedData = [
            'ids' => $affectedIds,
            'action' => $actionName,
            'entity' => $entity,
        ];

        static::assertEquals($expectedSource, $data['source']);
        static::assertEquals($expectedData, $data['data']);
        static::assertNotEmpty($data['meta']['timestamp']);
        static::assertTrue(Uuid::isValid($data['meta']['reference']));
    }

    private function parseSchemaErrors(ValidationResult $result): string
    {
        $message = '';

        foreach ($result->getErrors() as $validationError) {
            $message .= sprintf("Validation error at '%s' : %s \n", implode(',', $validationError->dataPointer()), $validationError->keyword());
            $message .= json_encode($validationError->keywordArgs(), JSON_PRETTY_PRINT);
        }

        return $message;
    }

    private function validateRequestSchema(string $body): ValidationResult
    {
        $requestData = \json_decode($body);
        $schema = Schema::fromJsonString(file_get_contents(self::SCHEMA_LOCATION));
        $validator = new Validator();

        return $validator->schemaValidation($requestData, $schema);
    }
}