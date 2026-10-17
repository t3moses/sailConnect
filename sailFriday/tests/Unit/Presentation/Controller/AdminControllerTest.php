<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Controller;

use App\Application\Exception\ValidationException;
use App\Application\UseCase\Admin\AddToCrewWhitelistUseCase;
use App\Application\UseCase\Admin\GetAllBoatsUseCase;
use App\Application\UseCase\Admin\GetAllCrewsUseCase;
use App\Application\UseCase\Admin\GetAllUsersUseCase;
use App\Application\UseCase\Admin\GetConfigUseCase;
use App\Application\UseCase\Admin\GetCrewBoatHistoryUseCase;
use App\Application\UseCase\Admin\GetMatchingDataUseCase;
use App\Application\UseCase\Admin\GetParticipantEmailsUseCase;
use App\Application\UseCase\Admin\GetUserDetailUseCase;
use App\Application\UseCase\Admin\RemoveFromCrewWhitelistUseCase;
use App\Application\UseCase\Admin\SendCustomNotificationUseCase;
use App\Application\UseCase\Admin\SetUserAdminUseCase;
use App\Application\UseCase\Admin\DeactivateUserUseCase;
use App\Application\UseCase\Admin\NotifyUserUseCase;
use App\Application\UseCase\Admin\UpdateCrewProfileUseCase;
use App\Application\UseCase\Crew\RecordNoShowUseCase;
use App\Application\UseCase\Season\ProcessSeasonUpdateUseCase;
use App\Application\UseCase\Season\UpdateConfigUseCase;
use App\Presentation\Controller\AdminController;
use App\Presentation\Response\JsonResponse;
use PHPUnit\Framework\TestCase;

class AdminControllerTest extends TestCase
{
    private array $adminAuth = ['is_admin' => true, 'user_id' => 1];

    private function makeController(
        UpdateConfigUseCase $updateConfigUseCase,
        ProcessSeasonUpdateUseCase $processSeasonUpdateUseCase,
        ?RecordNoShowUseCase $recordNoShowUseCase = null,
    ): AdminController {
        return new AdminController(
            $this->createStub(GetMatchingDataUseCase::class),
            $this->createStub(GetParticipantEmailsUseCase::class),
            $this->createStub(SendCustomNotificationUseCase::class),
            $this->createStub(GetConfigUseCase::class),
            $updateConfigUseCase,
            $processSeasonUpdateUseCase,
            $this->createStub(GetAllUsersUseCase::class),
            $this->createStub(SetUserAdminUseCase::class),
            $this->createStub(GetUserDetailUseCase::class),
            $this->createStub(GetAllCrewsUseCase::class),
            $this->createStub(GetAllBoatsUseCase::class),
            $this->createStub(GetCrewBoatHistoryUseCase::class),
            $this->createStub(UpdateCrewProfileUseCase::class),
            $this->createStub(AddToCrewWhitelistUseCase::class),
            $this->createStub(RemoveFromCrewWhitelistUseCase::class),
            $recordNoShowUseCase ?? $this->createStub(RecordNoShowUseCase::class),
            $this->createStub(DeactivateUserUseCase::class),
            $this->createStub(NotifyUserUseCase::class),
        );
    }

    private function getResponseData(JsonResponse $response): array
    {
        return (new \ReflectionClass($response))->getProperty('data')->getValue($response);
    }

    private function getResponseStatusCode(JsonResponse $response): int
    {
        return (new \ReflectionClass($response))->getProperty('statusCode')->getValue($response);
    }

    public function testUpdateConfigCallsProcessSeasonUpdateAndIncludesResultInResponse(): void
    {
        $updateConfigUseCase = $this->createMock(UpdateConfigUseCase::class);
        $updateConfigUseCase->method('execute')
            ->willReturn(['success' => true, 'message' => 'Season configuration updated successfully']);

        $processSeasonUpdateUseCase = $this->createMock(ProcessSeasonUpdateUseCase::class);
        $processSeasonUpdateUseCase->expects($this->once())
            ->method('execute')
            ->willReturn(['success' => true, 'events_processed' => 3, 'flotillas_generated' => 3]);

        $controller = $this->makeController($updateConfigUseCase, $processSeasonUpdateUseCase);
        $response   = $controller->updateConfig([], $this->adminAuth);

        $this->assertEquals(200, $this->getResponseStatusCode($response));

        $data = $this->getResponseData($response);
        $this->assertTrue($data['data']['recalculation']['success']);
        $this->assertEquals(3, $data['data']['recalculation']['events_processed']);
        $this->assertEquals(3, $data['data']['recalculation']['flotillas_generated']);
    }

