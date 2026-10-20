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

];
