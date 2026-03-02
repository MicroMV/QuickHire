<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>QuickHire</title>

    {{-- If your CSS is in public/css --}}
    <link rel="stylesheet" href="{{ asset('css/landingPage.css') }}">

    {{-- Fonts (optional but matches the bold/handwritten feel) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Kalam:wght@700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="topbar">
        <div class="topbar__inner">
            <a href="/" class="brand">
                <img src="{{ asset('images/quickhire-logo.png') }}" alt="QuickHire" class="brand__logo">
            </a>

            <div class="topbar__actions">
                <a class="btn btn--outline" href="/login">Log in</a>
                <a class="btn btn--primary" href="/register">Register</a>
            </div>
        </div>
    </header>

    <main class="hero">
        <h1 class="hero__title">
            Find your desire,<br>
            get hired with <span class="hero__brand">QuickHire</span>
        </h1>

        <p class="hero__subtitle">
            A web-based job recruitment platform designed to help job seekers quickly find
            suitable employment and participate in interviews online.
        </p>

        <a class="btn btn--cta" href="/register">Apply Now!</a>
    </main>
</body>
</html>