<?php

namespace App\Livewire\Member;

use App\Notifications\PasswordChanged;
use App\Support\MemberAccounts;
use App\Support\MemberProfile;
use App\Support\MemberRegistration;
use App\Support\NotificationPreferences;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The member's own profile (PRO-REQ01/02) and alumni request (REG-REQ07).
 */
#[Layout('components.layouts.app')]
class Profile extends Component
{
    use WithFileUploads;

    public ?string $email = '';

    public ?string $gender = '';

    public string $preferred_locale = 'sw';

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public string $newPhone = '';

    public string $phoneCode = '';

    public bool $phoneCodeSent = false;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $statusMessage = null;

    /** @var array<string, array{sms: bool, inbox: bool}> */
    public array $preferences = [];

    public function mount(): void
    {
        $member = auth()->user();
        $this->email = $member->email;
        $this->gender = $member->gender;
        $this->preferred_locale = $member->preferredLocale();
        $this->preferences = NotificationPreferences::all($member);
    }

    public function savePreferences(): void
    {
        foreach ($this->preferences as $category => $channels) {
            NotificationPreferences::set(auth()->user(), $category, (bool) ($channels['sms'] ?? false), (bool) ($channels['inbox'] ?? false));
        }

        $this->statusMessage = __('Your notification choices are saved.');
    }

    public function saveDetails(): void
    {
        $member = auth()->user();
        $data = $this->validate(MemberProfile::rules($member));

        MemberProfile::update($member, $data);
        $this->statusMessage = __('Your details are saved.');
    }

    public function updatedPhoto(): void
    {
        $this->validate(['photo' => 'image|mimes:jpg,jpeg,png,webp|max:4096'], [], ['photo' => __('photo')]);

        try {
            MemberProfile::storePhoto(auth()->user(), $this->photo);
        } catch (InvalidArgumentException $exception) {
            $this->addError('photo', $exception->getMessage());

            return;
        }

        $this->reset('photo');
        $this->statusMessage = __('Your photo is updated. It appears on your membership card.');
    }

    public function removePhoto(): void
    {
        MemberProfile::removePhoto(auth()->user());
        $this->statusMessage = __('Your photo was removed.');
    }

    public function sendPhoneCode(): void
    {
        $this->newPhone = MemberRegistration::compactPhone($this->newPhone);
        $this->validate(['newPhone' => ['required', 'regex:'.MemberRegistration::PHONE_PATTERN]], MemberRegistration::messages() + [
            'newPhone.regex' => __('Enter a Tanzanian mobile number, for example 0712 345 678.'),
        ], ['newPhone' => __('new mobile number')]);

        try {
            MemberProfile::requestPhoneChange(auth()->user(), $this->newPhone, request()->ip());
        } catch (InvalidArgumentException $exception) {
            $this->addError('newPhone', $exception->getMessage());

            return;
        }

        $this->phoneCodeSent = true;
    }

    public function confirmPhone(): void
    {
        $this->validate(['phoneCode' => 'required|digits:6'], [], ['phoneCode' => __('code')]);

        try {
            $phone = MemberProfile::confirmPhoneChange(auth()->user(), $this->phoneCode);
        } catch (InvalidArgumentException $exception) {
            $this->addError('phoneCode', $exception->getMessage());

            return;
        }

        $this->reset('newPhone', 'phoneCode', 'phoneCodeSent');
        $this->statusMessage = __('Your mobile number is now :phone. Use it to sign in.', ['phone' => $phone]);
    }

    public function cancelPhoneChange(): void
    {
        $this->reset('newPhone', 'phoneCode', 'phoneCodeSent');
    }

    public function changePassword(): void
    {
        $member = auth()->user();
        $this->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ]);

        if (! Hash::check($this->current_password, $member->password_hash)) {
            $this->addError('current_password', __('Your current password is not correct.'));

            return;
        }

        $member->forceFill(['password_hash' => Hash::make($this->password)])->save();
        $member->tokens()->delete();
        DB::table('sessions')->where('user_id', $member->user_id)->where('id', '!=', session()->getId())->delete();
        Notify::send($member, new PasswordChanged);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->statusMessage = __('Your password is changed. Other devices were signed out.');
    }

    public function requestAlumni(): void
    {
        try {
            MemberAccounts::requestAlumni(auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('alumni', $exception->getMessage());

            return;
        }

        $this->statusMessage = __('Request sent. Your patron or coordinator will confirm it.');
    }

    public function cancelAlumniRequest(): void
    {
        MemberAccounts::cancelAlumniRequest(auth()->user());
        $this->statusMessage = __('Alumni request cancelled.');
    }

    public function render(): mixed
    {
        $member = auth()->user()->refresh()->load('institution:institution_id,name,region');

        return view('livewire.member.profile', [
            'member' => $member,
            'history' => $member->statusHistory()->limit(10)->get(),
            'quizzes' => $member->quizAttempts()->count(),
        ])->title(__('My profile'));
    }
}
