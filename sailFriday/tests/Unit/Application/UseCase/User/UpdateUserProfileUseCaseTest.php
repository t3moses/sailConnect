<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase\User;

use App\Application\DTO\Request\UpdateProfileRequest;
use App\Application\DTO\Response\ProfileResponse;
use App\Application\DTO\Response\UserResponse;
use App\Application\Port\Repository\BoatRepositoryInterface;
use App\Application\Port\Repository\CrewRepositoryInterface;
use App\Application\Port\Repository\UserRepositoryInterface;
use App\Application\Port\Service\EmailServiceInterface;
use App\Application\Port\Service\EmailTemplateServiceInterface;
use App\Application\Port\Service\PasswordServiceInterface;
use App\Application\UseCase\User\GetUserProfileUseCase;
use App\Application\UseCase\User\UpdateUserProfileUseCase;
use App\Domain\Entity\Crew;
use App\Domain\Entity\User;
use App\Domain\Enum\SkillLevel;
use App\Domain\ValueObject\CrewKey;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UpdateUserProfileUseCaseTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private CrewRepositoryInterface $crewRepository;
    private BoatRepositoryInterface $boatRepository;
    private PasswordServiceInterface $passwordService;
    private GetUserProfileUseCase $getUserProfileUseCase;
    private EmailServiceInterface $emailService;
    private UpdateUserProfileUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->crewRepository = $this->createMock(CrewRepositoryInterface::class);
        $this->boatRepository = $this->createMock(BoatRepositoryInterface::class);
        $this->passwordService = $this->createMock(PasswordServiceInterface::class);
        $this->getUserProfileUseCase = $this->createMock(GetUserProfileUseCase::class);

        // Stub user lookup required early in execute()
        $stubUser = new User(email: 'test@example.com', passwordHash: 'hash', accountType: 'crew');
        $this->userRepository->method('findById')->willReturn($stubUser);

        // Stub profile fetch called at the end of execute()
        $stubProfile = new ProfileResponse(
            user: new UserResponse(id: 1, email: 'test@example.com', accountType: 'crew', isAdmin: false),
        );
        $this->getUserProfileUseCase->method('execute')->willReturn($stubProfile);

        $this->emailService = $this->createMock(EmailServiceInterface::class);
        $this->emailService->method('sendBulk')
            ->willReturnCallback(fn(array $recipients) => array_fill_keys($recipients, true));

        $this->useCase = new UpdateUserProfileUseCase(
            $this->userRepository,
            $this->crewRepository,
            $this->boatRepository,
            $this->passwordService,
            $this->getUserProfileUseCase,
            $this->emailService,
            $this->createMock(EmailTemplateServiceInterface::class),
            'admin@example.com',
            $this->createMock(LoggerInterface::class),
            'admin-secondary@example.com',
        );
    }

    private function createTestCrew(?string $experience = null): Crew
    {
        $crew = new Crew(
            key: CrewKey::fromString('test-crew'),
            displayName: 'Test Crew',
            firstName: 'Test',
            lastName: 'Crew',
            partnerKey: null,
            mobile: null,
            socialPreference: false,
            membershipNumber: null,
            skill: SkillLevel::INTERMEDIATE,
            experience: $experience,
        );
        $crew->setId(1);
        $crew->setUserId(1);
        return $crew;
    }

    public function testUpdateCrewProfileExperience(): void
    {
        $crew = $this->createTestCrew(null);

        $this->crewRepository->method('findByUserId')->willReturn($crew);

        $capturedCrew = null;
        $this->crewRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Crew $c) use (&$capturedCrew) {
                $capturedCrew = $c;
                return true;
            }));

        $request = new UpdateProfileRequest(
            crewProfile: ['experience' => 'CANSail 1 and 2, 3 seasons at NSC'],
        );

        $this->useCase->execute(1, $request);

        $this->assertNotNull($capturedCrew);
        $this->assertEquals('CANSail 1 and 2, 3 seasons at NSC', $capturedCrew->getExperience());
    }

    public function testClearCrewProfileExperienceWithEmptyString(): void
    {
        $crew = $this->createTestCrew('Some existing experience');

        $this->crewRepository->method('findByUserId')->willReturn($crew);

        $capturedCrew = null;
        $this->crewRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Crew $c) use (&$capturedCrew) {
                $capturedCrew = $c;
                return true;
            }));

        // Frontend sends empty string when the field is cleared
        $request = new UpdateProfileRequest(
            crewProfile: ['experience' => ''],
        );

        $this->useCase->execute(1, $request);

        $this->assertNotNull($capturedCrew);
        $this->assertNull($capturedCrew->getExperience());
    }

    public function testCrewProfileUpdateNotifiesPrimaryAndSecondaryRecipients(): void
    {
        $crew = $this->createTestCrew(null);
        $this->crewRepository->method('findByUserId')->willReturn($crew);

        $capturedRecipients = [];
        $this->emailService->method('sendBulk')
            ->willReturnCallback(function (array $recipients) use (&$capturedRecipients) {
                $capturedRecipients = $recipients;
                return array_fill_keys($recipients, true);
            });

        $request = new UpdateProfileRequest(
            crewProfile: ['experience' => 'CANSail 1 and 2, 3 seasons at NSC'],
        );

        $this->useCase->execute(1, $request);

        $this->assertEqualsCanonicalizing(
            ['admin@example.com', 'admin-secondary@example.com'],
            $capturedRecipients
        );
    }
}
