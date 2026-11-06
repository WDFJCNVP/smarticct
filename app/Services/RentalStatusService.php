<?php

namespace App\Services;

use App\Enums\RentalStatus;
use App\Models\User;
use App\Models\Vehicle;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Switches a vehicle between Active and Inactive for renting.
 * One place for the rules, so every page (the rental page switch, the vehicle record select)
 * behaves the same.
 */
class RentalStatusService
{
    /**
     * @throws DomainException with a message that is safe to show to the operator
     */
    public function set(Vehicle $vehicle, User $operator, RentalStatus $target): Vehicle
    {
        if ($vehicle->user_id !== $operator->id) {
            throw new DomainException('This is not your vehicle.');
        }

        // Operators can only switch between active and inactive. Suspended is admin-only.
        if (! in_array($target, [RentalStatus::Active, RentalStatus::Inactive], true)) {
            throw new DomainException('That status cannot be set here.');
        }

        return DB::transaction(function () use ($vehicle, $operator, $target) {
            $locked = Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            if ($locked->rental_status === RentalStatus::Suspended) {
                throw new DomainException('Rental for this vehicle was suspended by the admin.');
            }

            if ($target === RentalStatus::Active) {
                $problems = $this->activationProblems($locked, $operator);

                if ($problems !== []) {
                    throw new DomainException(implode(' ', $problems));
                }
            }

            $locked->rental_status = $target;
            $locked->save();

            return $locked;
        });
    }

    /** @return list<string> reasons this vehicle cannot be set to active yet */
    public function activationProblems(Vehicle $vehicle, User $operator): array
    {
        $problems = [];

        if ($operator->card === null) {
            $problems[] = 'You need an issued card before you can rent out a vehicle.';
        }

        $franchiseOk = $vehicle->has_franchise
            && in_array($vehicle->documentStatus(), ['valid', 'expiring'], true);

        if (! $franchiseOk) {
            $problems[] = 'This vehicle needs a valid franchise.';
        }

        if (! $vehicle->eventRates()->offered()->exists()) {
            $problems[] = 'Set up at least one rental event first.';
        }

        return $problems;
    }
}