<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase\Auth;

use App\Application\DTO\Request\RegisterRequest;
use App\Application\Port\Repository\BoatRepositoryInterface;
use App\Application\Port\Repository\CrewRepositoryInterface;
use App\Application\Port\Repository\EventRepositoryInterface;
use App\Application\Port\Repository\UserRepositoryInterface;
use App\Application\Port\Service\CalendarServiceInterface;
use App\Application\Port\Service\EmailServiceInterface;
use App\Application\Port\Service\EmailTemplateServiceInterface;
use App\Application\Port\Service\TransactionServiceInterface;
use App\Application\UseCase\Auth\RegisterUseCase;
use App\Domain\Entity\Boat;
use App\Domain\Entity\Crew;
use App\Domain\Service\RankingService;
use App\Domain\ValueObject\Rank;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RegisterUseCaseTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private CrewRepositoryInterface $crewRepository;
    private BoatRepositoryInterface $boatRepository;
    private RankingService $rankingService;
    private EmailServiceInterface $emailService;
    private EmailTemplateServiceInterface $emailTemplateService;
    private EventRepositoryInterface $eventRepository;
    private CalendarServiceInterface $calendarService;
    private TransactionServiceInterface $transactionService;
    private array $config;
    private RegisterUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for all dependencies
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->crewRepository = $this->createMock(CrewRepositoryInterface::class);
        $this->boatRepository = $this->createMock(BoatRepositoryInterface::class);
        $this->rankingService = $this->createMock(RankingService::class);
        $this->emailService = $this->createMock(EmailServiceInterface::class);
        $this->emailTemplateService = $this->createMock(EmailTemplateServiceInterface::class);
        $this->eventRepository = $this->createMock(EventRepositoryInterface::class);
        $this->calendarService = $this->createMock(CalendarServiceInterface::class);
        $this->transactionService = $this->createMock(TransactionServiceInterface::class);

        // Mock config array
        $this->config = [
            'email' => [
                'admin_notification_email' => 'test-admin@example.com',
                'registration_notification_email_secondary' => 'test-admin-secondary@example.com',
            ],
        ];

        // Setup common mock behaviors
        $this->userRepository->method('emailExists')->willReturn(false);

        // Default: no display name clashes
        $this->crewRepository->method('displayNameExists')->willReturn(false);
        $this->boatRepository->method('displayNameExists')->willReturn(false);

        // Setup ranking service to return valid ranks
        $this->rankingService->method('calculateCrewRank')->willReturn(
            Rank::forCrew(availability: 0, commitment: 0, membership: 0, absence: 0)
        );
        $this->rankingService->method('calculateBoatRank')->willReturn(
            Rank::forBoat(flexibility: 1, absence: 0)
        );

        // Default: no future events (falls back to plain send)
        $this->eventRepository->method('findFutureEvents')->willReturn([]);
        $this->calendarService->method('generateSeasonCalendar')->willReturn('BEGIN:VCALENDAR...');
        $this->emailService->method('sendWithAttachment')->willReturn(true);

        // Default: Email service returns true (success)
        $this->emailService->method('send')->willReturn(true);
        $this->emailService->method('sendBulk')
            ->willReturnCallback(function ($recipients) {
                return array_fill_keys($recipients, true);
            });

        // Mock email template service to return HTML strings with actual data
        $this->emailTemplateService->method('renderCrewRegistrationNotification')
            ->willReturnCallback(function ($user, $profile) {
                // Return HTML that includes the actual profile data for testing
                return sprintf(
                    '<html><body>Crew member registration: %s %s (%s), Display: %s, Skill: %s, Member: %s, Mobile: %s</body></html>',
                    $profile['firstName'] ?? '',
                    $profile['lastName'] ?? '',
                    $user->getEmail(),
                    $profile['displayName'] ?? '',
                    ($profile['skill'] ?? 0) === 2 ? 'Advanced' : (($profile['skill'] ?? 0) === 1 ? 'Intermediate' : 'Novice'),
                    $profile['membershipNumber'] ?? '',
                    $profile['mobile'] ?? ''
                );
            });
        $this->emailTemplateService->method('renderBoatOwnerRegistrationNotification')
            ->willReturnCallback(function ($user, $profile) {
                // Return HTML that includes the actual profile data for testing
                return sprintf(
                    '<html><body>Boat Owner Registration: %s %s (%s), Boat: %s, Berths: %d-%d, Mobile: %s, Assistance: %s</body></html>',
                    $profile['ownerFirstName'] ?? '',
                    $profile['ownerLastName'] ?? '',
                    $user->getEmail(),
                    $profile['displayName'] ?? '',
                    $profile['minBerths'] ?? 0,
                    $profile['maxBerths'] ?? 0,
                    $profile['ownerMobile'] ?? '',
                    ($profile['assistanceRequired'] ?? false) ? 'Yes' : 'No'
                );
            });

        $this->useCase = new RegisterUseCase(
            $this->userRepository,
            $this->crewRepository,
            $this->boatRepository,
            $this->rankingService,
            $this->emailService,
            $this->emailTemplateService,
            $this->eventRepository,
            $this->calendarService,
            $this->config,
            $this->transactionService,
            new NullLogger()
        );
    }

    /**
     * Build the sailConnect identity the signed-in user would have for this request.
     * Names, mobile number and membership come from the identity, so mirror what the test put in the profile.
     */
    private function identity(string $email, RegisterRequest $request): array
    {
        $p = $request->profile ?? [];

        return [
            'user_id' => 'auth-' . md5($email),
            'email_address' => $email,
            'first_name' => $p['firstName'] ?? $p['ownerFirstName'] ?? 'Test',
            'last_name' => $p['lastName'] ?? $p['ownerLastName'] ?? 'User',
            'is_member' => !empty($p['membershipNumber']),
            'membership_number' => $p['membershipNumber'] ?? null,
            'mobile_number' => $p['mobile'] ?? $p['ownerMobile'] ?? null,
        ];
    }

    public function testCrewDisplayNameGeneratedWhenNull(): void
    {
        // Arrange
        $this->crewRepository->method('findByKey')->willReturn(null);

        $capturedCrew = null;
        $this->crewRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Crew $crew) use (&$capturedCrew) {
                $capturedCrew = $crew;
                return true;
            }));

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: [
                'firstName' => 'John',
                'lastName' => 'Smith',
                // No displayName provided
            ]
        );
        $identity = $this->identity('john.smith@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert
        $this->assertNotNull($capturedCrew);
        $this->assertEquals('JohnS', $capturedCrew->getDisplayName());
    }

    public function testCrewDisplayNameGeneratedWhenEmptyString(): void
    {
        // Arrange
        $this->crewRepository->method('findByKey')->willReturn(null);

        $capturedCrew = null;
        $this->crewRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Crew $crew) use (&$capturedCrew) {
                $capturedCrew = $crew;
                return true;
            }));

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: [
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'displayName' => '',  // Empty string
            ]
        );
        $identity = $this->identity('jane.doe@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert
        $this->assertNotNull($capturedCrew);
        $this->assertEquals('JaneD', $capturedCrew->getDisplayName());
    }

    public function testCrewDisplayNamePreservedWhenProvided(): void
    {
        // Arrange
        $this->crewRepository->method('findByKey')->willReturn(null);

        $capturedCrew = null;
        $this->crewRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Crew $crew) use (&$capturedCrew) {
                $capturedCrew = $crew;
                return true;
            }));

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: [
                'firstName' => 'Sarah',
                'lastName' => 'Johnson',
                'displayName' => 'Skipper Sarah',  // Custom displayName
            ]
        );
        $identity = $this->identity('custom.user@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert
        $this->assertNotNull($capturedCrew);
        $this->assertEquals('Skipper Sarah', $capturedCrew->getDisplayName());
    }

    public function testCrewDisplayNameHandlesUnicodeCharacters(): void
    {
        // Arrange
        $this->crewRepository->method('findByKey')->willReturn(null);

        $capturedCrew = null;
        $this->crewRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Crew $crew) use (&$capturedCrew) {
                $capturedCrew = $crew;
                return true;
            }));

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: [
                'firstName' => '李',
                'lastName' => '明',
                // No displayName provided
            ]
        );
        $identity = $this->identity('li.ming@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert
        $this->assertNotNull($capturedCrew);
        $this->assertEquals('李明', $capturedCrew->getDisplayName());
    }

    public function testBoatDisplayNameGeneratedWhenNull(): void
    {
        // Arrange
        $this->boatRepository->method('findByKey')->willReturn(null);

        $capturedBoat = null;
        $this->boatRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Boat $boat) use (&$capturedBoat) {
                $capturedBoat = $boat;
                return true;
            }));

        $request = new RegisterRequest(
            accountType: 'boat_owner',
            profile: [
                'ownerFirstName' => 'Bob',
                'ownerLastName' => 'Johnson',
                'ownerMobile' => '555-1234',
                'minBerths' => 2,
                'maxBerths' => 4,
                // No displayName provided
            ]
        );
        $identity = $this->identity('bob.boat@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert
        $this->assertNotNull($capturedBoat);
        $this->assertEquals('BobJ', $capturedBoat->getDisplayName());
    }

    public function testBoatDisplayNameGeneratedWhenEmptyString(): void
    {
        // Arrange
        $this->boatRepository->method('findByKey')->willReturn(null);

        $capturedBoat = null;
        $this->boatRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Boat $boat) use (&$capturedBoat) {
                $capturedBoat = $boat;
                return true;
            }));

        $request = new RegisterRequest(
            accountType: 'boat_owner',
            profile: [
                'ownerFirstName' => 'Alice',
                'ownerLastName' => 'Williams',
                'ownerMobile' => '555-5678',
                'minBerths' => 3,
                'maxBerths' => 6,
                'displayName' => '   ',  // Whitespace only
            ]
        );
        $identity = $this->identity('alice.boat@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert
        $this->assertNotNull($capturedBoat);
        $this->assertEquals('AliceW', $capturedBoat->getDisplayName());
    }

    public function testBoatDisplayNamePreservedWhenProvided(): void
    {
        // Arrange
        $this->boatRepository->method('findByKey')->willReturn(null);

        $capturedBoat = null;
        $this->boatRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Boat $boat) use (&$capturedBoat) {
                $capturedBoat = $boat;
                return true;
            }));

        $request = new RegisterRequest(
            accountType: 'boat_owner',
            profile: [
                'ownerFirstName' => 'Mike',
                'ownerLastName' => 'Davis',
                'ownerMobile' => '555-9012',
                'minBerths' => 2,
                'maxBerths' => 5,
                'displayName' => 'Sea Breeze',  // Custom boat name
            ]
        );
        $identity = $this->identity('custom.boat@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert
        $this->assertNotNull($capturedBoat);
        $this->assertEquals('Sea Breeze', $capturedBoat->getDisplayName());
    }

    public function testCrewDisplayNameHandlesWhitespaceInFirstName(): void
    {
        // Arrange
        $this->crewRepository->method('findByKey')->willReturn(null);

        $capturedCrew = null;
        $this->crewRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Crew $crew) use (&$capturedCrew) {
                $capturedCrew = $crew;
                return true;
            }));

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: [
                'firstName' => '  Mary  ',  // Whitespace around name
                'lastName' => 'Brown',
            ]
        );
        $identity = $this->identity('whitespace@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert
        $this->assertNotNull($capturedCrew);
        $this->assertEquals('MaryB', $capturedCrew->getDisplayName());
    }

    public function testBoatDisplayNameUsedForBoatKey(): void
    {
        // Arrange
        $this->boatRepository->method('findByKey')->willReturn(null);

        $capturedBoat = null;
        $this->boatRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Boat $boat) use (&$capturedBoat) {
                $capturedBoat = $boat;
                return true;
            }));

        $request = new RegisterRequest(
            accountType: 'boat_owner',
            profile: [
                'ownerFirstName' => 'Chris',
                'ownerLastName' => 'Taylor',
                'ownerMobile' => '555-3456',
                'minBerths' => 2,
                'maxBerths' => 4,
            ]
        );
        $identity = $this->identity('boat.key@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert
        $this->assertNotNull($capturedBoat);
        $this->assertEquals('ChrisT', $capturedBoat->getDisplayName());
        // Verify the boat key is also based on the displayName (normalized: lowercase, spaces removed)
        $this->assertEquals('christ', $capturedBoat->getKey()->toString());
    }

    public function testSendsAdminNotificationForCrewRegistration(): void
    {
        // Arrange
        $this->crewRepository->method('findByKey')->willReturn(null);

        $capturedEmails = [];
        $this->emailService->method('sendBulk')
            ->willReturnCallback(function ($recipients, $subject, $body) use (&$capturedEmails) {
                foreach ($recipients as $to) {
                    $capturedEmails[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
                }
                return array_fill_keys($recipients, true);
            });

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'displayName' => 'JohnD',
                'skill' => 1,
            ]
        );
        $identity = $this->identity('john.doe@example.com', $request);

        // Act
        $response = $this->useCase->execute($request, $identity);

        // Assert response
        $this->assertNotNull($response);
        $this->assertSame($identity['user_id'], $response->id);

        // Assert admin notification was sent to both the primary and secondary recipients
        foreach (['test-admin@example.com', 'test-admin-secondary@example.com'] as $expectedRecipient) {
            $adminEmails = array_filter($capturedEmails, fn($e) => $e['to'] === $expectedRecipient);
            $this->assertNotEmpty($adminEmails, "Expected notification to $expectedRecipient");
            $adminEmail = array_values($adminEmails)[0];
            $this->assertStringContainsString('New Crew Registration', $adminEmail['subject']);
            $this->assertStringContainsString('Crew member registration', $adminEmail['body']);
        }
    }

    public function testSendsAdminNotificationForBoatOwnerRegistration(): void
    {
        // Arrange
        $this->boatRepository->method('findByKey')->willReturn(null);

        $capturedEmails = [];
        $this->emailService->method('sendBulk')
            ->willReturnCallback(function ($recipients, $subject, $body) use (&$capturedEmails) {
                foreach ($recipients as $to) {
                    $capturedEmails[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
                }
                return array_fill_keys($recipients, true);
            });

        $request = new RegisterRequest(
            accountType: 'boat_owner',
            profile: [
                'ownerFirstName' => 'Captain',
                'ownerLastName' => 'Hook',
                'ownerMobile' => '555-1111',
                'displayName' => 'The Jolly Roger',
                'minBerths' => 2,
                'maxBerths' => 4,
            ]
        );
        $identity = $this->identity('captain@example.com', $request);

        // Act
        $response = $this->useCase->execute($request, $identity);

        // Assert response
        $this->assertNotNull($response);
        $this->assertSame($identity['user_id'], $response->id);

        // Assert admin notification was sent to both the primary and secondary recipients
        foreach (['test-admin@example.com', 'test-admin-secondary@example.com'] as $expectedRecipient) {
            $adminEmails = array_filter($capturedEmails, fn($e) => $e['to'] === $expectedRecipient);
            $this->assertNotEmpty($adminEmails, "Expected notification to $expectedRecipient");
            $adminEmail = array_values($adminEmails)[0];
            $this->assertStringContainsString('New Boat Owner Registration', $adminEmail['subject']);
            $this->assertStringContainsString('Boat Owner Registration', $adminEmail['body']);
        }
    }

    public function testRegistrationSucceedsWhenEmailFails(): void
    {
        // Arrange
        $this->crewRepository->method('findByKey')->willReturn(null);

        // Email service returns false (failure)
        $this->emailService->expects($this->any())
            ->method('send')
            ->willReturn(false);

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: [
                'firstName' => 'Test',
                'lastName' => 'User',
            ]
        );
        $identity = $this->identity('test@example.com', $request);

        // Act
        $response = $this->useCase->execute($request, $identity);

        // Assert - Registration should still succeed
        $this->assertNotNull($response);
        $this->assertSame($identity['user_id'], $response->id);
    }

    public function testRegistrationSucceedsWhenEmailThrowsException(): void
    {
        // Arrange
        $this->crewRepository->method('findByKey')->willReturn(null);

        // Email service throws exception
        $this->emailService->expects($this->any())
            ->method('send')
            ->willThrowException(new \Exception('Email service unavailable'));

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: [
                'firstName' => 'Exception',
                'lastName' => 'Test',
            ]
        );
        $identity = $this->identity('exception@example.com', $request);

        // Act
        $response = $this->useCase->execute($request, $identity);

        // Assert - Registration should still succeed despite email exception
        $this->assertNotNull($response);
        $this->assertSame($identity['user_id'], $response->id);
    }

    public function testEmailContainsCrewDetailsForCrewRegistration(): void
    {
        // Arrange
        $this->crewRepository->method('findByKey')->willReturn(null);

        $capturedEmails = [];
        $this->emailService->method('sendBulk')
            ->willReturnCallback(function ($recipients, $subject, $body) use (&$capturedEmails) {
                foreach ($recipients as $to) {
                    $capturedEmails[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
                }
                return array_fill_keys($recipients, true);
            });

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: [
                'firstName' => 'Detailed',
                'lastName' => 'Tester',
                'displayName' => 'D.Tester',
                'skill' => 2,
                'membershipNumber' => '12345',
                'mobile' => '555-1234',
            ]
        );
        $identity = $this->identity('detailed@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert - Verify admin email contains key crew details
        $adminEmails = array_filter($capturedEmails, fn($e) => $e['to'] === 'test-admin@example.com');
        $this->assertNotEmpty($adminEmails);
        $capturedEmailBody = array_values($adminEmails)[0]['body'];
        $this->assertStringContainsString('Detailed', $capturedEmailBody);
        $this->assertStringContainsString('Tester', $capturedEmailBody);
        $this->assertStringContainsString('D.Tester', $capturedEmailBody);
        $this->assertStringContainsString('Advanced', $capturedEmailBody); // skill level 2
        $this->assertStringContainsString('12345', $capturedEmailBody); // membership number
        $this->assertStringContainsString('555-1234', $capturedEmailBody); // mobile
        $this->assertStringContainsString('detailed@example.com', $capturedEmailBody); // email
    }

    public function testCrewDisplayNameSuffixedOnClash(): void
    {
        // Arrange: first call ("JohnD") clashes, second call ("JohnD2") is free
        $this->crewRepository = $this->createMock(CrewRepositoryInterface::class);
        $this->crewRepository->method('findByKey')->willReturn(null);
        $this->crewRepository->method('displayNameExists')
            ->willReturnCallback(fn($name) => $name === 'JohnD');  // 'JohnD' taken, 'JohnD2' free

        $capturedCrew = null;
        $this->crewRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Crew $crew) use (&$capturedCrew) {
                $capturedCrew = $crew;
                return true;
            }));

        // Rebuild use case with the new mock
        $this->useCase = new RegisterUseCase(
            $this->userRepository,
            $this->crewRepository,
            $this->boatRepository,
            $this->rankingService,
            $this->emailService,
            $this->emailTemplateService,
            $this->eventRepository,
            $this->calendarService,
            $this->config,
            $this->transactionService,
            new NullLogger()
        );

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: [
                'firstName' => 'John',
                'lastName' => 'Denver',
            ]
        );
        $identity = $this->identity('john.denver@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert: display name got the "2" suffix because "JohnD" was taken
        $this->assertNotNull($capturedCrew);
        $this->assertEquals('JohnD2', $capturedCrew->getDisplayName());
    }

    public function testBoatDisplayNameSuffixedOnClash(): void
    {
        // Arrange: "JohnD" is taken, "JohnD2" is free
        $this->boatRepository = $this->createMock(BoatRepositoryInterface::class);
        $this->boatRepository->method('findByKey')->willReturn(null);
        $this->boatRepository->method('displayNameExists')
            ->willReturnCallback(fn($name) => $name === 'JohnD');
        $this->boatRepository->method('findAll')->willReturn([]);

        $capturedBoat = null;
        $this->boatRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Boat $boat) use (&$capturedBoat) {
                $capturedBoat = $boat;
                return true;
            }));

        // Rebuild use case with the new mock
        $this->useCase = new RegisterUseCase(
            $this->userRepository,
            $this->crewRepository,
            $this->boatRepository,
            $this->rankingService,
            $this->emailService,
            $this->emailTemplateService,
            $this->eventRepository,
            $this->calendarService,
            $this->config,
            $this->transactionService,
            new NullLogger()
        );

        $request = new RegisterRequest(
            accountType: 'boat_owner',
            profile: [
                'ownerFirstName' => 'John',
                'ownerLastName' => 'Davidson',
                'ownerMobile' => '555-1111',
                'minBerths' => 2,
                'maxBerths' => 4,
            ]
        );
        $identity = $this->identity('john.davidson@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert: display name got the "2" suffix because "JohnD" was taken
        $this->assertNotNull($capturedBoat);
        $this->assertEquals('JohnD2', $capturedBoat->getDisplayName());
        $this->assertEquals('johnd2', $capturedBoat->getKey()->toString());
    }

    public function testWelcomeEmailIncludesICalAttachmentWhenFutureEventsExist(): void
    {
        // Recreate mocks that need non-default behaviour
        $this->eventRepository = $this->createMock(EventRepositoryInterface::class);
        $this->eventRepository->method('findFutureEvents')->willReturn(['fri-may-30']);
        $this->eventRepository->method('findById')->willReturn([
            'event_id'    => 'fri-may-30',
            'event_date'  => '2026-05-30',
            'start_time'  => '12:45:00',
            'finish_time' => '17:00:00',
        ]);

        $this->calendarService = $this->createMock(CalendarServiceInterface::class);
        $this->calendarService->expects($this->once())
            ->method('generateSeasonCalendar')
            ->willReturn('BEGIN:VCALENDAR...');

        $capturedArgs = null;
        $this->emailService = $this->createMock(EmailServiceInterface::class);
        $this->emailService->method('send')->willReturn(true);
        $this->emailService->method('sendWithAttachment')
            ->willReturnCallback(function (...$args) use (&$capturedArgs) {
                $capturedArgs = $args;
                return true;
            });

        $this->crewRepository->method('findByKey')->willReturn(null);

        $this->useCase = new RegisterUseCase(
            $this->userRepository,
            $this->crewRepository,
            $this->boatRepository,
            $this->rankingService,
            $this->emailService,
            $this->emailTemplateService,
            $this->eventRepository,
            $this->calendarService,
            $this->config,
            $this->transactionService,
            new NullLogger()
        );

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: ['firstName' => 'New', 'lastName' => 'User']
        );
        $identity = $this->identity('new@example.com', $request);

        $this->useCase->execute($request, $identity);

        $this->assertNotNull($capturedArgs);
        $this->assertEquals('new@example.com', $capturedArgs[0]);
        $this->assertEquals('social-day-cruising.ics', $capturedArgs[4]);
        $this->assertEquals('text/calendar', $capturedArgs[5]);
    }

    public function testEmailContainsBoatDetailsForBoatOwnerRegistration(): void
    {
        // Arrange
        $this->boatRepository->method('findByKey')->willReturn(null);

        $capturedEmails = [];
        $this->emailService->method('sendBulk')
            ->willReturnCallback(function ($recipients, $subject, $body) use (&$capturedEmails) {
                foreach ($recipients as $to) {
                    $capturedEmails[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
                }
                return array_fill_keys($recipients, true);
            });

        $request = new RegisterRequest(
            accountType: 'boat_owner',
            profile: [
                'ownerFirstName' => 'Boat',
                'ownerLastName' => 'Owner',
                'displayName' => 'SS Minnow',
                'minBerths' => 3,
                'maxBerths' => 5,
                'ownerMobile' => '555-5678',
                'assistanceRequired' => true,
                'socialPreference' => true,
            ]
        );
        $identity = $this->identity('boat@example.com', $request);

        // Act
        $this->useCase->execute($request, $identity);

        // Assert - Verify admin email contains key boat details
        $adminEmails = array_filter($capturedEmails, fn($e) => $e['to'] === 'test-admin@example.com');
        $this->assertNotEmpty($adminEmails);
        $capturedEmailBody = array_values($adminEmails)[0]['body'];
        $this->assertStringContainsString('Boat', $capturedEmailBody);
        $this->assertStringContainsString('Owner', $capturedEmailBody);
        $this->assertStringContainsString('SS Minnow', $capturedEmailBody);
        $this->assertStringContainsString('3-5', $capturedEmailBody); // berth capacity
        $this->assertStringContainsString('555-5678', $capturedEmailBody); // mobile
        $this->assertStringContainsString('Yes', $capturedEmailBody); // assistance required
        $this->assertStringContainsString('boat@example.com', $capturedEmailBody); // email
    }

    public function testIdentityNamesOverrideSubmittedProfileNames(): void
    {
        $this->crewRepository->method('findByKey')->willReturn(null);

        $capturedCrew = null;
        $this->crewRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Crew $crew) use (&$capturedCrew) {
                $capturedCrew = $crew;
                return true;
            }));

        $request = new RegisterRequest(
            accountType: 'crew',
            profile: ['firstName' => 'Spoofed', 'lastName' => 'Name', 'membershipNumber' => '9999-1']
        );
        $identity = [
            'user_id' => 'auth-1', 'email_address' => 'real@example.com',
            'first_name' => 'Real', 'last_name' => 'Person', 'is_member' => false, 'membership_number' => null,
        ];

        $this->useCase->execute($request, $identity);

        $this->assertSame('Real', $capturedCrew->getFirstName());
        $this->assertSame('Person', $capturedCrew->getLastName());
        $this->assertNull($capturedCrew->getMembershipNumber());
    }

    public function testBoatOwnerWithoutMobileInSailConnectProfileIsRejected(): void
    {
        $request = new RegisterRequest(
            accountType: 'boat_owner',
            // A mobile number in the request body is ignored: only the sailConnect profile counts
            profile: ['ownerFirstName' => 'No', 'ownerLastName' => 'Phone', 'ownerMobile' => '555-0000', 'minBerths' => 1, 'maxBerths' => 2]
        );
        $identity = $this->identity('nophone@example.com', $request);
        $identity['mobile_number'] = null;

        $this->crewRepository->expects($this->never())->method('save');
        $this->boatRepository->expects($this->never())->method('save');

        try {
            $this->useCase->execute($request, $identity);
            $this->fail('Expected ValidationException');
        } catch (\App\Application\Exception\ValidationException $e) {
            $this->assertArrayHasKey('profile.ownerMobile', $e->getErrors());
        }
    }

    public function testCrewJoiningWhatsAppWithoutMobileInSailConnectProfileIsRejected(): void
    {
        $request = new RegisterRequest(accountType: 'crew', profile: ['socialPreference' => true, 'mobile' => '555-0000']);
        $identity = $this->identity('nophone@example.com', $request);
        $identity['mobile_number'] = null;

        $this->crewRepository->expects($this->never())->method('save');

        try {
            $this->useCase->execute($request, $identity);
            $this->fail('Expected ValidationException');
        } catch (\App\Application\Exception\ValidationException $e) {
            $this->assertArrayHasKey('profile.mobile', $e->getErrors());
        }
    }

    public function testCrewMobileComesFromSailConnectNotFromTheRequest(): void
    {
        $this->crewRepository->method('findByKey')->willReturn(null);
        $captured = null;
        $this->crewRepository->method('save')->willReturnCallback(function (Crew $crew) use (&$captured) {
            $captured = $crew;
        });

        $request = new RegisterRequest(accountType: 'crew', profile: ['socialPreference' => true, 'mobile' => '555-0000']);
        $identity = $this->identity('real@example.com', $request);
        $identity['mobile_number'] = '613-555-0100';

        $this->useCase->execute($request, $identity);

        $this->assertSame('613-555-0100', $captured->getMobile());
    }

    public function testRejectsIdentityThatIsAlreadyEnrolled(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(
            new \App\Domain\Entity\User('real@example.com', 'crew', false, null, null, 'auth-1')
        );
        $useCase = new RegisterUseCase(
            $userRepository, $this->crewRepository, $this->boatRepository, $this->rankingService,
            $this->emailService, $this->emailTemplateService, $this->eventRepository, $this->calendarService,
            $this->config, $this->transactionService, new NullLogger()
        );
        $request = new RegisterRequest(accountType: 'crew', profile: []);
        $identity = [
            'user_id' => 'auth-1', 'email_address' => 'real@example.com',
            'first_name' => 'Real', 'last_name' => 'Person', 'is_member' => false, 'membership_number' => null,
        ];

        $this->expectException(\App\Application\Exception\UserAlreadyExistsException::class);
        $useCase->execute($request, $identity);
    }
}
