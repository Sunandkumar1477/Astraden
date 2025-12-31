<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Galactic Gardener - Deep Space Explorer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Plus+Jakarta+Sans:wght@300;400&display=swap');

        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #020205;
            color: #e0e7ff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            user-select: none;
            touch-action: none; /* Prevents default browser gestures like pull-to-refresh */
        }

        canvas { display: block; }

        .ui-screen {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 50;
            transition: all 1.5s cubic-bezier(0.4, 0, 0.2, 1);
            background: radial-gradient(circle at center, rgba(7, 11, 22, 0.95) 0%, #020205 100%);
        }

        .ui-hidden {
            opacity: 0;
            pointer-events: none;
            transform: scale(1.1);
        }

        .hud {
            position: absolute;
            inset: 0;
            pointer-events: none;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            opacity: 0;
            transition: opacity 2s ease;
            z-index: 40;
        }

        .hud-active { opacity: 1; }

        .glass {
            background: rgba(10, 15, 30, 0.4);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 2px;
            padding: 0.8rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }

        .btn-start {
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 0.5em;
            padding: 1rem 2.5rem;
            border: 1px solid rgba(129, 140, 248, 0.3);
            background: linear-gradient(180deg, rgba(129, 140, 248, 0.1) 0%, rgba(10, 10, 20, 0.5) 100%);
            transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            color: #fff;
            font-size: 0.8rem;
        }

        .btn-start:hover {
            border-color: #818cf8;
            box-shadow: 0 0 50px rgba(129, 140, 248, 0.3);
            letter-spacing: 0.6em;
            background: rgba(129, 140, 248, 0.2);
        }

        /* Mobile Fire Button */
        #mobile-fire-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 80px;
            height: 80px;
            background: rgba(99, 102, 241, 0.2);
            border: 2px solid rgba(99, 102, 241, 0.5);
            border-radius: 50%;
            display: none; /* Shown via JS if touch device */
            align-items: center;
            justify-content: center;
            z-index: 100;
            pointer-events: auto;
            backdrop-filter: blur(5px);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.3);
            active: scale(0.9);
        }

        #mobile-fire-btn:active {
            background: rgba(99, 102, 241, 0.5);
            transform: scale(0.9);
        }

        .compass {
            width: 70px;
            height: 70px;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 50%;
            position: relative;
            background: radial-gradient(circle, rgba(129, 140, 248, 0.05) 0%, transparent 70%);
        }

        .compass-needle {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 2px;
            height: 25px;
            background: linear-gradient(to top, transparent, #ef4444);
            transform-origin: bottom center;
            transform: translate(-50%, -100%);
            box-shadow: 0 0 10px #ef4444;
        }

        .health-needle {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 2px;
            height: 20px;
            background: linear-gradient(to top, transparent, #22c55e);
            transform-origin: bottom center;
            transform: translate(-50%, -100%);
            box-shadow: 0 0 10px #22c55e;
            opacity: 0;
        }

        .label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6366f1;
            font-weight: 700;
            margin-bottom: 2px;
        }

        #damage-flash {
            position: fixed;
            inset: 0;
            background: rgba(239, 68, 68, 0.15);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 60;
        }
    </style>
