<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cosmic Numbers: Space Mission</title>
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

        .number-star {
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 1.5rem;
            touch-action: none;
            will-change: transform, opacity;
        }

        .number-text {
            position: relative;
            z-index: 1;
        }

        .number-star.active {
            filter: drop-shadow(0 0 25px currentColor);
            animation: pulse 1s infinite ease-in-out;
            z-index: 10;
            transform: scale(1.3);
            border: 4px solid rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.15);
        }

        .number-star.completed {
            opacity: 0.3;
            filter: grayscale(1);
            transform: scale(0.85);
            cursor: default;
        }

        .number-star.locked {
            opacity: 0.05;
            filter: blur(3px);
            cursor: default;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1.25); filter: drop-shadow(0 0 15px currentColor); }
            50% { transform: scale(1.4); filter: drop-shadow(0 0 40px currentColor); }
        }

        /* Target Display Pop Animation */
        .target-pop {
            animation: target-hit 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes target-hit {
            0% { transform: scale(1); }
            50% { transform: scale(1.5) rotate(5deg); filter: brightness(1.5) drop-shadow(0 0 30px currentColor); }
            100% { transform: scale(1); }
        }

        .cosmic-particle {
            position: absolute;
            pointer-events: none;
            border-radius: 50%;
            z-index: 100;
        }

        .emoji-particle {
            position: absolute;
            pointer-events: none;
            z-index: 101;
            font-size: 1.5rem;
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

        .level-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.95));
            border: 2px solid rgba(96, 165, 250, 0.3);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4), 0 0 15px rgba(96, 165, 250, 0.2);
            position: relative;
            overflow: hidden;
            touch-action: manipulation;
            backdrop-filter: blur(10px);
        }

        .level-card:hover {
            transform: scale(1.08) translateY(-8px);
            border-color: #60a5fa;
            box-shadow: 0 8px 30px rgba(96, 165, 250, 0.5), 0 0 25px rgba(96, 165, 250, 0.4);
            background: linear-gradient(145deg, rgba(30, 41, 59, 1), rgba(15, 23, 42, 1));
        }

        .level-card:active {
            transform: scale(1.02) translateY(-3px);
        }

        .level-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            filter: drop-shadow(0 0 10px rgba(255,255,255,0.3));
        }

        .floating-anim {
            animation: float-slow 3s infinite ease-in-out;
        }

        @keyframes float-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
    </style>
