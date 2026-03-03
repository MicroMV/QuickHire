<?php
namespace Rongie\QuickHire\Core;

class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (!Session::get('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return (string) Session::get('csrf_token');
    }

    public static function verify(?string $token): bool
    {
        Session::start();
        $sessionToken = Session::get('csrf_token');
        return is_string($token) && is_string($sessionToken) && hash_equals($sessionToken, $token);
    }
}