<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Validation\ValidationException;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'nerd_name' => ['required', 'string', 'max:255'],
            'kennel' => ['required', 'string', 'max:255'],
            'shirt_size' => ['required', 'string', 'max:255'],
            'short_bus' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'phone' => ['required', 'string'],
        ])->validateWithBag('updateProfileInformation');
        $phone = $this->validatePhone($input['phone']);

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'nerd_name' => $input['nerd_name'],
                'kennel' => $input['kennel'],
                'shirt_size' => $input['shirt_size'],
                'short_bus' => $input['short_bus'],
                'email' => $input['email'],
                'phone' => $phone,
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }

    protected function validatePhone(string $phone): string
    {
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $parsed = $phoneUtil->parse($phone, 'US'); // use 'AUTO' or user country code
            if (!$phoneUtil->isValidNumber($parsed)) {
                throw new \Exception('Invalid phone number');
            }

            // You can normalize to E.164 format (e.g., +15555551212)
            return $phoneUtil->format($parsed, PhoneNumberFormat::E164);

        } catch (NumberParseException | \Exception $e) {
            throw ValidationException::withMessages([
                'phone' => ['The phone number is not valid.'],
            ]);
        }
    }
}