</head>
<body class="flex flex-col items-center min-h-screen">

    <div class="stars-container" id="stars"></div>

    <header class="w-full p-4 flex justify-between items-center glass-panel sticky top-0 z-50">
        <div class="flex flex-col">
            <h1 class="text-xl md:text-2xl font-bold text-blue-400">COSMIC NUMBERS</h1>
            <span id="level-display" class="text-xs text-blue-300 uppercase tracking-widest">Select Level</span>
        </div>
        <div id="target-box" class="flex items-center gap-3 transition-transform duration-300">
            <span id="mission-icon-display" class="text-3xl filter drop-shadow-md">🚀</span>
            <span class="text-blue-200 text-sm">TARGET:</span>
            <span id="target-number" class="text-5xl font-black text-white transition-all duration-300">?</span>
        </div>
    </header>

    <main id="number-grid" class="flex-1 w-full max-w-5xl p-4 pb-24 md:pb-4 grid grid-cols-3 sm:grid-cols-5 gap-4 md:gap-8 items-center justify-items-center overflow-y-auto">
        <!-- Numbers generated here -->
    </main>

    <div id="selection-overlay" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/98 backdrop-blur-2xl">
        <div class="text-center p-6 w-full max-w-4xl h-full flex flex-col justify-center overflow-y-auto relative">
            <!-- Back Button -->
            <button id="back-to-kids-zone-btn" class="absolute top-4 left-4 md:top-6 md:left-6 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-400 hover:from-blue-500 hover:to-blue-300 rounded-full text-white font-bold shadow-lg transition-all active:scale-95 flex items-center gap-2 z-50">
                <span>←</span>
                <span>Back</span>
            </button>
            
            <div class="mb-10 floating-anim">
                <span class="text-7xl">🛰️</span>
                <h2 class="text-5xl md:text-7xl font-black text-white mt-4 bg-clip-text text-transparent bg-gradient-to-b from-white to-blue-500">MISSION LOG</h2>
                <p class="text-blue-300 text-lg md:text-xl mt-2">Choose a planet mission!</p>
            </div>
            
            <div id="level-grid" class="grid grid-cols-2 md:grid-cols-5 gap-4 md:gap-6 p-4">
                <!-- Level cards generated here -->
            </div>
        </div>
    </div>

    <!-- Level Confirmation Modal -->
    <div id="level-confirm-overlay" class="fixed inset-0 z-[105] hidden flex items-center justify-center bg-slate-950/95 backdrop-blur-xl">
        <div class="text-center p-8 md:p-12 glass-panel rounded-[2rem] md:rounded-[4rem] border border-blue-400/30 shadow-[0_0_100px_rgba(59,130,246,0.3)] max-w-md md:max-w-lg w-full mx-4">
            <div id="confirm-level-icon" class="text-6xl md:text-8xl mb-4 floating-anim">🚀</div>
            <h2 id="confirm-level-title" class="text-3xl md:text-5xl font-black text-white mb-3">MISSION READY</h2>
            <div id="confirm-level-info" class="text-blue-200 mb-6 text-lg md:text-xl space-y-2">
                <p id="confirm-level-range" class="font-bold">Numbers: <span id="confirm-range-text">1-10</span></p>
                <p id="confirm-level-planet" class="text-sm md:text-base opacity-80">Planet <span id="confirm-planet-num">1</span></p>
                <p class="text-sm md:text-base opacity-70 mt-4">Tap numbers in order from <span id="confirm-start-num">1</span> to <span id="confirm-end-num">10</span></p>
            </div>
            <div class="flex flex-col md:flex-row gap-4 justify-center">
                <button id="confirm-play-btn" class="px-8 md:px-12 py-4 md:py-5 bg-gradient-to-r from-blue-600 to-blue-400 hover:from-blue-500 hover:to-blue-300 rounded-full text-xl md:text-2xl font-bold shadow-2xl transition-all active:scale-95">
                    PLAY 🚀
                </button>
                <button id="confirm-cancel-btn" class="px-8 md:px-12 py-4 md:py-5 bg-gradient-to-r from-slate-600 to-slate-500 hover:from-slate-500 hover:to-slate-400 rounded-full text-xl md:text-2xl font-bold shadow-2xl transition-all active:scale-95">
                    CANCEL
                </button>
            </div>
        </div>
    </div>

    <div id="success-overlay" class="fixed inset-0 z-[110] hidden flex items-center justify-center bg-blue-950/90 backdrop-blur-md">
        <div class="text-center p-12 glass-panel rounded-[4rem] border border-blue-400/30 shadow-[0_0_100px_rgba(59,130,246,0.3)]">
            <div class="text-8xl mb-6">🏆</div>
            <h2 class="text-6xl font-black text-white mb-4">AWESOME!</h2>
            <p class="text-blue-200 mb-8 text-2xl">Mission Objective Reached!</p>
            <div class="flex flex-col gap-4">
                <button id="next-level-btn" class="px-16 py-6 bg-gradient-to-r from-blue-600 to-blue-400 hover:from-blue-500 hover:to-blue-300 rounded-full text-3xl font-bold shadow-2xl transition-all active:scale-95">
                    NEXT MISSION 🚀
                </button>
                <button id="back-home-btn" class="text-blue-400 hover:text-white underline font-bold text-lg mt-4">
                    Choose Another Planet
                </button>
            </div>
        </div>
    </div>

    <script>
        const grid = document.getElementById('number-grid');
        const targetDisplay = document.getElementById('target-number');
        const targetBox = document.getElementById('target-box');
        const missionIconDisplay = document.getElementById('mission-icon-display');
        const levelDisplay = document.getElementById('level-display');
        const selectionOverlay = document.getElementById('selection-overlay');
        const levelGrid = document.getElementById('level-grid');
        const successOverlay = document.getElementById('success-overlay');
        const nextLevelBtn = document.getElementById('next-level-btn');
        const backHomeBtn = document.getElementById('back-home-btn');
        const backToKidsZoneBtn = document.getElementById('back-to-kids-zone-btn');
        const levelConfirmOverlay = document.getElementById('level-confirm-overlay');
        const confirmPlayBtn = document.getElementById('confirm-play-btn');
        const confirmCancelBtn = document.getElementById('confirm-cancel-btn');
        
        let selectedLevelIndex = null;

        let currentNumbers = [];
        let currentIndex = 0;
        let isSpeaking = false;
        let femaleVoice = null;
        let currentRangeIndex = 0;
        let currentLevelIcon = '🚀';

        const ranges = [
            { start: 1, end: 10, icon: '🚀' },
            { start: 11, end: 20, icon: '🪐' },
            { start: 21, end: 30, icon: '🌑' },
            { start: 31, end: 40, icon: '🛸' },
            { start: 41, end: 50, icon: '🌍' },
            { start: 51, end: 60, icon: '🔭' },
            { start: 61, end: 70, icon: '🌌' },
            { start: 71, end: 80, icon: '🌞' },
            { start: 81, end: 90, icon: '☄️' },
            { start: 91, end: 100, icon: '👾' }
        ];

        const colors = ['#60a5fa', '#f87171', '#4ade80', '#fbbf24', '#a78bfa', '#f472b6', '#22d3ee', '#fb923c', '#818cf8', '#ef4444'];

        function loadVoices() {
            const voices = window.speechSynthesis.getVoices();
            femaleVoice = voices.find(v => 
                v.lang.startsWith('en') && 
                (v.name.includes('Female') || v.name.includes('Girl') || v.name.includes('Google UK English Female') || v.name.includes('Samantha') || v.name.includes('Zira'))
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

        function initLevelButtons() {
            levelGrid.innerHTML = '';
            ranges.forEach((range, idx) => {
                const card = document.createElement('div');
                card.className = 'level-card p-4 md:p-6 rounded-[1.5rem] md:rounded-[2rem] flex flex-col items-center justify-center cursor-pointer min-h-[120px] md:min-h-[140px]';
                card.innerHTML = `
                    <div class="level-icon text-2xl md:text-3xl">${range.icon}</div>
                    <span class="text-blue-400 text-[9px] md:text-[10px] tracking-widest font-bold mb-1 mt-1">PLANET ${idx + 1}</span>
                    <span class="text-xl md:text-2xl font-black text-white">${range.start}-${range.end}</span>
                `;
                card.onpointerdown = (e) => {
                    e.preventDefault();
                    showLevelConfirmation(idx);
                };
                levelGrid.appendChild(card);
            });
        }

        function showLevelConfirmation(rangeIdx) {
            selectedLevelIndex = rangeIdx;
            const range = ranges[rangeIdx];
            
            // Update confirmation modal with level info
            document.getElementById('confirm-level-icon').innerText = range.icon;
            document.getElementById('confirm-range-text').innerText = `${range.start}-${range.end}`;
            document.getElementById('confirm-planet-num').innerText = rangeIdx + 1;
            document.getElementById('confirm-start-num').innerText = range.start;
            document.getElementById('confirm-end-num').innerText = range.end;
            
            // Show confirmation modal
            levelConfirmOverlay.classList.remove('hidden');
        }

        function startLevel(rangeIdx) {
            currentRangeIndex = rangeIdx;
            const range = ranges[rangeIdx];
            currentLevelIcon = range.icon;
            missionIconDisplay.innerText = currentLevelIcon;
            currentNumbers = [];
            for (let i = range.start; i <= range.end; i++) {
                currentNumbers.push(i);
            }
            currentIndex = 0;
            selectionOverlay.classList.add('hidden');
            levelConfirmOverlay.classList.add('hidden');
            successOverlay.classList.add('hidden');
            levelDisplay.innerText = `${range.icon} MISSION: ${range.start} TO ${range.end}`;
            renderNumbers();
        }

        function renderNumbers() {
            grid.innerHTML = '';
            currentNumbers.forEach((num, index) => {
                const btn = document.createElement('div');
                btn.className = 'number-star w-20 h-20 md:w-24 md:h-24 text-3xl md:text-5xl font-bold glass-panel';
                btn.id = `num-${index}`;
                
                // Clean background with only the number text
                btn.innerHTML = `<span class="number-text">${num}</span>`;
                
                btn.style.color = colors[index % colors.length];
                btn.onpointerdown = (e) => {
                    e.preventDefault();
                    handleInteraction(index, e.clientX, e.clientY);
                };
                grid.appendChild(btn);
            });
            updateNumberStates();
        }

        function updateNumberStates() {
            currentNumbers.forEach((_, index) => {
                const el = document.getElementById(`num-${index}`);
                if (!el) return;
                el.classList.remove('active', 'locked', 'completed', 'shake');
                if (index === currentIndex) {
                    el.classList.add('active');
                    targetDisplay.innerText = currentNumbers[index];
                    targetDisplay.style.color = colors[index % colors.length];
                } else if (index < currentIndex) {
                    el.classList.add('completed');
                } else {
                    el.classList.add('locked');
                }
            });
        }

        function speakImmediate(text) {
            if (isSpeaking) { window.speechSynthesis.cancel(); }
            isSpeaking = true;
            const ut = new SpeechSynthesisUtterance(text);
            if (!femaleVoice) loadVoices();
            if (femaleVoice) ut.voice = femaleVoice;
            ut.pitch = 1.2;
            ut.rate = 1.0;
            ut.onend = () => {
                isSpeaking = false;
                if (currentIndex >= currentNumbers.length) { showLevelComplete(); }
            };
            window.speechSynthesis.speak(ut);
        }

        function handleInteraction(index, x, y) {
            const el = document.getElementById(`num-${index}`);
            if (!el) return;

            if (index === currentIndex) {
                targetBox.classList.remove('target-pop');
                void targetBox.offsetWidth;
                targetBox.classList.add('target-pop');

                createExplosion(x || window.innerWidth/2, y || window.innerHeight/2, colors[index % colors.length]);
                speakImmediate(currentNumbers[index].toString());
                currentIndex++;
                updateNumberStates();
            } else if (index > currentIndex) {
                el.classList.add('shake');
                setTimeout(() => el.classList.remove('shake'), 400);
            }
        }

        function createExplosion(x, y, color) {
            for (let i = 0; i < 20; i++) {
                const p = document.createElement('div');
                p.className = 'cosmic-particle';
                p.style.left = x + 'px';
                p.style.top = y + 'px';
                p.style.width = Math.random() * 10 + 5 + 'px';
                p.style.height = p.style.width;
                p.style.backgroundColor = color;
                p.style.boxShadow = `0 0 15px ${color}`;
                const angle = Math.random() * Math.PI * 2;
                const velocity = Math.random() * 300 + 100;
                const tx = Math.cos(angle) * velocity;
                const ty = Math.sin(angle) * velocity;
                document.body.appendChild(p);
                p.animate([
                    { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                    { transform: `translate(${tx}px, ${ty}px) scale(0)`, opacity: 0 }
                ], { duration: 800, easing: 'ease-out' }).onfinish = () => p.remove();
            }

            for (let i = 0; i < 8; i++) {
                const ep = document.createElement('div');
                ep.className = 'emoji-particle';
                ep.innerText = currentLevelIcon;
                ep.style.left = x + 'px';
                ep.style.top = y + 'px';
                const angle = Math.random() * Math.PI * 2;
                const velocity = Math.random() * 200 + 100;
                const tx = Math.cos(angle) * velocity;
                const ty = Math.sin(angle) * velocity;
                const rotation = Math.random() * 360;
                document.body.appendChild(ep);
                ep.animate([
                    { transform: 'translate(0, 0) scale(0.5) rotate(0deg)', opacity: 1 },
                    { transform: `translate(${tx}px, ${ty}px) scale(1.5) rotate(${rotation}deg)`, opacity: 0 }
                ], { duration: 1000, easing: 'ease-out' }).onfinish = () => ep.remove();
            }
        }

        function showLevelComplete() {
            successOverlay.classList.remove('hidden');
            // Say "congratulations" only
            const congrats = new SpeechSynthesisUtterance("Congratulations");
            if (femaleVoice) congrats.voice = femaleVoice;
            congrats.pitch = 1.2;
            congrats.rate = 1.0;
            window.speechSynthesis.speak(congrats);
        }

        nextLevelBtn.onpointerdown = (e) => {
            e.preventDefault();
            if (currentRangeIndex < ranges.length - 1) { startLevel(currentRangeIndex + 1); } 
            else { successOverlay.classList.add('hidden'); selectionOverlay.classList.remove('hidden'); }
        };

        backHomeBtn.onpointerdown = (e) => {
            e.preventDefault();
            successOverlay.classList.add('hidden');
            selectionOverlay.classList.remove('hidden');
        };

        // Back button to return to Kids Zone
        if (backToKidsZoneBtn) {
            backToKidsZoneBtn.onpointerdown = (e) => {
                e.preventDefault();
                // Go back to index page (Kids Zone)
                window.location.href = 'index.php';
            };
        }

        // Play button - start the selected level
        if (confirmPlayBtn) {
            confirmPlayBtn.onpointerdown = (e) => {
                e.preventDefault();
                if (selectedLevelIndex !== null) {
                    startLevel(selectedLevelIndex);
                    selectedLevelIndex = null;
                }
            };
        }

        // Cancel button - close modal and return to level selection
        if (confirmCancelBtn) {
            confirmCancelBtn.onpointerdown = (e) => {
                e.preventDefault();
                levelConfirmOverlay.classList.add('hidden');
                selectedLevelIndex = null;
                // Ensure selection overlay is visible
                selectionOverlay.classList.remove('hidden');
            };
        }

        window.onload = () => {
            createStars();
            loadVoices();
            initLevelButtons();
        };
    </script>
</body>
</html>