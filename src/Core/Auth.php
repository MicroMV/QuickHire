<?php
namespace Rongie\QuickHire\Core;

class Auth
{
    public static function requireLogin(): void
    {
        Session::start();
        if (!Session::get('user_id')) {
            header("Location: /QuickHire/Public/index.php?open=login");
            exit;
        }
    }

    public static function userId(): int
    {
        return (int) Session::get('user_id');
    }

    public static function role(): string
    {
        return (string) Session::get('role');
    }
}