<?php

namespace FluentAuth\App\Services\TwoFa;

/**
 * Contract for a second factor.
 *
 * An implementation owns only the proof itself: producing it, rendering its form and
 * checking it. Everything wrapped around it - the pending login row, the redirect
 * intent, the remember-me flag, the attempt cap and the final sign in - stays in
 * TwoFaHandler, so adding a method never means reimplementing the login flow.
 */
abstract class BaseTwoFaMethod
{
    /**
     * Recorded in fls_login_hashes.use_type, which is varchar(20).
     *
     * @return string
     */
    abstract public function getKey();

    /**
     * @return string
     */
    abstract public function getTitle();

    /**
     * What this method proves. See AuthFactor.
     *
     * @return string
     */
    abstract public function getSatisfiedFactor();

    /**
     * Whether this user can be challenged with this method right now - enabled for
     * their role, or enrolled in it.
     *
     * @param $user \WP_User
     * @return bool
     */
    abstract public function isAvailableForUser($user);

    /**
     * The use_type recorded when the challenge was raised because the account is under
     * attack rather than because the method is switched on. Methods that need no
     * separate marker just reuse their own key.
     *
     * @return string
     */
    public function getChallengeKey()
    {
        return $this->getKey();
    }

    /**
     * Builds the proof.
     *
     * Returns `columns` to merge into the pending login row (a hashed code, a WebAuthn
     * challenge, or nothing for a method that needs no server side state) and `secret`,
     * the plaintext to hand to dispatchChallenge. The secret is never persisted.
     *
     * @param $user \WP_User
     * @return array
     */
    public function prepareChallenge($user)
    {
        return [
            'columns' => [],
            'secret'  => null
        ];
    }

    /**
     * Runs once the pending row exists, for anything with a side effect - sending the
     * code email, for instance. A method whose proof already lives on the user's device
     * has nothing to do here.
     *
     * @param $user \WP_User
     * @param $challenge array as returned by prepareChallenge()
     * @param $context array login_hash, redirect_to and the row that was written
     * @return void
     */
    public function dispatchChallenge($user, $challenge, $context)
    {
    }

    /**
     * @param $data array
     * @return string
     */
    abstract public function renderForm($data);

    /**
     * Checks the submitted proof.
     *
     * Returning false is a wrong answer and counts against the attempt cap. A WP_Error
     * is a hard failure - malformed input, an unusable credential - and does not.
     *
     * @param $user \WP_User
     * @param $logHash object
     * @param $request array
     * @return bool|\WP_Error
     */
    abstract public function verifyProof($user, $logHash, $request);

    /**
     * Recorded on the auth log so an admin can see which factor was actually used.
     *
     * @return string
     */
    public function getLoginMedia()
    {
        return 'two_factor_' . $this->getKey();
    }
}
