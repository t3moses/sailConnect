<?php

declare(strict_types=1);

namespace App\Application\UseCase\User;

use App\Application\DTO\Request\UpdateProfileRequest;
use App\Application\DTO\Response\ProfileResponse;
use App\Application\Exception\ValidationException;
use App\Application\Exception\WeakPasswordException;
use App\Application\Port\Repository\BoatRepositoryInterface;
use App\Application\Port\Repository\CrewRepositoryInterface;
use App\Application\Port\Repository\UserRepositoryInterface;
use App\Application\Port\Service\EmailServiceInterface;
use App\Application\Port\Service\EmailTemplateServiceInterface;
use App\Application\Port\Service\PasswordServiceInterface;
use App\Domain\Entity\Boat;
use App\Domain\Entity\Crew;
use App\Domain\Entity\User;
use Psr\Log\LoggerInterface;

/**
 * Update User Profile Use Case
 *
 * Updates user account information (email, password) and/or crew/boat profiles.
 */
class UpdateUserProfileUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private CrewRepositoryInterface $crewRepository,
        private BoatRepositoryInterface $boatRepository,
        private PasswordServiceInterface $passwordService,
        private GetUserProfileUseCase $getUserProfileUseCase,
        private EmailServiceInterface $emailService,
        private EmailTemplateServiceInterface $emailTemplateService,
        private string $adminNotificationEmail,
        private LoggerInterface $logger,
        private ?string $secondaryNotificationEmail = null,
    ) {
    }

    /**
     * Execute update user profile
     *
     * @param int $userId User ID
     * @param UpdateProfileRequest $request Update request
     * @return ProfileResponse Updated profile
     * @throws ValidationException If validation fails
     * @throws WeakPasswordException If password doesn't meet requirements
     */
    public function execute(int $userId, UpdateProfileRequest $request): ProfileResponse
    {
        // Validate request
        $errors = $request->validate();
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Check if request has any updates
        if (!$request->hasUpdates()) {
            throw new ValidationException(['error' => 'No updates provided']);
        }

        // Get user
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new \RuntimeException('User not found');
        }

        // Update email if provided
        if ($request->email !== null && !empty($request->email)) {
            // Check if email is already taken by another user
            $existingUser = $this->userRepository->findByEmail($request->email);
            if ($existingUser !== null && $existingUser->getId() !== $userId) {
                throw new ValidationException(['email' => 'Email already exists']);
            }
            $user->setEmail($request->email);
        }

        // Update password if provided
        if ($request->password !== null && !empty($request->password)) {
            if (!$this->passwordService->meetsRequirements($request->password)) {
                throw new WeakPasswordException($this->passwordService->getRequirementsMessage());
            }
            $passwordHash = $this->passwordService->hash($request->password);
            $user->setPasswordHash($passwordHash);
        }

        // Save user updates
        $this->userRepository->save($user);

        // Update crew profile if provided
        $updatedCrew = null;
        if ($request->crewProfile !== null && !empty($request->crewProfile)) {
            $updatedCrew = $this->updateCrewProfile($userId, $request->crewProfile);
        }

        // Update boat profile if provided
        $updatedBoat = null;
        if ($request->boatProfile !== null && !empty($request->boatProfile)) {
            $updatedBoat = $this->updateBoatProfile($userId, $request->boatProfile);
        }

        $updatedSections = array_values(array_filter([
            ($request->email !== null && !empty($request->email)) ? 'email' : null,
            ($request->password !== null && !empty($request->password)) ? 'password' : null,
            ($request->crewProfile !== null && !empty($request->crewProfile)) ? 'crew' : null,
            ($request->boatProfile !== null && !empty($request->boatProfile)) ? 'boat' : null,
        ]));
        $this->logger->info('user.profile_updated', [
            'user_id'          => $userId,
            'updated_sections' => $updatedSections,
        ]);

        // Send admin notification email (don't fail the update if email fails)
        if ($updatedCrew !== null) {
            $this->sendCrewAdminNotification($user, $updatedCrew);
        }
        if ($updatedBoat !== null) {
            $this->sendBoatAdminNotification($user, $updatedBoat);
        }

        // Return updated profile
        return $this->getUserProfileUseCase->execute($userId);
    }

    /**
     * Send admin notification email summarizing the revised crew profile
     *
     * @param User $user User entity
     * @param Crew $crew Updated crew entity
     * @return void
     */
    private function sendCrewAdminNotification(User $user, Crew $crew): void
    {
        try {
            $profile = [
                'firstName'        => $crew->getFirstName(),
                'lastName'         => $crew->getLastName(),
                'displayName'      => $crew->getDisplayName(),
                'mobile'           => $crew->getMobile(),
                'skill'            => $crew->getSkill()->value,
                'membershipNumber' => $crew->getMembershipNumber(),
                'socialPreference' => $crew->hasSocialPreference(),
                'experience'       => $crew->getExperience(),
            ];

            $subject = sprintf('Crew Profile Updated - %s %s', $profile['firstName'], $profile['lastName']);
            $body = $this->emailTemplateService->renderCrewProfileUpdateNotification($user, $profile);
            $results = $this->emailService->sendBulk($this->getNotificationRecipients(), $subject, $body);

            $logContext = ['type' => 'admin_profile_update', 'account_type' => 'crew'];
            foreach ($results as $recipient => $success) {
                if ($success) {
                    $this->logger->info('email.sent', $logContext + ['to' => $recipient]);
                } else {
                    $this->logger->warning('email.failed', $logContext + ['to' => $recipient]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('email.failed', ['type' => 'admin_profile_update', 'account_type' => 'crew', 'to' => implode(',', $this->getNotificationRecipients()), 'error' => $e->getMessage()]);
        }
    }

    /**
     * Send admin notification email summarizing the revised boat profile
     *
     * @param User $user User entity
     * @param Boat $boat Updated boat entity
     * @return void
     */
    private function sendBoatAdminNotification(User $user, Boat $boat): void
    {
        try {
            $profile = [
                'displayName'         => $boat->getDisplayName(),
                'ownerFirstName'      => $boat->getOwnerFirstName(),
                'ownerLastName'       => $boat->getOwnerLastName(),
                'ownerMobile'         => $boat->getOwnerMobile(),
                'minBerths'           => $boat->getMinBerths(),
                'maxBerths'           => $boat->getMaxBerths(),
                'assistanceRequired'  => $boat->requiresAssistance(),
                'socialPreference'    => $boat->hasSocialPreference(),
            ];

            $subject = sprintf('Boat Profile Updated - %s', $profile['displayName']);
            $body = $this->emailTemplateService->renderBoatOwnerProfileUpdateNotification($user, $profile);
            $results = $this->emailService->sendBulk($this->getNotificationRecipients(), $subject, $body);

            $logContext = ['type' => 'admin_profile_update', 'account_type' => 'boat_owner'];
            foreach ($results as $recipient => $success) {
                if ($success) {
                    $this->logger->info('email.sent', $logContext + ['to' => $recipient]);
                } else {
                    $this->logger->warning('email.failed', $logContext + ['to' => $recipient]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('email.failed', ['type' => 'admin_profile_update', 'account_type' => 'boat_owner', 'to' => implode(',', $this->getNotificationRecipients()), 'error' => $e->getMessage()]);
        }
    }

    /**
     * Build the list of admin notification recipients, including the secondary recipient if configured
     *
     * @return string[]
     */
    private function getNotificationRecipients(): array
    {
        return array_filter([$this->adminNotificationEmail, $this->secondaryNotificationEmail]);
    }

    /**
     * Update crew profile
     *
     * @param int $userId User ID
     * @param array $profile Crew profile updates
     * @return Crew Updated crew entity
     */
    private function updateCrewProfile(int $userId, array $profile): Crew
    {
        $crew = $this->crewRepository->findByUserId($userId);
        if ($crew === null) {
            throw new ValidationException(['crew_profile' => 'User does not have a crew profile']);
        }

        // Store original rank to preserve it (especially flexibility rank)
        $originalRank = $crew->getRank();

        // Update allowed fields
        if (isset($profile['firstName'])) {
            $crew->setFirstName($profile['firstName']);
        }
        if (isset($profile['lastName'])) {
            $crew->setLastName($profile['lastName']);
        }
        if (isset($profile['displayName'])) {
            $crew->setDisplayName($profile['displayName']);
        }
        if (isset($profile['mobile'])) {
            $crew->setMobile($profile['mobile']);
        }
        if (isset($profile['skill'])) {
            $crew->setSkill(\App\Domain\Enum\SkillLevel::from((int)$profile['skill']));
        }
        if (isset($profile['socialPreference'])) {
            $crew->setSocialPreference($profile['socialPreference']);
        }
        if (isset($profile['membershipNumber'])) {
            $crew->setMembershipNumber($profile['membershipNumber']);
        }
        if (array_key_exists('experience', $profile)) {
            $crew->setExperience($profile['experience'] ?: null);
        }
        // Restore original rank before saving to avoid overwriting pipeline-managed dimensions
        // (availability, commitment, absence), then reapply the membership dimension so a
        // qualifying/non-qualifying membership number change is still reflected.
        $crew->setRank($originalRank);
        $crew->setRankDimension(
            \App\Domain\Enum\CrewRankDimension::MEMBERSHIP,
            Crew::calculateMembershipRank($crew->getMembershipNumber())
        );

        $this->crewRepository->save($crew);

        return $crew;
    }

    /**
     * Update boat profile
     *
     * @param int $userId User ID
     * @param array $profile Boat profile updates
     * @return Boat Updated boat entity
     */
    private function updateBoatProfile(int $userId, array $profile): Boat
    {
        $boat = $this->boatRepository->findByOwnerUserId($userId);
        if ($boat === null) {
            throw new ValidationException(['boat_profile' => 'User does not have a boat profile']);
        }

        // Store original rank to preserve it (especially flexibility rank)
        $originalRank = $boat->getRank();

        // Update allowed fields
        if (isset($profile['ownerFirstName'])) {
            $boat->setOwnerFirstName($profile['ownerFirstName']);
        }
        if (isset($profile['ownerLastName'])) {
            $boat->setOwnerLastName($profile['ownerLastName']);
        }
        if (isset($profile['displayName'])) {
            $boat->setDisplayName($profile['displayName']);
        }
        if (isset($profile['ownerMobile'])) {
            $boat->setOwnerMobile($profile['ownerMobile']);
        }
        if (isset($profile['minBerths'])) {
            $boat->setMinBerths((int)$profile['minBerths']);
        }
        if (isset($profile['maxBerths'])) {
            $boat->setMaxBerths((int)$profile['maxBerths']);
        }
        if (isset($profile['assistanceRequired'])) {
            $boat->setAssistanceRequired($profile['assistanceRequired']);
        }
        if (isset($profile['socialPreference'])) {
            $boat->setSocialPreference($profile['socialPreference']);
        }
        if (isset($profile['willingToCrew'])) {
            $boat->setRankDimension(
                \App\Domain\Enum\BoatRankDimension::FLEXIBILITY,
                ((bool)$profile['willingToCrew']) ? 0 : 1
            );
        }

        // Validate berth consistency before saving
        if ($boat->getMinBerths() > $boat->getMaxBerths()) {
            throw new ValidationException(['maxBerths' => 'maxBerths must be greater than or equal to minBerths']);
        }

        // Restore original rank to avoid overwriting pipeline-managed values,
        // but carry forward any willingToCrew change in the flexibility dimension
        $rankToRestore = $originalRank;
        if (isset($profile['willingToCrew'])) {
            $rankToRestore = $rankToRestore->withDimension(
                \App\Domain\Enum\BoatRankDimension::FLEXIBILITY,
                ((bool)$profile['willingToCrew']) ? 0 : 1
            );
        }
        $boat->setRank($rankToRestore);

        $this->boatRepository->save($boat);

        return $boat;
    }
}