</head>
<body>

    <div id="damage-flash"></div>

    <div id="startScreen" class="ui-screen">
        <div class="absolute inset-0 opacity-20 pointer-events-none" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 50px 50px;"></div>
        <h1 class="text-4xl md:text-5xl font-bold tracking-[0.4em] mb-4 text-white font-['Orbitron'] text-center px-4">GALACTIC GARDENER</h1>
        <p class="text-indigo-300 tracking-[0.2em] mb-12 opacity-50 uppercase text-[9px] text-center px-4">Interstellar Defense Protocol</p>
        <button class="btn-start" onclick="beginMission()">INITIALIZE VOYAGE</button>
    </div>

    <div id="hud" class="hud">
        <div class="flex justify-between items-start">
            <div class="flex flex-col gap-3">
                <div class="glass flex flex-col gap-1">
                    <span class="label">Hull Integrity</span>
                    <div class="w-40 md:w-56 h-[4px] bg-gray-900 rounded-full overflow-hidden">
                        <div id="health-bar" class="h-full bg-green-500 transition-all duration-300" style="width: 100%"></div>
                    </div>
                </div>
                <div class="glass flex flex-col gap-1">
                    <span class="label">Propulsion Output</span>
                    <div class="w-40 md:w-56 h-[2px] bg-gray-900 rounded-full overflow-hidden">
                        <div id="fuel-bar" class="h-full bg-indigo-500 transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <div class="flex justify-between text-[8px] font-mono mt-0.5 opacity-40">
                        <span>REACTOR: NOMINAL</span>
                        <span id="fuel-txt">0%</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col items-end gap-2">
                <div class="glass text-right min-w-[120px] md:min-w-[160px]">
                    <span class="label">Stellar Score</span>
                    <div id="score-val" class="text-xl md:text-2xl font-['Orbitron'] text-indigo-400">00000</div>
                </div>
            </div>
        </div>

        <div class="flex justify-between items-end">
            <div class="glass">
                <div class="compass">
                    <div id="compass-needle" class="compass-needle"></div>
                    <div id="health-needle" class="health-needle"></div>
                </div>
                <div class="text-[8px] mt-2 text-center opacity-30 font-bold uppercase tracking-widest">
                    <span class="text-red-400">Threat</span> / <span class="text-green-400">Repair</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Button -->
    <div id="mobile-fire-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-indigo-400"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
    </div>

    <canvas id="mainCanvas"></canvas>

    <script>
        const canvas = document.getElementById('mainCanvas');
        const ctx = canvas.getContext('2d');
        const startScreen = document.getElementById('startScreen');
        const hud = document.getElementById('hud');
        const fuelBar = document.getElementById('fuel-bar');
        const fuelTxt = document.getElementById('fuel-txt');
        const healthBar = document.getElementById('health-bar');
        const scoreVal = document.getElementById('score-val');
        const compassNeedle = document.getElementById('compass-needle');
        const healthNeedle = document.getElementById('health-needle');
        const damageFlash = document.getElementById('damage-flash');
        const mobileFireBtn = document.getElementById('mobile-fire-btn');

        let width, height;
        let isStarted = false;
        let frame = 0;
        let mouse = { x: 0, y: 0, pressed: false };
        let keys = {};
        let screenShake = 0;

        // Simulation Entities
        let stars = [];
        let nebulae = [];
        let particles = []; 
        let bullets = [];
        let asteroids = [];

        const player = {
            x: 0, y: 0,
            vx: 0, vy: 0,
            angle: 0,
            health: 100,
            score: 0,
            shootCooldown: 0,
            thrustLevel: 0
        };

        // Detect touch capability for Fire button visibility
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        if (isTouchDevice) {
            mobileFireBtn.style.display = 'flex';
        }

        // --- Audio Context ---
        let audioCtx, masterGain;

        function initAudio() {
            try {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                masterGain = audioCtx.createGain();
                masterGain.gain.setValueAtTime(0, audioCtx.currentTime);
                masterGain.gain.linearRampToValueAtTime(0.25, audioCtx.currentTime + 3);
                masterGain.connect(audioCtx.destination);
            } catch (e) { console.log("Audio not supported"); }
        }

        function playSound(freq, type = 'triangle', duration = 0.5, volume = 0.05) {
            if(!audioCtx) return;
            const osc = audioCtx.createOscillator();
            const g = audioCtx.createGain();
            osc.type = type;
            osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(freq / 2, audioCtx.currentTime + duration);
            g.gain.setValueAtTime(volume, audioCtx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);
            osc.connect(g);
            g.connect(masterGain);
            osc.start();
            osc.stop(audioCtx.currentTime + duration);
        }

        // --- Classes ---
        class NebulaCloud {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.radius = 200 + Math.random() * 400;
                this.hue = Math.random() > 0.5 ? 260 : 200; 
                this.opacity = 0.03 + Math.random() * 0.04;
            }
            update() {
                this.x -= player.vx * 0.2;
                this.y -= player.vy * 0.2;
                if (this.x < -this.radius) this.x = width + this.radius;
                if (this.x > width + this.radius) this.x = -this.radius;
                if (this.y < -this.radius) this.y = height + this.radius;
                if (this.y > height + this.radius) this.y = -this.radius;
            }
            draw() {
                const g = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.radius);
                g.addColorStop(0, `hsla(${this.hue}, 80%, 30%, ${this.opacity})`);
                g.addColorStop(1, 'transparent');
                ctx.fillStyle = g;
                ctx.fillRect(this.x - this.radius, this.y - this.radius, this.radius * 2, this.radius * 2);
            }
        }

        class Star {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.z = Math.random() * 10;
                this.size = 0.5 + (1 - this.z/10) * 1.5;
                this.brightness = 0.1 + Math.random() * 0.9;
                this.color = Math.random() > 0.8 ? (Math.random() > 0.5 ? '#f9fafb' : '#bfdbfe') : '#fff';
            }
            update() {
                this.x -= player.vx * (1 / (this.z + 1));
                this.y -= player.vy * (1 / (this.z + 1));
                if (this.x < -10) this.x = width + 10;
                if (this.x > width + 10) this.x = -10;
                if (this.y < -10) this.y = height + 10;
                if (this.y > height + 10) this.y = -10;
            }
            draw() {
                ctx.fillStyle = this.color;
                ctx.globalAlpha = this.brightness;
                ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2); ctx.fill();
                ctx.globalAlpha = 1.0;
            }
        }

        class Bullet {
            constructor(x, y, angle) {
                this.x = x; this.y = y;
                this.vx = Math.cos(angle) * 18 + player.vx;
                this.vy = Math.sin(angle) * 18 + player.vy;
                this.life = 60;
            }
            update() {
                this.x += this.vx - player.vx;
                this.y += this.vy - player.vy;
                this.life--;
            }
            draw() {
                ctx.shadowBlur = 10; ctx.shadowColor = '#4f46e5';
                ctx.fillStyle = '#fff';
                ctx.beginPath(); ctx.arc(this.x, this.y, 2, 0, Math.PI * 2); ctx.fill();
                ctx.shadowBlur = 0;
            }
        }

        class Asteroid {
            constructor(x, y, isHealth = false) {
                this.x = x || (Math.random() > 0.5 ? -200 : width + 200);
                this.y = y || (Math.random() > 0.5 ? -200 : height + 200);
                this.isHealth = isHealth;
                this.radius = isHealth ? 15 : 20 + Math.random() * 40;
                this.vx = (Math.random() - 0.5) * 1.5;
                this.vy = (Math.random() - 0.5) * 1.5;
                this.rotation = Math.random() * Math.PI * 2;
                this.rotSpeed = (Math.random() - 0.5) * 0.01;
                this.points = [];
                const verts = isHealth ? 12 : 8 + Math.floor(Math.random() * 6);
                for(let i=0; i<verts; i++) {
                    const r = this.radius * (0.8 + Math.random() * 0.4);
                    const a = (i / verts) * Math.PI * 2;
                    this.points.push({x: Math.cos(a) * r, y: Math.sin(a) * r});
                }
            }
            update() {
                this.x += this.vx - player.vx;
                this.y += this.vy - player.vy;
                this.rotation += this.rotSpeed;

                const buffer = 800;
                if (this.x < -buffer) this.x = width + buffer;
                if (this.x > width + buffer) this.x = -buffer;
                if (this.y < -buffer) this.y = height + buffer;
                if (this.y > height + buffer) this.y = -buffer;
            }
            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.rotation);
                
                const grad = ctx.createRadialGradient(-this.radius*0.3, -this.radius*0.3, 0, 0, 0, this.radius);
                if (this.isHealth) {
                    grad.addColorStop(0, '#22c55e');
                    grad.addColorStop(1, '#064e3b');
                    ctx.shadowBlur = 15; ctx.shadowColor = '#22c55e';
                } else {
                    grad.addColorStop(0, '#4b5563');
                    grad.addColorStop(1, '#111827');
                }
                
                ctx.fillStyle = grad;
                ctx.strokeStyle = this.isHealth ? '#22c55e' : '#374151';
                ctx.lineWidth = 1;
                
                ctx.beginPath();
                ctx.moveTo(this.points[0].x, this.points[0].y);
                for(let i=1; i<this.points.length; i++) ctx.lineTo(this.points[i].x, this.points[i].y);
                ctx.closePath();
                ctx.fill();
                ctx.stroke();
                ctx.restore();
                ctx.shadowBlur = 0;
            }
        }

        class Stardust {
            constructor(x, y, vx, vy, color) {
                this.x = x; this.y = y; this.vx = vx; this.vy = vy;
                this.life = 1.0;
                this.color = color || `rgba(99, 102, 241, 1)`;
                this.size = Math.random() * 2 + 1;
            }
            update() {
                this.x += this.vx - player.vx;
                this.y += this.vy - player.vy;
                this.life -= 0.02;
            }
            draw() {
                ctx.globalAlpha = this.life; ctx.fillStyle = this.color;
                ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2); ctx.fill();
                ctx.globalAlpha = 1.0;
            }
        }

        function resize() {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
            player.x = width / 2;
            player.y = height / 2;
        }

        function triggerDamage() {
            player.health = Math.max(0, player.health - 15);
            screenShake = 15;
            damageFlash.style.opacity = '1';
            playSound(100, 'sawtooth', 0.4, 0.1);
            setTimeout(() => damageFlash.style.opacity = '0', 200);
            if (player.health <= 0) {
                player.health = 100;
                player.score = Math.floor(player.score * 0.5);
                scoreVal.innerText = player.score.toString().padStart(5, '0');
            }
        }

        function shoot() {
            if (player.shootCooldown <= 0) {
                bullets.push(new Bullet(width/2 + Math.cos(player.angle)*25, height/2 + Math.sin(player.angle)*25, player.angle));
                player.shootCooldown = 12;
                playSound(800, 'square', 0.1, 0.04);
            }
        }

        function beginMission() {
            if (isStarted) return;
            isStarted = true;
            startScreen.classList.add('ui-hidden');
            hud.classList.add('hud-active');
            initAudio();
            resize();
            for(let i=0; i<300; i++) stars.push(new Star());
            for(let i=0; i<10; i++) nebulae.push(new NebulaCloud());
            for(let i=0; i<12; i++) asteroids.push(new Asteroid());
            animate();
        }

        // --- Interaction Listeners ---
        window.addEventListener('mousedown', () => mouse.pressed = true);
        window.addEventListener('mouseup', () => mouse.pressed = false);
        window.addEventListener('mousemove', (e) => { mouse.x = e.clientX; mouse.y = e.clientY; });
        
        // Touch Interaction
        window.addEventListener('touchstart', (e) => {
            if (e.target === mobileFireBtn || mobileFireBtn.contains(e.target)) return;
            mouse.pressed = true;
            mouse.x = e.touches[0].clientX;
            mouse.y = e.touches[0].clientY;
        }, { passive: false });
        
        window.addEventListener('touchmove', (e) => {
            if (e.target === mobileFireBtn || mobileFireBtn.contains(e.target)) return;
            e.preventDefault(); // Stop scrolling
            mouse.x = e.touches[0].clientX;
            mouse.y = e.touches[0].clientY;
        }, { passive: false });

        window.addEventListener('touchend', () => {
            mouse.pressed = false;
        });

        // Mobile fire button
        mobileFireBtn.addEventListener('touchstart', (e) => {
            e.preventDefault();
            shoot();
        });

        window.addEventListener('keydown', (e) => {
            keys[e.code] = true;
            if (e.code === 'Space') shoot();
        });
        window.addEventListener('keyup', (e) => keys[e.code] = false);
        window.addEventListener('resize', resize);

        function drawRealisticShip() {
            ctx.save();
            ctx.translate(width/2, height/2);
            ctx.rotate(player.angle);
            if(mouse.pressed) {
                const flareWidth = 30 + Math.random() * 15;
                const flareGrad = ctx.createRadialGradient(-20, 0, 0, -25, 0, flareWidth);
                flareGrad.addColorStop(0, '#fff'); flareGrad.addColorStop(1, 'transparent');
                ctx.fillStyle = flareGrad; ctx.beginPath(); ctx.arc(-25, 0, flareWidth, 0, Math.PI*2); ctx.fill();
            }
            ctx.fillStyle = '#334155'; ctx.fillRect(-15, -15, 12, 6); ctx.fillRect(-15, 9, 12, 6);
            const hullGrad = ctx.createLinearGradient(0, -10, 0, 10);
            hullGrad.addColorStop(0, '#94a3b8'); hullGrad.addColorStop(1, '#1e293b');
            ctx.fillStyle = hullGrad;
            ctx.beginPath(); ctx.moveTo(25, 0); ctx.lineTo(-5, -12); ctx.lineTo(-20, -10); ctx.lineTo(-20, 10); ctx.lineTo(-5, 12); ctx.closePath(); ctx.fill();
            const glassGrad = ctx.createRadialGradient(8, -2, 0, 10, 0, 8);
            glassGrad.addColorStop(0, '#bae6fd'); glassGrad.addColorStop(1, '#0c4a6e');
            ctx.fillStyle = glassGrad; ctx.beginPath(); ctx.ellipse(8, 0, 8, 4, 0, 0, Math.PI*2); ctx.fill();
            ctx.restore();
        }

        function animate() {
            frame++;
            ctx.save();
            if (screenShake > 0) {
                ctx.translate((Math.random()-0.5)*screenShake, (Math.random()-0.5)*screenShake);
                screenShake *= 0.9;
            }
            ctx.fillStyle = '#020205'; ctx.fillRect(-20, -20, width+40, height+40);

            // Calculation logic
            const targetAngle = Math.atan2(mouse.y - height/2, mouse.x - width/2);
            let angleDiff = targetAngle - player.angle;
            while (angleDiff < -Math.PI) angleDiff += Math.PI * 2;
            while (angleDiff > Math.PI) angleDiff -= Math.PI * 2;
            player.angle += angleDiff * 0.12;

            if (mouse.pressed) {
                player.vx += Math.cos(player.angle) * 0.18;
                player.vy += Math.sin(player.angle) * 0.18;
                player.thrustLevel = Math.min(100, player.thrustLevel + 2);
                if (frame % 4 === 0) playSound(50, 'sine', 0.2, 0.02);
            } else { player.thrustLevel = Math.max(0, player.thrustLevel - 2); }

            if (player.shootCooldown > 0) player.shootCooldown--;
            player.vx *= 0.985; player.vy *= 0.985;

            nebulae.forEach(n => { n.update(); n.draw(); });
            stars.forEach(s => { s.update(); s.draw(); });
            
            asteroids.forEach((a, idx) => {
                a.update(); a.draw();
                // Player Collision
                const dxp = a.x - width/2; const dyp = a.y - height/2;
                if (Math.sqrt(dxp*dxp + dyp*dyp) < a.radius + 15) {
                    if (a.isHealth) {
                        player.health = Math.min(100, player.health + 25);
                        asteroids.splice(idx, 1);
                        playSound(1200, 'sine', 0.5, 0.1);
                        setTimeout(() => asteroids.push(new Asteroid()), 5000);
                    } else {
                        triggerDamage();
                        asteroids.splice(idx, 1);
                        setTimeout(() => asteroids.push(new Asteroid()), 2000);
                    }
                }
                // Bullet Collision
                bullets.forEach((b, bidx) => {
                    const dx = a.x - b.x; const dy = a.y - b.y;
                    if (Math.sqrt(dx*dx + dy*dy) < a.radius) {
                        if (a.isHealth) {
                            player.health = Math.min(100, player.health + 10);
                            playSound(1000, 'sine', 0.2, 0.1);
                        } else {
                            playSound(150, 'square', 0.2, 0.08);
                            player.score += 250; scoreVal.innerText = player.score.toString().padStart(5, '0');
                        }
                        for(let i=0; i<8; i++) particles.push(new Stardust(a.x, a.y, (Math.random()-0.5)*6, (Math.random()-0.5)*6, a.isHealth ? '#22c55e' : '#94a3b8'));
                        asteroids.splice(idx, 1); bullets.splice(bidx, 1);
                        setTimeout(() => asteroids.push(new Asteroid(null, null, Math.random() < 0.15)), 2000);
                    }
                });
            });

            bullets.forEach((b, idx) => { b.update(); b.draw(); if (b.life <= 0) bullets.splice(idx, 1); });
            particles.forEach((p, idx) => { p.update(); p.draw(); if (p.life <= 0) particles.splice(idx, 1); });

            drawRealisticShip();
            ctx.restore();

            // HUD & Scanner
            healthBar.style.width = player.health + '%';
            fuelBar.style.width = player.thrustLevel + '%';
            fuelTxt.innerText = Math.floor(player.thrustLevel) + '%';
            
            let nearestAsteroid = null, nearestHealth = null;
            let minDistA = Infinity, minDistH = Infinity;
            asteroids.forEach(a => {
                const dist = Math.sqrt((a.x - width/2)**2 + (a.y - height/2)**2);
                if (a.isHealth) { if (dist < minDistH) { minDistH = dist; nearestHealth = a; } } 
                else { if (dist < minDistA) { minDistA = dist; nearestAsteroid = a; } }
            });

            if (nearestAsteroid) {
                const angle = Math.atan2(nearestAsteroid.y - height/2, nearestAsteroid.x - width/2);
                compassNeedle.style.transform = `translate(-50%, -100%) rotate(${angle + Math.PI/2}rad)`;
            }
            if (nearestHealth) {
                const angle = Math.atan2(nearestHealth.y - height/2, nearestHealth.x - width/2);
                healthNeedle.style.opacity = '1';
                healthNeedle.style.transform = `translate(-50%, -100%) rotate(${angle + Math.PI/2}rad)`;
            } else { healthNeedle.style.opacity = '0'; }

            requestAnimationFrame(animate);
        }
        window.onload = () => {
            resize();
            // Show HUD preview
            ctx.fillStyle = '#020205';
            ctx.fillRect(0, 0, width, height);
        };
    </script>
</body>
</html>