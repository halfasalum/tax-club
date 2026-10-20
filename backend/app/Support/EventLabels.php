<?php

namespace App\Support;

/**
 * Translated names for event types, modes and registration states.
 */
final class EventLabels
{
    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'workshop' => __('Workshop'),
            'seminar' => __('Seminar'),
            'competition' => __('Competition'),
            'campaign' => __('Campaign'),
            'webinar' => __('Webinar'),
            'school_visit' => __('School visit'),
            'tax_day' => __('Taxpayers\' day'),
            'other' => __('Other'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function modes(): array
    {
        return [
            'in_person' => __('In person'),
            'online' => __('Online'),
            'hybrid' => __('In person and online'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function registrationStatuses(): array
    {
        return [
            'registered' => __('Registered'),
            'waitlisted' => __('On the waitlist'),
            'cancelled' => __('Cancelled'),
            'attended' => __('Attended'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function checkInMethods(): array
    {
        return [
            'qr' => __('Card QR code'),
            'number' => __('Membership number'),
            'manual' => __('Ticked on the list'),
        ];
    }
}
