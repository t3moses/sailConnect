<?php

declare(strict_types=1);

namespace App\Application\Exception;

use App\Domain\ValueObject\BoatKey;

/**
 * Boat Not Found Exception
 *
 * Thrown when a boat cannot be found in the repository.
 */
class BoatNotFoundException extends \RuntimeException
{
    public function __construct(BoatKey|string $keyOrMessage)
    {
        if ($keyOrMessage instanceof BoatKey) {
            $message = "Boat not found: {$keyOrMessage->toString()}";
        } else {
            $message = $keyOrMessage;
        }

        parent::__construct($message);
    }
}
