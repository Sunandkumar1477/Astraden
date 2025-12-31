<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Galactic Gardener - High Performance Mobile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Plus+Jakarta+Sans:wght@300;400&display=swap');

        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #010103;
            color: #e0e7ff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            user-select: none;
            touch-action: none;
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
            transition: opacity 0.8s ease;
            background: #010103;
        }

        .ui-hidden {
            opacity: 0;
            pointer-events: none;
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
            transition: opacity 1s ease;
            z-index: 40;
        }

        .hud-active { opacity: 1; }

        .glass {
            background: rgba(10, 15, 30, 0.6);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            padding: 0.6rem;
        }

        .btn-start {
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 0.4em;
            padding: 1rem 2rem;
            border: 2px solid #6366f1;
            background: rgba(99, 102, 241, 0.1);
            color: #fff;
            font-size: 1rem;
            text-transform: uppercase;
        }

        #mobile-fire-btn {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            width: 70px;
            height: 70px;
            background: rgba(99, 102, 241, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            pointer-events: auto;
        }

        #mobile-fire-btn:active {
            background: rgba(255, 255, 255, 0.4);
            transform: scale(0.95);
        }

        .label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #818cf8;
            font-weight: 700;
        }

        #damage-flash {
            position: fixed;
            inset: 0;
            background: rgba(239, 68, 68, 0.2);
            pointer-events: none;
            opacity: 0;
            z-index: 60;
        }
    </style>
