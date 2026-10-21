<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule violation (insufficient funds, invalid state transition, forbidden actor...).
 * Rendered as {"error": {"code": ..., "message": ...}} with the given HTTP status.
 */
class EscrowException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function insufficientFunds(int $availableCents, int $requiredCents): self
    {
        return new self('insufficient_funds', 'Wallet balance is insufficient to fund this milestone.', 422, [
            'available' => $availableCents / 100,
            'required' => $requiredCents / 100,
        ]);
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self('invalid_status_transition', "Milestone cannot move from '{$from}' to '{$to}'.", 409, [
            'current_status' => $from,
        ]);
    }

    public static function forbidden(string $message): self
    {
        return new self('forbidden', $message, 403);
    }
}
