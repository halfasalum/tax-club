<?php

namespace App\Support;

use App\Notifications\AccountDeactivated;
use App\Notifications\AccountReactivated;
use App\Notifications\AccountSuspended;
use App\Notifications\AlumniStatusGranted;
use App\Notifications\BadgeEarned;
use App\Notifications\BroadcastMessage;
use App\Notifications\ContentReviewed;
use App\Notifications\CoordinatorAssigned;
use App\Notifications\EventChanged;
use App\Notifications\EventPlaceOffered;
use App\Notifications\EventRegistrationConfirmed;
use App\Notifications\EventReminder;
use App\Notifications\EventUpdatePosted;
use App\Notifications\FeedbackAssigned;
use App\Notifications\FeedbackUpdated;
use App\Notifications\OpportunityDeadlineReminder;
use App\Notifications\PasswordChanged;
use App\Notifications\PasswordResetCode;
use App\Notifications\PatronAssigned;
use App\Notifications\PhoneChangeCode;
use App\Notifications\PointsAdjusted;
use App\Notifications\QuizClosingReminder;
use App\Notifications\RegistrationApproved;
use App\Notifications\RegistrationReceived;
use App\Notifications\RegistrationRejected;
use App\Notifications\StaffLoginCode;
use App\Notifications\TaxClubNotification;
use Illuminate\Support\Facades\App;

/**
 * Every notification administrators can configure (NOT-REQ01).
 */
final class NotificationCatalogue
{
    /** @var list<class-string<TaxClubNotification>> */
    public const NOTIFICATIONS = [
        RegistrationReceived::class,
        RegistrationApproved::class,
        RegistrationRejected::class,
        AccountSuspended::class,
        AccountDeactivated::class,
        AccountReactivated::class,
        AlumniStatusGranted::class,
        PatronAssigned::class,
        CoordinatorAssigned::class,
        BadgeEarned::class,
        PointsAdjusted::class,
        ContentReviewed::class,
        BroadcastMessage::class,
        EventRegistrationConfirmed::class,
        EventPlaceOffered::class,
        EventChanged::class,
        EventUpdatePosted::class,
        EventReminder::class,
        OpportunityDeadlineReminder::class,
        QuizClosingReminder::class,
        FeedbackUpdated::class,
        FeedbackAssigned::class,
        PasswordChanged::class,
        PasswordResetCode::class,
        StaffLoginCode::class,
        PhoneChangeCode::class,
    ];

    /** Example values used for previews. */
    public const SAMPLES = [
        'name' => 'Asha M.',
        'reference' => 'REG-2026-AB12CD',
        'number' => 'TC-26-DSM-000123-4',
        'reason' => 'Example reason',
        'url' => 'https://club.tra.go.tz/login',
        'club' => 'Azania Secondary School Tax Club',
        'region' => 'Mwanza',
        'code' => '482913',
        'minutes' => '10',
        'title' => 'VAT in daily life',
        'decision' => 'approved',
        'note' => '',
        'badge' => '🏅 Quiz champion',
        'points' => '20',
        'when' => 'Sat 14 Nov, 09:00',
        'place' => 'Mwanza Secondary School hall',
        'status' => 'Your place is confirmed',
        'change' => 'was postponed',
        'update' => 'Programme and venue map',
        'date' => '30 Nov 2026',
        'message' => 'Tax Club week starts on Monday. Visit your club patron for details.',
    ];

    /**
     * @return class-string<TaxClubNotification>|null
     */
    public static function find(string $key): ?string
    {
        foreach (self::NOTIFICATIONS as $class) {
            if ($class::key() === $key) {
                return $class;
            }
        }

        return null;
    }

    /**
     * The built-in wording in a language, with placeholders.
     *
     * @param  class-string<TaxClubNotification>  $class
     */
    public static function defaultText(string $class, string $locale): string
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return (new \ReflectionClass($class))->newInstanceWithoutConstructor()->message();
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * Placeholders a template may not drop: codes must reach the user.
     *
     * @param  class-string<TaxClubNotification>  $class
     * @return list<string>
     */
    public static function mandatoryPlaceholders(string $class): array
    {
        return $class::sensitive() ? ['code'] : [];
    }

    public static function preview(string $text): string
    {
        return TaxClubNotification::fill($text, self::SAMPLES);
    }
}
