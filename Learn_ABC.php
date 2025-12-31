<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cosmic ABC: Alphabet Mission</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka+One&display=swap');

        body {
            margin: 0;
            overflow: hidden;
            background: #020617;
            font-family: 'Fredoka One', cursive;
            color: white;
            user-select: none;
            touch-action: none;
        }

        .stars-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: radial-gradient(circle at center, #0f172a 0%, #020617 100%);
        }

        .star {
            position: absolute;
            background: white;
            border-radius: 50%;
            opacity: 0.5;
            animation: twinkle var(--duration) infinite ease-in-out;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.2; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.3); }
        }

        .letter-star {
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 1.5rem;
            touch-action: none;
        }

        /* The active letter glows and pulses */
        .letter-star.active {
            filter: drop-shadow(0 0 25px currentColor);
            animation: pulse 1s infinite ease-in-out;
            z-index: 10;
            transform: scale(1.3);
            border: 4px solid rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.15);
        }

        .letter-star.completed {
            opacity: 0.3;
            filter: grayscale(1);
            transform: scale(0.85);
            cursor: default;
        }

        .letter-star.locked {
            opacity: 0.05;
            filter: blur(3px);
            cursor: default;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1.25); filter: drop-shadow(0 0 15px currentColor); }
            50% { transform: scale(1.4); filter: drop-shadow(0 0 40px currentColor); }
        }

        .cosmic-particle {
            position: absolute;
            pointer-events: none;
            border-radius: 50%;
            z-index: 100;
        }

        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(15px);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .shake {
            animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both;
        }

        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-3px, 0, 0); }
            40%, 60% { transform: translate3d(3px, 0, 0); }
        }
    </style>