    public function testUpdateConfigReturns200WithRecalculationErrorWhenProcessSeasonUpdateThrows(): void
    {
        $updateConfigUseCase = $this->createMock(UpdateConfigUseCase::class);
        $updateConfigUseCase->method('execute')
            ->willReturn(['success' => true, 'message' => 'Season configuration updated successfully']);

        $processSeasonUpdateUseCase = $this->createMock(ProcessSeasonUpdateUseCase::class);
        $processSeasonUpdateUseCase->method('execute')
            ->willThrowException(new \RuntimeException('Database locked'));

        $controller = $this->makeController($updateConfigUseCase, $processSeasonUpdateUseCase);
        $response   = $controller->updateConfig([], $this->adminAuth);

        // Config was saved — must still be 200
        $this->assertEquals(200, $this->getResponseStatusCode($response));

        $data = $this->getResponseData($response);
        $this->assertTrue($data['success']);
        $this->assertEquals('Database locked', $data['data']['recalculation']['error']);
    }

    public function testUpdateConfigDoesNotCallProcessSeasonUpdateWhenValidationFails(): void
    {
        $updateConfigUseCase = $this->createMock(UpdateConfigUseCase::class);
        $updateConfigUseCase->method('execute')
            ->willThrowException(new ValidationException(['source' => 'Invalid value']));

        $processSeasonUpdateUseCase = $this->createMock(ProcessSeasonUpdateUseCase::class);
        $processSeasonUpdateUseCase->expects($this->never())->method('execute');

        $controller = $this->makeController($updateConfigUseCase, $processSeasonUpdateUseCase);
        $response   = $controller->updateConfig(['source' => 'invalid'], $this->adminAuth);

        $this->assertEquals(400, $this->getResponseStatusCode($response));
    }

    public function testRecordNoShowReturns200WithResultOnSuccess(): void
    {
        $recordNoShowUseCase = $this->createMock(RecordNoShowUseCase::class);
        $recordNoShowUseCase->expects($this->once())
            ->method('execute')
            ->with('crew_1', 'Fri Apr 17')
            ->willReturn([
                'crew_key' => 'crew_1',
                'display_name' => 'Test Crew',
                'no_show_count' => 1,
                'rank_commitment' => 1,
                'active' => true,
                'withdrawn_from_future_events' => true,
            ]);

        $controller = $this->makeController(
            $this->createStub(UpdateConfigUseCase::class),
            $this->createStub(ProcessSeasonUpdateUseCase::class),
            $recordNoShowUseCase,
        );
        $response = $controller->recordNoShow(['crewKey' => 'crew_1'], ['event_id' => 'Fri Apr 17'], $this->adminAuth);

        $this->assertEquals(200, $this->getResponseStatusCode($response));
        $data = $this->getResponseData($response);
        $this->assertEquals(1, $data['data']['rank_commitment']);
    }

    public function testRecordNoShowReturns400WhenEventIdMissing(): void
    {
        $recordNoShowUseCase = $this->createMock(RecordNoShowUseCase::class);
        $recordNoShowUseCase->expects($this->never())->method('execute');

        $controller = $this->makeController(
            $this->createStub(UpdateConfigUseCase::class),
            $this->createStub(ProcessSeasonUpdateUseCase::class),
            $recordNoShowUseCase,
        );
        $response = $controller->recordNoShow(['crewKey' => 'crew_1'], [], $this->adminAuth);

        $this->assertEquals(400, $this->getResponseStatusCode($response));
    }

    public function testRecordNoShowReturns403WhenNotAdmin(): void
    {
        $recordNoShowUseCase = $this->createMock(RecordNoShowUseCase::class);
        $recordNoShowUseCase->expects($this->never())->method('execute');

        $controller = $this->makeController(
            $this->createStub(UpdateConfigUseCase::class),
            $this->createStub(ProcessSeasonUpdateUseCase::class),
            $recordNoShowUseCase,
        );
        $response = $controller->recordNoShow(['crewKey' => 'crew_1'], ['event_id' => 'Fri Apr 17'], ['is_admin' => false, 'user_id' => 1]);

        $this->assertEquals(403, $this->getResponseStatusCode($response));
    }
}