</head>
<body>

    <div id="damage-flash"></div>

    <div id="startScreen" class="ui-screen">
        <h1 class="text-3xl md:text-5xl font-bold tracking-widest mb-4 text-white font-['Orbitron'] text-center px-4">GALACTIC GARDENER</h1>
        <p class="text-indigo-300 tracking-[0.2em] mb-12 opacity-50 uppercase text-[9px] text-center px-4">Deep Space Extraction Initiative</p>
        <button class="btn-start" onclick="beginMission()">Launch</button>
    </div>

    <div id="hud" class="hud">
        <div class="flex justify-between items-start">
            <div class="flex flex-col gap-2">
                <div class="glass flex flex-col gap-1 w-36 md:w-48">
                    <span class="label">Hull Integrity</span>
                    <div class="h-[4px] bg-gray-900 rounded-full overflow-hidden">
                        <div id="health-bar" class="h-full bg-green-500" style="width: 100%"></div>
                    </div>
                </div>
                <div class="glass flex flex-col gap-1 w-36 md:w-48">
                    <span class="label">Engine Thrust</span>
                    <div class="h-[2px] bg-gray-900 rounded-full overflow-hidden">
                        <div id="fuel-bar" class="h-full bg-indigo-500" style="width: 0%"></div>
                    </div>
                </div>
            </div>
            <div class="glass text-right">
                <span class="label">Stellar Score</span>
                <div id="score-val" class="text-xl font-['Orbitron'] text-indigo-400">00000</div>
            </div>
        </div>
    </div>

    <div id="mobile-fire-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
    </div>

    <canvas id="mainCanvas"></canvas>

    <script>
        const canvas = document.getElementById('mainCanvas');
        const ctx = canvas.getContext('2d', { alpha: false }); 
        const startScreen = document.getElementById('startScreen');
        const hud = document.getElementById('hud');
        const fuelBar = document.getElementById('fuel-bar');
        const healthBar = document.getElementById('health-bar');
        const scoreVal = document.getElementById('score-val');
        const damageFlash = document.getElementById('damage-flash');
        const mobileFireBtn = document.getElementById('mobile-fire-btn');

        let width, height;
        let isStarted = false;
        let frame = 0;
        let mouse = { x: 0, y: 0, pressed: false };
        let screenShake = 0;

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

        class NebulaCloud {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.radius = 150 + Math.random() * 200;
                this.hue = Math.random() > 0.5 ? 260 : 200;
                this.opacity = 0.04;
            }
            update() {
                this.x -= player.vx * 0.2;
                this.y -= player.vy * 0.2;
                if (this.x < -this.radius) this.x = width + this.radius;
                else if (this.x > width + this.radius) this.x = -this.radius;
                if (this.y < -this.radius) this.y = height + this.radius;
                else if (this.y > height + this.radius) this.y = -this.radius;
            }
            draw() {
                const g = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.radius);
                g.addColorStop(0, `hsla(${this.hue}, 50%, 20%, ${this.opacity})`);
                g.addColorStop(1, 'transparent');
                ctx.fillStyle = g;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        class Star {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.z = Math.random() * 8;
                this.size = 1;
                this.color = '#fff';
            }
            update() {
                this.x -= player.vx * (1 / (this.z + 1));
                this.y -= player.vy * (1 / (this.z + 1));
                if (this.x < 0) this.x = width;
                else if (this.x > width) this.x = 0;
                if (this.y < 0) this.y = height;
                else if (this.y > height) this.y = 0;
            }
            draw() {
                ctx.fillStyle = this.color;
                ctx.fillRect(this.x, this.y, this.size, this.size);
            }
        }

        class Bullet {
            constructor(x, y, angle) {
                this.x = x; this.y = y;
                this.vx = Math.cos(angle) * 15 + player.vx;
                this.vy = Math.sin(angle) * 15 + player.vy;
                this.life = 50;
            }
            update() {
                this.x += this.vx - player.vx;
                this.y += this.vy - player.vy;
                this.life--;
            }
            draw() {
                ctx.fillStyle = '#fff';
                ctx.beginPath();
                ctx.arc(this.x, this.y, 2, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        class Asteroid {
            constructor(x, y, isHealth = false) {
                this.x = x || (Math.random() > 0.5 ? -100 : width + 100);
                this.y = y || (Math.random() > 0.5 ? -100 : height + 100);
                this.isHealth = isHealth;
                this.radius = isHealth ? 18 : 20 + Math.random() * 35;
                this.vx = (Math.random() - 0.5) * 1.2;
                this.vy = (Math.random() - 0.5) * 1.2;
                this.rotation = Math.random() * 6;
                this.rotSpeed = (Math.random() - 0.5) * 0.03;
                
                // Realistic shape generation
                this.points = [];
                const vertices = 8 + Math.floor(Math.random() * 6);
                for (let i = 0; i < vertices; i++) {
                    const angle = (i / vertices) * Math.PI * 2;
                    const jitter = 0.7 + Math.random() * 0.4;
                    this.points.push({
                        x: Math.cos(angle) * jitter * this.radius,
                        y: Math.sin(angle) * jitter * this.radius
                    });
                }

                // Surface detail (craters)
                this.craters = [];
                const craterCount = 2 + Math.floor(Math.random() * 3);
                for (let i = 0; i < craterCount; i++) {
                    const angle = Math.random() * Math.PI * 2;
                    const dist = Math.random() * (this.radius * 0.6);
                    this.craters.push({
                        x: Math.cos(angle) * dist,
                        y: Math.sin(angle) * dist,
                        r: 2 + Math.random() * (this.radius * 0.2)
                    });
                }
            }

            update() {
                this.x += this.vx - player.vx;
                this.y += this.vy - player.vy;
                this.rotation += this.rotSpeed;
                const buffer = 400;
                if (this.x < -buffer) this.x = width + buffer;
                else if (this.x > width + buffer) this.x = -buffer;
                if (this.y < -buffer) this.y = height + buffer;
                else if (this.y > height + buffer) this.y = -buffer;
            }

            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.rotation);

                // Volumetric Shading
                const lightAngle = -Math.PI / 4;
                const grad = ctx.createRadialGradient(
                    -this.radius * 0.3, -this.radius * 0.3, 0,
                    0, 0, this.radius
                );

                if (this.isHealth) {
                    grad.addColorStop(0, '#4ade80'); // Emerald highlight
                    grad.addColorStop(0.5, '#166534'); // Core color
                    grad.addColorStop(1, '#052e16'); // Dark edge
                } else {
                    grad.addColorStop(0, '#94a3b8'); // Slate 400 highlight
                    grad.addColorStop(0.4, '#475569'); // Mid gray
                    grad.addColorStop(1, '#0f172a'); // Near black shadow
                }

                // Draw main body
                ctx.beginPath();
                ctx.moveTo(this.points[0].x, this.points[0].y);
                for (let i = 1; i < this.points.length; i++) {
                    ctx.lineTo(this.points[i].x, this.points[i].y);
                }
                ctx.closePath();
                ctx.fillStyle = grad;
                ctx.fill();

                // Surface Craters
                this.craters.forEach(c => {
                    const craterGrad = ctx.createLinearGradient(
                        c.x - c.r, c.y - c.r,
                        c.x + c.r, c.y + c.r
                    );
                    craterGrad.addColorStop(0, 'rgba(0,0,0,0.4)');
                    craterGrad.addColorStop(1, 'rgba(255,255,255,0.1)');
                    
                    ctx.fillStyle = craterGrad;
                    ctx.beginPath();
                    ctx.arc(c.x, c.y, c.r, 0, Math.PI * 2);
                    ctx.fill();
                });

                // Detailed rim light for health asteroids
                if (this.isHealth) {
                    ctx.strokeStyle = 'rgba(74, 222, 128, 0.5)';
                    ctx.lineWidth = 1;
                    ctx.stroke();
                }

                ctx.restore();
            }
        }

        class Stardust {
            constructor(x, y, vx, vy, color) {
                this.x = x; this.y = y; this.vx = vx; this.vy = vy;
                this.life = 1.0;
                this.color = color || '#818cf8';
            }
            update() {
                this.x += this.vx - player.vx;
                this.y += this.vy - player.vy;
                this.life -= 0.04;
            }
            draw() {
                ctx.globalAlpha = this.life;
                ctx.fillStyle = this.color;
                ctx.fillRect(this.x, this.y, 2, 2);
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
            screenShake = 10;
            damageFlash.style.opacity = '1';
            setTimeout(() => damageFlash.style.opacity = '0', 100);
            if (player.health <= 0) {
                player.health = 100;
                player.score = Math.floor(player.score * 0.5);
                scoreVal.innerText = player.score.toString().padStart(5, '0');
            }
        }

        function shoot() {
            if (player.shootCooldown <= 0) {
                bullets.push(new Bullet(width/2, height/2, player.angle));
                player.shootCooldown = 12;
            }
        }

        function beginMission() {
            if (isStarted) return;
            isStarted = true;
            startScreen.classList.add('ui-hidden');
            hud.classList.add('hud-active');
            resize();
            for(let i=0; i<150; i++) stars.push(new Star());
            for(let i=0; i<4; i++) nebulae.push(new NebulaCloud());
            for(let i=0; i<10; i++) asteroids.push(new Asteroid());
            animate();
        }

        window.addEventListener('touchstart', (e) => {
            if (e.target === mobileFireBtn || mobileFireBtn.contains(e.target)) return;
            mouse.pressed = true;
            mouse.x = e.touches[0].clientX;
            mouse.y = e.touches[0].clientY;
        }, { passive: false });
        
        window.addEventListener('touchmove', (e) => {
            if (e.target === mobileFireBtn || mobileFireBtn.contains(e.target)) return;
            e.preventDefault();
            mouse.x = e.touches[0].clientX;
            mouse.y = e.touches[0].clientY;
        }, { passive: false });

        window.addEventListener('touchend', () => mouse.pressed = false);

        mobileFireBtn.addEventListener('touchstart', (e) => {
            e.preventDefault();
            shoot();
        });

        window.addEventListener('mousedown', () => mouse.pressed = true);
        window.addEventListener('mouseup', () => mouse.pressed = false);
        window.addEventListener('mousemove', (e) => { mouse.x = e.clientX; mouse.y = e.clientY; });
        window.addEventListener('keydown', (e) => { if (e.code === 'Space') shoot(); });
        window.addEventListener('resize', resize);

        function drawShip() {
            ctx.save();
            ctx.translate(width/2, height/2);
            ctx.rotate(player.angle);
            
            if(mouse.pressed) {
                const flareSize = 20 + Math.random() * 15;
                const flareGrad = ctx.createRadialGradient(-15, 0, 0, -20, 0, flareSize);
                flareGrad.addColorStop(0, '#fff');
                flareGrad.addColorStop(0.3, '#6366f1');
                flareGrad.addColorStop(1, 'transparent');
                ctx.fillStyle = flareGrad;
                ctx.beginPath();
                ctx.arc(-20, 0, flareSize, 0, Math.PI * 2);
                ctx.fill();
            }

            const hullGrad = ctx.createLinearGradient(0, -10, 0, 10);
            hullGrad.addColorStop(0, '#94a3b8'); 
            hullGrad.addColorStop(0.5, '#475569'); 
            hullGrad.addColorStop(1, '#1e293b'); 
            
            ctx.fillStyle = hullGrad;
            ctx.strokeStyle = 'rgba(255,255,255,0.2)';
            ctx.lineWidth = 1;

            ctx.beginPath();
            ctx.moveTo(25, 0);       
            ctx.lineTo(-5, -12);     
            ctx.lineTo(-18, -8);     
            ctx.lineTo(-18, 8);      
            ctx.lineTo(-5, 12);      
            ctx.closePath();
            ctx.fill();
            ctx.stroke();

            const glassGrad = ctx.createRadialGradient(8, -2, 0, 10, 0, 8);
            glassGrad.addColorStop(0, '#bae6fd'); 
            glassGrad.addColorStop(1, '#0c4a6e'); 
            ctx.fillStyle = glassGrad;
            ctx.beginPath();
            ctx.ellipse(8, 0, 8, 4, 0, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#334155';
            ctx.fillRect(-15, -14, 10, 5); 
            ctx.fillRect(-15, 9, 10, 5);  
            
            ctx.restore();
        }

        function animate() {
            frame++;
            ctx.save();
            if (screenShake > 0) {
                ctx.translate((Math.random()-0.5)*screenShake, (Math.random()-0.5)*screenShake);
                screenShake *= 0.85;
            }
            
            ctx.fillStyle = '#010103';
            ctx.fillRect(0, 0, width, height);

            const targetAngle = Math.atan2(mouse.y - height/2, mouse.x - width/2);
            let angleDiff = targetAngle - player.angle;
            while (angleDiff < -Math.PI) angleDiff += Math.PI * 2;
            while (angleDiff > Math.PI) angleDiff -= Math.PI * 2;
            player.angle += angleDiff * 0.15;

            if (mouse.pressed) {
                player.vx += Math.cos(player.angle) * 0.2;
                player.vy += Math.sin(player.angle) * 0.2;
                player.thrustLevel = Math.min(100, player.thrustLevel + 4);
                if (frame % 5 === 0) particles.push(new Stardust(width/2, height/2, -Math.cos(player.angle)*3, -Math.sin(player.angle)*3));
            } else {
                player.thrustLevel = Math.max(0, player.thrustLevel - 3);
            }

            if (player.shootCooldown > 0) player.shootCooldown--;
            player.vx *= 0.98;
            player.vy *= 0.98;

            nebulae.forEach(n => { n.update(); n.draw(); });
            stars.forEach(s => { s.update(); s.draw(); });
            
            asteroids.forEach((a, idx) => {
                a.update(); a.draw();
                const dShip = Math.hypot(a.x - width/2, a.y - height/2);
                if (dShip < a.radius + 15) {
                    if (a.isHealth) {
                        player.health = Math.min(100, player.health + 20);
                        asteroids.splice(idx, 1);
                        setTimeout(() => asteroids.push(new Asteroid()), 3000);
                    } else {
                        triggerDamage();
                        asteroids.splice(idx, 1);
                        setTimeout(() => asteroids.push(new Asteroid()), 1500);
                    }
                }

                bullets.forEach((b, bidx) => {
                    const dBull = Math.hypot(a.x - b.x, a.y - b.y);
                    if (dBull < a.radius) {
                        if (!a.isHealth) {
                            player.score += 100;
                            scoreVal.innerText = player.score.toString().padStart(5, '0');
                        } else {
                            player.health = Math.min(100, player.health + 5);
                        }
                        asteroids.splice(idx, 1);
                        bullets.splice(bidx, 1);
                        setTimeout(() => asteroids.push(new Asteroid(null, null, Math.random() < 0.15)), 2000);
                    }
                });
            });

            bullets.forEach((b, idx) => { b.update(); b.draw(); if (b.life <= 0) bullets.splice(idx, 1); });
            particles.forEach((p, idx) => { p.update(); p.draw(); if (p.life <= 0) particles.splice(idx, 1); });

            drawShip();
            ctx.restore();

            healthBar.style.width = player.health + '%';
            fuelBar.style.width = player.thrustLevel + '%';

            requestAnimationFrame(animate);
        }
        window.onload = resize;
    </script>
</body>
</html>