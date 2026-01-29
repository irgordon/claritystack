<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="[theme-url]/css/style.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 flex flex-col min-h-screen">

    <nav class="bg-white border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="/" class="font-bold text-xl tracking-tight text-blue-600">
                            CLARITY<span class="text-gray-900">STACK</span>
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-8">
                    <a href="/" class="text-gray-500 hover:text-gray-900">Home</a>
                    <a href="/portal" class="bg-gray-900 text-white px-4 py-2 rounded hover:bg-black transition">
                        Client Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow">
        <?= $body ?>
    </main>

    <footer class="bg-gray-900 text-white border-t border-gray-800 mt-auto">
        <div class="max-w-7xl mx-auto py-12 px-4 overflow-hidden sm:px-6 lg:px-8">
            <div class="text-center">
                <p class="text-base text-gray-400">
                    &copy; <?= date('Y') ?> ClarityStack. All rights reserved.
                </p>
                <div class="mt-4 flex justify-center space-x-6 text-sm text-gray-500">
                    <a href="#" class="hover:text-gray-300">Privacy</a>
                    <span>&bull;</span>
                    <a href="#" class="hover:text-gray-300">Terms</a>
                    <span>&bull;</span>
                    <a href="/admin/login" class="hover:text-gray-300">Admin</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
