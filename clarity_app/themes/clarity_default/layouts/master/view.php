<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?= htmlspecialchars($business_name ?? 'ClarityStack Photography') ?></title>
    
    <link rel="stylesheet" href="[theme-url]/css/style.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Meie+Script&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 
                        brand: { 
                            teal: '#0e8966', 
                            cyan: '#94e5e5', 
                            salmon: '#fa9680', 
                            dark: '#1a202c' 
                        } 
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased overflow-x-hidden flex flex-col min-h-screen">

    <div id="menu-overlay" onclick="toggleMenu()" class="fixed inset-0 bg-brand-dark/50 backdrop-blur-sm z-[60] hidden transition-opacity opacity-0"></div>
    
    <div id="side-menu" class="fixed top-0 left-0 h-full w-full md:w-1/2 bg-white z-[70] transform -translate-x-full menu-slide shadow-2xl flex flex-col justify-center px-12 border-r-4 border-brand-teal">
        <button onclick="toggleMenu()" class="absolute top-8 left-8 text-brand-dark hover:text-brand-salmon transition-colors" aria-label="Close Menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
        
        <nav class="flex flex-col space-y-8">
            <a href="/" onclick="toggleMenu()" class="text-3xl md:text-5xl font-black uppercase tracking-tighter hover:text-brand-teal transition-colors">Home</a>
            <a href="/#portfolio" onclick="toggleMenu()" class="text-3xl md:text-5xl font-black uppercase tracking-tighter hover:text-brand-teal transition-colors">Portfolio</a>
            <a href="/#booking" onclick="toggleMenu()" class="text-3xl md:text-5xl font-black uppercase tracking-tighter hover:text-brand-salmon transition-colors">Booking</a>
            <a href="/#contact" onclick="toggleMenu()" class="text-3xl md:text-5xl font-black uppercase tracking-tighter hover:text-brand-cyan transition-colors">Contact</a>
        </nav>

        <div class="mt-20">
            <?php if (!empty($contact_address)): ?>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Studio Location</p>
                <p class="text-sm font-medium"><?= nl2br(htmlspecialchars($contact_address)) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <header class="fixed w-full top-0 z-50 glass border-b border-gray-100 shadow-sm transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="flex justify-between items-center h-24">
                
                <button onclick="toggleMenu()" class="group flex flex-col justify-center items-start gap-1.5 w-12 h-12 p-2 border border-transparent hover:border-gray-200 transition-all rounded-none focus:outline-none" aria-label="Open Menu">
                    <span class="h-0.5 w-full bg-gray-900 group-hover:bg-brand-teal transition-colors duration-300"></span>
                    <span class="h-0.5 w-3/4 bg-gray-900 group-hover:bg-brand-teal transition-colors duration-300"></span>
                    <span class="h-0.5 w-1/2 bg-gray-900 group-hover:bg-brand-teal transition-colors duration-300"></span>
                </button>

                <a href="/" class="absolute left-1/2 transform -translate-x-1/2 h-24 py-1 flex items-center overflow-hidden">
                   <svg class="h-full w-auto" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">
                      <defs>
                        <path id="topArch" d="M 50,250 A 200,200 0 0,1 450,250" fill="none"/>
                        <path id="bottomArch" d="M 110,270 A 140,140 0 0,0 390,270" fill="none"/>
                        <g id="apertureIcon">
                            <circle cx="0" cy="0" r="20" fill="none" stroke="black" stroke-width="3"/>
                            <path d="M -5,-18 L 5,0 L 18,10" stroke="black" stroke-width="2" fill="none" transform="rotate(0)"/>
                            <path d="M -5,-18 L 5,0 L 18,10" stroke="black" stroke-width="2" fill="none" transform="rotate(60)"/>
                            <path d="M -5,-18 L 5,0 L 18,10" stroke="black" stroke-width="2" fill="none" transform="rotate(120)"/>
                            <path d="M -5,-18 L 5,0 L 18,10" stroke="black" stroke-width="2" fill="none" transform="rotate(180)"/>
                            <path d="M -5,-18 L 5,0 L 18,10" stroke="black" stroke-width="2" fill="none" transform="rotate(240)"/>
                            <path d="M -5,-18 L 5,0 L 18,10" stroke="black" stroke-width="2" fill="none" transform="rotate(300)"/>
                        </g>
                      </defs>
                      <text class="serif-text" font-size="28" letter-spacing="4">
                        <textPath href="#topArch" startOffset="50%" text-anchor="middle" side="left">
                            <?= strtoupper(htmlspecialchars($business_name ?? 'CLARITY PHOTO')) ?>
                        </textPath>
                      </text>
                      <text x="185" y="290" class="serif-text" font-size="140" text-anchor="middle"><?= substr($business_name ?? 'C', 0, 1) ?></text>
                      <line x1="250" y1="170" x2="250" y2="330" class="lines" />
                      <text x="315" y="290" class="serif-text" font-size="140" text-anchor="middle"><?= substr(strpos($business_name ?? ' ', ' ') !== false ? substr($business_name, strpos($business_name, ' ') + 1) : 'S', 0, 1) ?></text>
                      <use href="#apertureIcon" x="80" y="300" />
                      <use href="#apertureIcon" x="420" y="300" />
                      <text class="script-text" font-size="55">
                        <textPath href="#bottomArch" startOffset="50%" text-anchor="middle">Fine Art Portraiture</textPath>
                      </text>
                    </svg>
                </a>

                <button onclick="toggleModal('authModal')" class="flex items-center justify-center w-12 h-12 border border-transparent hover:border-gray-200 hover:bg-gray-50 text-brand-dark hover:text-brand-teal transition-all rounded-none group" title="Client Portal Login">
                    <i class="fa-regular fa-user text-xl"></i>
                </button>
            </div>
        </div>
    </header>

    <main class="pt-24 flex-grow">
        <?= $body ?>
    </main>

    <footer class="bg-brand-salmon text-white py-16 mt-auto">
        <div class="max-w-7xl mx-auto px-4 flex flex-col items-center text-center">
            
            <div class="flex space-x-8 mb-8">
                <?php if (!empty($social_instagram)): ?>
                <a href="<?= htmlspecialchars($social_instagram) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-brand-dark transition-transform hover:-translate-y-1">
                    <i class="fa-brands fa-instagram text-2xl"></i>
                </a>
                <?php endif; ?>

                <?php if (!empty($social_twitter)): ?>
                <a href="<?= htmlspecialchars($social_twitter) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-brand-dark transition-transform hover:-translate-y-1">
                    <i class="fa-brands fa-twitter text-2xl"></i>
                </a>
                <?php endif; ?>

                <?php if (!empty($social_facebook)): ?>
                <a href="<?= htmlspecialchars($social_facebook) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-brand-dark transition-transform hover:-translate-y-1">
                    <i class="fa-brands fa-facebook text-2xl"></i>
                </a>
                <?php endif; ?>

                <?php if (!empty($support_email)): ?>
                <a href="mailto:<?= htmlspecialchars($support_email) ?>" class="hover:text-brand-dark transition-transform hover:-translate-y-1">
                    <i class="fa-solid fa-envelope text-2xl"></i>
                </a>
                <?php endif; ?>
            </div>

            <div class="mb-6">
                <h3 class="font-serif text-2xl tracking-widest text-brand-dark">
                    <?= strtoupper(htmlspecialchars($business_name ?? 'CLARITYSTACK')) ?>
                </h3>
                
                <?php if(!empty($footer_locations)): ?>
                    <p class="text-white/80 text-sm uppercase tracking-wide mt-2 flex flex-wrap justify-center gap-2">
                        <?= $footer_locations ?> 
                        </p>
                <?php else: ?>
                    <p class="text-white/80 text-sm uppercase tracking-wide mt-2 flex flex-wrap justify-center gap-2">
                        <span>Washington DC</span> <span class="hidden md:block text-brand-dark">•</span>
                        <span>Virginia</span> <span class="hidden md:block text-brand-dark">•</span>
                        <span>Maryland</span>
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="border-t border-white/20 w-full max-w-xs my-6"></div>

            <div class="flex flex-col md:flex-row items-center gap-4 text-xs font-bold uppercase tracking-widest text-white/80">
                <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($business_name ?? 'ClarityStack') ?>.</p>
                <span class="hidden md:block text-brand-dark">•</span>
                <button onclick="toggleModal('termsModal')" class="hover:text-brand-dark hover:underline">Terms & Conditions</button>
                <span class="hidden md:block text-brand-dark">•</span>
                <a href="/admin/login" class="hover:text-brand-dark hover:underline">Admin</a>
            </div>
        </div>
    </footer>

    <div id="authModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-brand-dark/90 backdrop-blur-sm" onclick="toggleModal('authModal')"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white p-8 shadow-2xl border-t-4 border-brand-salmon rounded-none">
            <div class="text-center mb-6">
                <i class="fa-solid fa-wand-magic-sparkles text-3xl text-brand-salmon mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-900 uppercase">Client Access</h3>
                <p class="text-sm text-gray-500 mt-2">Enter your email. We'll send you a secure magic link to view your gallery.</p>
            </div>
            <form onsubmit="sendMagicLink(event)">
                <input type="email" id="magicEmail" placeholder="client@example.com" class="w-full bg-gray-50 border border-gray-300 p-4 text-center font-bold text-gray-900 focus:outline-none focus:border-brand-teal rounded-none mb-4" required>
                <button type="submit" id="magicBtn" class="w-full bg-brand-teal text-white font-bold py-4 uppercase tracking-widest hover:bg-brand-dark transition-colors rounded-none">
                    Send Magic Link
                </button>
            </form>
            <div id="magicMessage" class="hidden mt-4 p-4 bg-green-50 text-green-800 text-sm text-center border border-green-200 rounded-none"></div>
            <div id="magicError" class="hidden mt-4 p-4 bg-red-50 text-red-800 text-sm text-center border border-red-200 rounded-none"></div>
        </div>
    </div>

    <div id="termsModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-brand-dark/90 backdrop-blur-sm" onclick="toggleModal('termsModal')"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white shadow-2xl rounded-none max-h-[80vh] overflow-y-auto">
            <div class="bg-brand-salmon p-6 text-white">
                <h3 class="text-xl font-bold uppercase tracking-widest">Terms & Conditions</h3>
            </div>
            <div class="p-8 space-y-6 text-sm text-gray-600 leading-relaxed">
                <p><strong>1. Copyright Ownership:</strong> The Photographer retains the entire copyright in the Photographs and Works at all times.</p>
                <p><strong>2. Personal Use:</strong> The Client is granted a personal-use license. Commercial use is prohibited without permission.</p>
                <div class="pt-4 border-t border-gray-200">
                    <button onclick="toggleModal('termsModal')" class="w-full bg-gray-900 text-white font-bold py-3 uppercase hover:bg-brand-teal transition-colors rounded-none">
                        I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleModal(modalId) {
            const modal = document.getElementById(modalId);
            const body = document.body;
            
            if (modal.classList.contains('hidden')) {
                modal.classList.remove('hidden');
                body.classList.add('modal-open');
            } else {
                modal.classList.add('hidden');
                body.classList.remove('modal-open');
            }
        }

        function toggleMenu() {
            const menu = document.getElementById('side-menu');
            const overlay = document.getElementById('menu-overlay');
            const body = document.body;
            
            if (overlay.classList.contains('hidden')) {
                overlay.classList.remove('hidden');
                body.classList.add('modal-open');
                setTimeout(() => {
                    overlay.classList.remove('opacity-0');
                    menu.classList.remove('-translate-x-full');
                }, 10);
            } else {
                menu.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0');
                body.classList.remove('modal-open');
                setTimeout(() => {
                    overlay.classList.add('hidden');
                }, 400);
            }
        }

        // Logic to communicate with the AuthController API
        async function sendMagicLink(e) {
            e.preventDefault();
            const email = document.getElementById('magicEmail').value;
            const btn = document.getElementById('magicBtn');
            const msg = document.getElementById('magicMessage');
            const err = document.getElementById('magicError');

            btn.innerText = "Sending...";
            btn.disabled = true;
            msg.classList.add('hidden');
            err.classList.add('hidden');

            try {
                // This endpoint must match api/controllers/AuthController.php -> requestLink()
                const response = await fetch('/api/auth/magic-link', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });

                if (response.ok) {
                    btn.innerText = "Sent";
                    msg.innerHTML = '<i class="fa-solid fa-check-circle mr-2"></i> Link sent! Check your email.';
                    msg.classList.remove('hidden');
                } else {
                    const data = await response.json();
                    throw new Error(data.error || "User not found");
                }
            } catch (error) {
                btn.innerText = "Try Again";
                btn.disabled = false;
                err.innerText = error.message;
                err.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>
