<?php

namespace Drivezy\LaravelAssetManager\Library\AssetManagement\AssetAvailability;

use Drivezy\LaravelAssetManager\Models\AssetBooking;
use Drivezy\LaravelUtility\Library\Message;

/**
 * Class ResetAssetAvailability
 * @package Drivezy\LaravelAssetManager\Library\AssetManagement\AssetAvailability
 *
 * @see https://github.com/drivezy/laravel-asset-manager
 * @author Ankit Tiwari  ankit19.alpha@gmail.com>
 */
class ResetAssetAvailability extends BaseAvailability
{
    /**
     * Pending and active Bookings array
     * Sorted with start_time ascending
     * @var AssetBooking object|null
     */
    protected $bookings = [];

    /**
     * Reset Asset availability
     * True will reset all asset availability record and recreate it to the venue given
     * False will just refresh position with timing.
     * @var bool
     */
    protected $reset = false;

    /**
     * Process setting venues
     */
    public function process ()
    {
        if ( !$this->validation() )
            return false;

        $this->resetAvailability();

        return true;
    }

    /**
     * Validation checks to.
     * @return boolean
     */
    private function validation ()
    {
        if ( !$this->assetDetail )
            return Message::error('No such asset in system');

        if ( !$this->assetDetail->active )
            return Message::error('Asset is inactive.');

        return true;
    }

    /**
     * Reset Availability
     * Delete all availability
     * Fetch sorted array of blocking in ascending order
     */
    private function resetAvailability ()
    {
        $this->deleteAllAvailability($this->assetDetail->id);

        $this->getSortedArrayOfBooking();

        if ( !$this->reset )
            $this->refreshAvailability();
        else
            $this->setAvailability();
    }

    /**
     * Gives sorted arrays of blocks.
     * Get all bookings
     * Get all maintenance blocks
     * merge above both array and sort it.
     */
    private function getSortedArrayOfBooking ()
    {
        $this->getAllBookings();
        sort($this->bookings);
    }

    /**
     * Set availability of asset on a given venue
     */
    private function setAvailability ()
    {
        $time = $this->currentTime;

        foreach ( $this->bookings as $booking ) {
            if ( $time >= $booking['start_time'] ) {
                if ( $time <= $booking['end_time'] )
                    $time = $booking['end_time'];

                continue;
            }

            $this->createAvailability($time, $booking['start_time'], $this->venue->id);
            $time = $booking['end_time'];
        }

        if ( $time < $this->maxAvailabilityDateTime )
            $this->createAvailability($time, $this->maxAvailabilityDateTime, $this->venue->id);
    }


    /**
     * Refresh the availability on drop_venue of bookings
     */
    private function refreshAvailability ()
    {
        $time = $this->currentTime;
        $venueId = $this->lastBookingVenue();

        foreach ( $this->bookings as $booking ) {
            if ( $time >= $booking['start_time'] ) {
                if ( $time <= $booking['end_time'] ) {
                    $time = $booking['end_time'];
                    $venueId = $booking['drop_venue_id'];
                }

                continue;
            }

            $this->createAvailability($time, $booking['start_time'], $venueId);
            $time = $booking['end_time'];
            $venueId = $booking['drop_venue_id'];
        }

        if ( $time < $this->maxAvailabilityDateTime )
            $this->createAvailability($time, $this->maxAvailabilityDateTime, $venueId);
    }

    /**
     * Eloquent to give all bookings having drop time in future to current time
     */
    private function getAllBookings ()
    {
        $this->bookings = AssetBooking::where('asset_detail_id', $this->assetDetail->id)
            ->where('status_id', '<', COMPLETED)
            ->where('end_time', '>=', $this->lastAvailabilityTime)
            ->orderBy('start_time', 'asc')
            ->get(['actual_start_time AS start_time', 'actual_end_time AS end_time', 'pickup_venue_id', 'drop_venue_id'])
            ->toArray();
    }

    /**
     * @return mixed
     */
    private function lastBookingVenue ()
    {
        $booking = AssetBooking::where('asset_detail_id', $this->assetDetail->id)
            ->where('status_id', COMPLETED)
            ->where('end_time', '<', $this->currentTime)
            ->orderBy('end_time', 'desc')
            ->first();

        return $booking ? $booking->drop_venue_id : $this->assetDetail->home_venue_id;
    }
}