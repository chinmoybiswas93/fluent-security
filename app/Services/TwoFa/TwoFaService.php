<?php

namespace FluentAuth\App\Services\TwoFa;

use FluentAuth\App\Helpers\Helper;

/**
 * Registry of second factor methods, and the rule for picking one.
 */
class TwoFaService
{
    private static $methods = null;

    /**
     * Registered methods, keyed by method key.
     *
     * Order is significant: the dispatcher asks for the first one that fits, so a
     * stronger factor registered ahead of a weaker one wins.
     *
     * @return BaseTwoFaMethod[]
     */
    public static function getMethods()
    {
        if (self::$methods !== null) {
            return self::$methods;
        }

        $methods = [];

        $registered = apply_filters('fluent_auth/2fa_methods', [
            new EmailTwoFaMethod()
        ]);

        foreach ($registered as $method) {
            if ($method instanceof BaseTwoFaMethod) {
                $methods[$method->getKey()] = $method;
            }
        }

        self::$methods = $methods;

        return self::$methods;
    }

    /**
     * Resolves the method that owns a pending row, by its use_type.
     *
     * A row raised as a challenge carries the method's challenge key rather than its
     * own key, so both have to resolve back to the same method.
     *
     * @param $useType string
     * @return BaseTwoFaMethod|null
     */
    public static function getMethodByUseType($useType)
    {
        foreach (self::getMethods() as $method) {
            if ($useType === $method->getKey() || $useType === $method->getChallengeKey()) {
                return $method;
            }
        }

        return null;
    }

    /**
     * Every use_type that belongs to a second factor, for querying the hashes table.
     *
     * @return array
     */
    public static function getAllUseTypes()
    {
        $useTypes = [];

        foreach (self::getMethods() as $method) {
            $useTypes[] = $method->getKey();
            $useTypes[] = $method->getChallengeKey();
        }

        return array_values(array_unique($useTypes));
    }

    /**
     * The method this user still owes, given what the first step already proved.
     *
     * A method proving the same factor as the first step is skipped - an emailed code
     * after a magic link is the same mailbox twice, so it adds nothing. Anything proving
     * a different factor is still required however the user got here, which is what
     * keeps magic login from becoming a way around an authenticator app.
     *
     * @param $user \WP_User
     * @param $satisfiedFactors array|null
     * @param $challengeRequired bool
     * @return BaseTwoFaMethod|null
     */
    public static function getRequiredMethod($user, $satisfiedFactors = null, $challengeRequired = false)
    {
        if (!$user instanceof \WP_User) {
            return null;
        }

        if ($satisfiedFactors === null) {
            $satisfiedFactors = Helper::getSatisfiedFactors();
        }

        $fallback = null;

        foreach (self::getMethods() as $method) {
            if (in_array($method->getSatisfiedFactor(), (array)$satisfiedFactors, true)) {
                continue;
            }

            if ($method->isAvailableForUser($user)) {
                return $method;
            }

            /*
             * An account under attack is challenged even where the method is switched
             * off for its role - but only with a method that proves something the first
             * step did not. Someone who arrived by magic link has already shown they
             * hold the mailbox, which is the very thing the challenge exists to ask for.
             */
            if ($challengeRequired && $fallback === null) {
                $fallback = $method;
            }
        }

        return $fallback;
    }

    /**
     * @return void
     */
    public static function resetMethods()
    {
        self::$methods = null;
    }
}