</head>
<body class="flex flex-col items-center min-h-screen">

    <div class="stars-container" id="stars"></div>

    <header class="w-full p-4 flex justify-between items-center glass-panel sticky top-0 z-50">
        <h1 class="text-xl md:text-2xl font-bold text-blue-400">COSMIC ABC</h1>
        <div class="flex items-center gap-4">
            <div id="target-container" class="flex items-center gap-3">
                <span class="text-blue-200 text-sm">TARGET:</span>
                <span id="target-letter" class="text-5xl font-black text-white transition-all duration-300">A</span>
            </div>
            <button id="restart-btn" class="hidden px-6 py-2 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-400 hover:to-emerald-500 text-white rounded-full text-lg font-bold shadow-[0_0_20px_rgba(16,185,129,0.4)] transition-all active:scale-95">
                RESTART 🔄
            </button>
        </div>
    </header>

    <main id="alphabet-grid" class="flex-1 w-full max-w-5xl p-4 pb-24 md:pb-4 grid grid-cols-4 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-9 gap-3 md:gap-6 items-center justify-items-center overflow-y-auto">
        <!-- Letters generated here -->
    </main>

    <div id="start-overlay" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/95 backdrop-blur-xl">
        <div class="text-center p-12 glass-panel rounded-[4rem] border border-blue-500/30">
            <div class="text-8xl mb-6">👧</div>
            <h2 class="text-5xl font-black text-white mb-6">ABC MISSION</h2>
            <p class="text-blue-200 mb-8 text-xl">Tap the glowing letters!</p>
            <button id="start-btn" class="px-16 py-6 bg-blue-600 hover:bg-blue-500 rounded-full text-3xl font-bold shadow-[0_0_50px_rgba(37,99,235,0.5)] transition-all active:scale-90">
                START!
            </button>
        </div>
    </div>

    <script>
        const letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("");
        const colors = ['#60a5fa', '#f87171', '#4ade80', '#fbbf24', '#a78bfa', '#f472b6', '#22d3ee', '#fb923c', '#818cf8', '#94a3b8', '#ef4444', '#c084fc', '#38bdf8', '#4ade80', '#fbbf24', '#f87171', '#fcd34d', '#94a3b8', '#6366f1', '#fb923c', '#8b5cf6', '#ec4899', '#eab308', '#14b8a6', '#60a5fa', '#f87171'];

        let currentIndex = 0;
        let isSpeaking = false;
        let femaleVoice = null;

        const grid = document.getElementById('alphabet-grid');
        const targetDisplay = document.getElementById('target-letter');
        const targetContainer = document.getElementById('target-container');
        const restartBtn = document.getElementById('restart-btn');
        const startBtn = document.getElementById('start-btn');
        const startOverlay = document.getElementById('start-overlay');

        function loadVoices() {
            const voices = window.speechSynthesis.getVoices();
            femaleVoice = voices.find(v => 
                v.lang.startsWith('en') && 
                (v.name.includes('Female') || v.name.includes('Girl') || v.name.includes('Google UK English Female') || v.name.includes('Samantha'))
            ) || voices.find(v => v.lang.startsWith('en'));
        }

        window.speechSynthesis.onvoiceschanged = loadVoices;

        function createStars() {
            const container = document.getElementById('stars');
            container.innerHTML = '';
            for (let i = 0; i < 80; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                const size = Math.random() * 2 + 1;
                star.style.width = size + 'px';
                star.style.height = size + 'px';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.setProperty('--duration', (Math.random() * 2 + 2) + 's');
                container.appendChild(star);
            }
        }

        function renderLetters() {
            grid.innerHTML = '';
            letters.forEach((char, index) => {
                const btn = document.createElement('div');
                btn.className = 'letter-star w-14 h-14 md:w-16 md:h-16 text-2xl md:text-4xl font-bold glass-panel';
                btn.id = `letter-${index}`;
                btn.innerText = char;
                btn.style.color = colors[index];
                
                btn.onpointerdown = (e) => {
                    e.preventDefault();
                    handleInteraction(index, e.clientX, e.clientY);
                };
                
                grid.appendChild(btn);
            });
            updateLetterStates();
        }

        function updateLetterStates() {
            letters.forEach((_, index) => {
                const el = document.getElementById(`letter-${index}`);
                if (!el) return;
                el.classList.remove('active', 'locked', 'completed', 'shake');
                if (index === currentIndex) {
                    el.classList.add('active');
                    targetDisplay.innerText = letters[index];
                    targetDisplay.style.color = colors[index];
                } else if (index < currentIndex) {
                    el.classList.add('completed');
                } else {
                    el.classList.add('locked');
                }
            });
        }

        function speakImmediate(letter) {
            if (isSpeaking) {
                window.speechSynthesis.cancel();
            }
            
            isSpeaking = true;
            const ut = new SpeechSynthesisUtterance(letter);
            
            if (!femaleVoice) loadVoices();
            if (femaleVoice) ut.voice = femaleVoice;
            
            ut.pitch = 1.2;
            ut.rate = 1.0;
            
            ut.onend = () => {
                isSpeaking = false;
                advanceMission();
            };

            window.speechSynthesis.speak(ut);
        }

        function advanceMission() {
            if (currentIndex < letters.length - 1) {
                currentIndex++;
                updateLetterStates();
            } else if (currentIndex === letters.length - 1) {
                // Just finished Z
                currentIndex++;
                targetContainer.classList.add('hidden');
                restartBtn.classList.remove('hidden');
                updateLetterStates();
                
                // Final celebration sound
                const victory = new SpeechSynthesisUtterance("Mission Complete! Great job!");
                if (femaleVoice) victory.voice = femaleVoice;
                window.speechSynthesis.speak(victory);
            }
        }

        function restartGame() {
            currentIndex = 0;
            targetContainer.classList.remove('hidden');
            restartBtn.classList.add('hidden');
            renderLetters();
            
            // Effect
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 bg-white/20 backdrop-blur-sm z-[200] pointer-events-none';
            document.body.appendChild(overlay);
            overlay.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 500 }).onfinish = () => overlay.remove();
        }

        function createExplosion(x, y, color) {
            for (let i = 0; i < 30; i++) {
                const p = document.createElement('div');
                p.className = 'cosmic-particle';
                p.style.left = x + 'px';
                p.style.top = y + 'px';
                p.style.width = Math.random() * 12 + 6 + 'px';
                p.style.height = p.style.width;
                p.style.backgroundColor = color;
                p.style.boxShadow = `0 0 20px ${color}`;
                
                const angle = Math.random() * Math.PI * 2;
                const velocity = Math.random() * 300 + 150;
                const tx = Math.cos(angle) * velocity;
                const ty = Math.sin(angle) * velocity;

                document.body.appendChild(p);
                p.animate([
                    { transform: 'translate(0, 0) scale(1) rotate(0deg)', opacity: 1 },
                    { transform: `translate(${tx}px, ${ty}px) scale(0) rotate(360deg)`, opacity: 0 }
                ], { duration: 800, easing: 'ease-out' }).onfinish = () => p.remove();
            }
        }

        function handleInteraction(index, x, y) {
            const el = document.getElementById(`letter-${index}`);
            if (!el) return;

            if (index === currentIndex) {
                createExplosion(x || window.innerWidth/2, y || window.innerHeight/2, colors[index]);
                speakImmediate(letters[index]);
            } else if (index > currentIndex) {
                el.classList.add('shake');
                setTimeout(() => el.classList.remove('shake'), 400);
            }
        }

        startBtn.onpointerdown = (e) => {
            e.preventDefault();
            loadVoices();
            startOverlay.style.opacity = '0';
            setTimeout(() => {
                startOverlay.remove();
                renderLetters();
            }, 300);
        };

        restartBtn.onpointerdown = (e) => {
            e.preventDefault();
            restartGame();
        };

        window.onload = () => {
            createStars();
            loadVoices();
        };
    </script>
</body>
</html>