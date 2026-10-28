<?php

namespace App\Enums;

enum WalletReservationStatus: string
{
    /** Funds are held and still count toward the wallet's reserved balance. */
    case Pending = 'pending';

    /** The hold was settled and the amount was deducted from the balance. */
    case Confirmed = 'confirmed';

    /** The hold was cancelled and the funds returned to the available balance. */
    case Released = 'released';

    /** The hold passed its expiry without being confirmed or released. */
    case Expired = 'expired';

    /**
     * Whether the reservation still holds funds against the wallet.
     */
    public function isActive(): bool
    {
        return $this === self::Pending;
    }
}
