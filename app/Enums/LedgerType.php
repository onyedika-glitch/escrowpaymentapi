<?php

namespace App\Enums;

enum LedgerType: string
{
    case WalletFunded = 'wallet_funded';           // client deposit: available += amount
    case MilestoneFunded = 'milestone_funded';     // client: available -> escrow
    case MilestoneReleased = 'milestone_released'; // client: escrow -= amount (paid out)
    case PaymentReceived = 'payment_received';     // freelancer: available += amount
}
