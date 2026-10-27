<?php

/*
|--------------------------------------------------------------------------
| Tax Club programme settings
|--------------------------------------------------------------------------
*/

return [

    /*
    | Content and quizzes must be approved by someone other than the person
    | who submitted them (LRN-REQ02, GAM-REQ03). Turn off only for a pilot
    | with a single content officer.
    */
    'four_eyes_review' => env('FOUR_EYES_REVIEW', true),

    /*
    | The mobile app. Builds older than min_version are asked to update;
    | store links appear once the app is published.
    */
    'mobile' => [
        'min_version' => env('MOBILE_MIN_VERSION', '1.0.0'),
        'latest_version' => env('MOBILE_LATEST_VERSION', '1.0.0'),
        'android_url' => env('MOBILE_ANDROID_URL'),
        'ios_url' => env('MOBILE_IOS_URL'),
    ],

];
