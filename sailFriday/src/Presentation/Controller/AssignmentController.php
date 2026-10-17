<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\DTO\Request\FlagAssignedCrewRequest;
use App\Application\DTO\Request\UpdateAssignedCrewSkillRequest;
use App\Application\Exception\BoatNotFoundException;
use App\Application\Exception\CrewNotFoundException;
use App\Application\Exception\ValidationException;
use App\Application\UseCase\Boat\FlagAssignedCrewUseCase;
use App\Application\UseCase\Boat\RemoveAssignedCrewFromWhitelistUseCase;
use App\Application\UseCase\Boat\UpdateAssignedCrewSkillUseCase;
use App\Application\UseCase\Crew\GetUserAssignmentsUseCase;
use App\Application\UseCase\Season\ProcessSeasonUpdateUseCase;
use App\Presentation\Response\JsonResponse;

/**
 * Assignment Controller
 *
 * Handles assignment-related endpoints (authenticated access).
 */
class AssignmentController
{
    public function __construct(
        private GetUserAssignmentsUseCase $getUserAssignmentsUseCase,
        private FlagAssignedCrewUseCase $flagAssignedCrewUseCase,
        private UpdateAssignedCrewSkillUseCase $updateAssignedCrewSkillUseCase,
        private RemoveAssignedCrewFromWhitelistUseCase $removeAssignedCrewFromWhitelistUseCase,
        private ProcessSeasonUpdateUseCase $processSeasonUpdateUseCase,
    ) {
    }

    /**
     * GET /api/assignments
     *
     * Returns user's assignments across all events.
     *
     * @param array $auth Authentication data (user_id, email, account_type, is_admin)
     */
    public function getUserAssignments(array $auth): JsonResponse
    {
        try {
            // Execute use case with user ID
            $assignments = $this->getUserAssignmentsUseCase->execute($auth['user_id']);

            return JsonResponse::success([
                'assignments' => array_map(fn($a) => $a->toArray(), $assignments),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::serverError($e->getMessage());
        }
    }

    /**
     * POST /api/assignments/crew-flags
     *
     * Lets a boat owner flag crew members assigned to their boat as no-shows,
     * recomputing their commitment rank from their total no-show count,
     * withdrawing them from future events, and deactivating the account if
     * commitment rank hits 0.
     *
     * @param array $body Request body (flags: array of {eventId, crewKey})
     * @param array $auth Authentication data (user_id, email, account_type, is_admin)
     */
    public function flagCrew(array $body, array $auth): JsonResponse
    {
        try {
            $request = FlagAssignedCrewRequest::fromArray($body);
            $errors = $request->validate();
            if (!empty($errors)) {
                throw new ValidationException($errors);
            }

            $results = $this->flagAssignedCrewUseCase->execute($auth['user_id'], $request->flags);

            return JsonResponse::success(['flagged' => $results]);
        } catch (BoatNotFoundException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (ValidationException $e) {
            return JsonResponse::error($e->getMessage(), 400, $e->getErrors());
        } catch (\Exception $e) {
            return JsonResponse::serverError($e->getMessage());
        }
    }

    /**
     * PATCH /api/assignments/crew-skill
     *
     * Lets a boat owner correct the skill level of a crew member who was
     * assigned to their boat for a past event.
     *
     * @param array $body Request body (eventId, crewKey, skill)
     * @param array $auth Authentication data (user_id, email, account_type, is_admin)
     */
    public function updateCrewSkill(array $body, array $auth): JsonResponse
    {
        try {
            $request = UpdateAssignedCrewSkillRequest::fromArray($body);
            $errors = $request->validate();
            if (!empty($errors)) {
                throw new ValidationException($errors);
            }

            $result = $this->updateAssignedCrewSkillUseCase->execute(
                $auth['user_id'],
                $request->eventId,
                $request->crewKey,
                $request->skill
            );

            return JsonResponse::success($result);
        } catch (BoatNotFoundException|CrewNotFoundException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (ValidationException $e) {
            return JsonResponse::error($e->getMessage(), 400, $e->getErrors());
        } catch (\Exception $e) {
            return JsonResponse::serverError($e->getMessage());
        }
    }

    /**
     * DELETE /api/assignments/crew-whitelist/{eventId}/{crewKey}
     *
     * Lets a boat owner remove their own boat from the whitelist of a crew
     * member who was assigned to their boat for a past event.
     *
     * @param array $params Route parameters (eventId, crewKey)
     * @param array $auth Authentication data (user_id, email, account_type, is_admin)
     */
    public function removeCrewFromWhitelist(array $params, array $auth): JsonResponse
    {
        try {
            $result = $this->removeAssignedCrewFromWhitelistUseCase->execute(
                $auth['user_id'],
                $params['eventId'],
                $params['crewKey']
            );

            return JsonResponse::success($result);
        } catch (BoatNotFoundException|CrewNotFoundException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (ValidationException $e) {
            return JsonResponse::error($e->getMessage(), 400, $e->getErrors());
        } catch (\Exception $e) {
            return JsonResponse::serverError($e->getMessage());
        }
    }

    /**
     * POST /api/assignments/recalculate
     *
     * Re-runs the season update pipeline (ranking, selection, and flotilla
     * generation) using current database contents. Used after a boat owner
     * corrects a past-event crewmate's skill or flags a no-show, so the next
     * event's assignment reflects the correction immediately.
     *
     * @param array $auth Authentication data (user_id, email, account_type, is_admin)
     */
    public function recalculate(array $auth): JsonResponse
    {
        // Lock contention (\RuntimeException, code 409) is left to propagate to
        // ErrorHandlerMiddleware, which the frontend already retries automatically.
        $result = $this->processSeasonUpdateUseCase->execute();

        return JsonResponse::success($result);
    }
}
