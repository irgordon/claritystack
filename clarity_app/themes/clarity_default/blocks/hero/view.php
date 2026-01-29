<?php
// Default fallbacks to prevent errors if props are missing
$title = $title ?? 'Welcome to Our Studio';
$subtitle = $subtitle ?? 'Photography & Creative Direction';
$bgImage = $bg_image ?? 'https://images.unsplash.com/photo-1452587925148-ce544e77e70d?auto=format&fit=crop&w=1920&q=80';
?>

<div class="relative bg-gray-900 overflow-hidden">
    <div class="absolute inset-0">
        <img class="w-full h-full object-cover opacity-40" src="<?= htmlspecialchars($bgImage) ?>" alt="">
        <div class="absolute inset-0 bg-gray-900 mix-blend-multiply" aria-hidden="true"></div>
    </div>

    <div class="relative max-w-7xl mx-auto py-24 px-4 sm:py-32 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl mb-6">
            <?= htmlspecialchars($title) ?>
        </h1>
        <p class="mt-6 text-xl text-gray-300 max-w-3xl mx-auto">
            <?= htmlspecialchars($subtitle) ?>
        </p>
        
        <div class="mt-10 max-w-sm mx-auto sm:max-w-none sm:flex sm:justify-center">
            <a href="/portal" class="flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-blue-700 bg-white hover:bg-gray-50 md:py-4 md:text-lg md:px-10">
                Client Portal
            </a>
        </div>
    </div>
</div>
