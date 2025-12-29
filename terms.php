<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <title>Terms and Conditions - Astraden</title>
    <!-- Favicon - Must be early in head for proper display -->
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="alternate icon" type="image/png" href="Alogo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="Alogo.svg">
    <link rel="icon" type="image/svg+xml" sizes="any" href="Alogo.svg">
    
    <!-- Google Fonts - Space Theme -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/index.css">
    <style>
        body {
            position: relative;
            z-index: 1;
        }
        
        .terms-container {
            max-width: 900px;
            margin: 80px auto 40px;
            padding: 40px;
            background: rgba(15, 15, 25, 0.98);
            border-radius: 20px;
            box-shadow: 0 0 40px rgba(0, 255, 255, 0.3);
            border: 1px solid rgba(0, 255, 255, 0.2);
            position: relative;
            z-index: 10;
        }
        
        .terms-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(0, 255, 255, 0.3);
        }
        
        .terms-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            color: var(--primary-cyan);
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
            margin-bottom: 10px;
        }
        
        .terms-header p {
            color: rgba(0, 255, 255, 0.7);
            font-size: 1rem;
        }
        
        .terms-content {
            color: #ffffff;
            line-height: 1.8;
            font-size: 1rem;
            position: relative;
            z-index: 10;
        }
        
        .terms-content h2 {
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-cyan);
            font-size: 1.5rem;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0, 255, 255, 0.2);
        }
        
        .terms-content h3 {
            font-family: 'Rajdhani', sans-serif;
            color: var(--primary-purple);
            font-size: 1.2rem;
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .terms-content p {
            margin-bottom: 15px;
            text-align: justify;
            color: #ffffff;
        }
        
        .terms-content ul, .terms-content ol {
            margin-left: 30px;
            margin-bottom: 15px;
        }
        
        .terms-content li {
            margin-bottom: 10px;
            color: #ffffff;
        }
        
        .important-notice {
            background: rgba(255, 0, 110, 0.15);
            border-left: 4px solid var(--primary-pink);
            padding: 20px;
            margin: 25px 0;
            border-radius: 5px;
            position: relative;
            z-index: 10;
        }
        
        .important-notice strong {
            color: var(--primary-pink);
            font-size: 1.1rem;
        }
        
        .important-notice p {
            color: #ffffff;
        }
        
        .important-notice ul {
            color: #ffffff;
        }
        
        .important-notice li {
            color: #ffffff;
        }
        
        .disclaimer-box {
            background: rgba(157, 78, 221, 0.15);
            border: 2px solid var(--primary-purple);
            padding: 25px;
            margin: 30px 0;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(157, 78, 221, 0.3);
            position: relative;
            z-index: 10;
        }
        
        .disclaimer-box strong {
            color: var(--primary-purple);
            font-size: 1.2rem;
            display: block;
            margin-bottom: 10px;
        }
        
        .disclaimer-box p {
            color: #ffffff;
        }
        
        .disclaimer-box ul {
            color: #ffffff;
        }
        
        .disclaimer-box li {
            color: #ffffff;
        }
        
        .back-button {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple));
            color: var(--dark-bg);
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.3);
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.5);
        }
        
        .last-updated {
            text-align: center;
            color: rgba(0, 255, 255, 0.5);
            font-size: 0.9rem;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 255, 255, 0.2);
        }
        
        @media (max-width: 768px) {
            .terms-container {
                margin: 60px 20px 30px;
                padding: 25px;
            }
            
            .terms-header h1 {
                font-size: 1.8rem;
            }
            
            .terms-content h2 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body class="no-select" oncontextmenu="return false;">
    
    <!-- Space Background -->
    <div id="space-background"></div>
    
    <div class="terms-container">
        <div class="terms-header">
            <h1>TERMS AND CONDITIONS</h1>
            <p>Astraden - Legal Agreement</p>
        </div>
        
        <div class="terms-content">
            <div class="important-notice">
                <strong>⚠️ IMPORTANT: PLEASE READ CAREFULLY</strong>
                <p>By accessing, using, or registering an account on Astraden ("Platform", "Service", "We", "Us", "Our"), you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions. If you do not agree to these terms, you must not use our Platform.</p>
            </div>
            
            <h2>1. ACCEPTANCE OF TERMS</h2>
            <p>By creating an account, accessing, or using Astraden, you represent and warrant that:</p>
            <ul>
                <li>You are at least 18 years of age or the legal age of majority in your jurisdiction</li>
                <li>You have the legal capacity to enter into this binding agreement</li>
                <li>You will comply with all applicable laws and regulations</li>
                <li>You accept full responsibility for your use of the Platform</li>
                <li>You understand and agree to all disclaimers and limitations of liability set forth herein</li>
            </ul>
            
            <h2>2. PLATFORM DESCRIPTION</h2>
            <p>Astraden is a gaming contest platform that provides:</p>
            <ul>
                <li>Access to various space-themed games and contests</li>
                <li>Credit-based entry system for contests</li>
                <li>Credit rewards and contest participation</li>
            </ul>
            <p><strong>We are solely a contest platform provider.</strong> We facilitate contests and distribute credit rewards. We do not provide any other services, guarantees, warranties, or assume any responsibilities beyond contest facilitation and credit distribution.</p>
            
            <h2>3. USER RESPONSIBILITIES AND DEVICE USAGE</h2>
            <h3>3.1 Mobile Device Usage</h3>
            <div class="disclaimer-box">
                <strong>CRITICAL DISCLAIMER - MOBILE DEVICE USAGE</strong>
                <p>Users who access or play games on mobile devices do so entirely at their own risk. Astraden is NOT responsible for:</p>
                <ul>
                    <li>Any damage, loss, or malfunction of mobile devices while using our Platform</li>
                    <li>Battery drain, overheating, or performance issues on mobile devices</li>
                    <li>Data usage, network charges, or mobile carrier fees incurred while using our Platform</li>
                    <li>Compatibility issues between our Platform and any mobile device, operating system, or browser</li>
                    <li>Loss of data, contacts, or other information stored on mobile devices</li>
                    <li>Security breaches or unauthorized access to mobile devices</li>
                    <li>Any physical injury or health issues resulting from mobile device usage</li>
                    <li>Distraction-related incidents while playing games on mobile devices</li>
                </ul>
                <p><strong>You acknowledge that playing games on mobile devices may cause distraction and you are solely responsible for ensuring safe usage, including but not limited to not using mobile devices while driving, operating machinery, or in any situation where attention is required for safety.</strong></p>
            </div>
            
            <h3>3.2 General User Responsibilities</h3>
            <p>You are solely responsible for:</p>
            <ul>
                <li>Maintaining the confidentiality of your account credentials</li>
                <li>All activities that occur under your account</li>
                <li>Ensuring your device meets technical requirements</li>
                <li>Providing accurate and truthful information</li>
                <li>Compliance with all applicable local, state, and federal laws</li>
                <li>Any consequences resulting from your use of the Platform</li>
            </ul>
            
            <h2>4. PLATFORM FEATURES AND FUNCTIONALITY</h2>
            <div class="disclaimer-box">
                <strong>NO WARRANTIES OR GUARANTEES</strong>
                <p>Astraden provides the Platform "AS IS" and "AS AVAILABLE" without any warranties, express or implied. We are NOT responsible for:</p>
                <ul>
                    <li>Any errors, bugs, glitches, or technical issues in the Platform</li>
                    <li>Interruptions, downtime, or unavailability of the Platform</li>
                    <li>Loss of game progress, scores, or data</li>
                    <li>Incompatibility with any device, browser, or operating system</li>
                    <li>Network connectivity issues or internet service provider problems</li>
                    <li>Third-party service failures (payment processors, hosting providers, etc.)</li>
                    <li>Security breaches or unauthorized access (beyond our reasonable security measures)</li>
                    <li>Any modifications, updates, or changes to Platform features</li>
                    <li>Accuracy, completeness, or timeliness of any information on the Platform</li>
                </ul>
                <p>We reserve the right to modify, suspend, or discontinue any feature at any time without notice or liability.</p>
            </div>
            
            <h2>5. CONTESTS AND REAL MONEY TRANSFERS</h2>
            <h3>5.1 Contest Participation</h3>
            <p>By participating in contests on Astraden:</p>
            <ul>
                <li>You acknowledge that contests involve credit entry fees</li>
                <li>You understand that credit rewards are awarded based on contest rules and leaderboard rankings</li>
                <li>You agree that contest results are final and binding</li>
                <li>You accept that we reserve the right to disqualify any participant for violations of terms</li>
            </ul>
            
            <h3>5.2 Credit Rewards</h3>
            <div class="disclaimer-box">
                <strong>CREDIT REWARDS DISCLAIMER</strong>
                <p>Astraden provides credit rewards for contest participation and achievements. However:</p>
                <ul>
                    <li>We are NOT responsible for delays in credit distribution due to technical issues or system maintenance</li>
                    <li>We are NOT responsible for any errors in credit calculations or distribution</li>
                    <li>We are NOT responsible for incorrect account information provided by users</li>
                    <li>We are NOT responsible for any restrictions or limitations imposed by system policies</li>
                    <li>Credit rewards are subject to verification and may take time to process</li>
                    <li>Users are responsible for maintaining accurate account information</li>
                    <li>Credit rewards are non-transferable and subject to Platform terms</li>
                </ul>
                <p><strong>We provide credit rewards in good faith, but we are not liable for any issues beyond our direct control in the distribution process.</strong></p>
            </div>
            
            <h3>5.3 Credit System</h3>
            <p>Credits on the Platform:</p>
            <ul>
                <li>Are non-refundable except as required by law</li>
                <li>Cannot be exchanged for cash</li>
                <li>Are valid only for contest entry fees and game participation</li>
                <li>May expire according to Platform policies</li>
                <li>Are subject to Platform terms and conditions</li>
            </ul>
            
            <h2>6. LIMITATION OF LIABILITY</h2>
            <div class="disclaimer-box">
                <strong>COMPREHENSIVE LIMITATION OF LIABILITY</strong>
                <p>TO THE MAXIMUM EXTENT PERMITTED BY APPLICABLE LAW, ASTRADEN, ITS OWNERS, OPERATORS, EMPLOYEES, AGENTS, AND AFFILIATES SHALL NOT BE LIABLE FOR:</p>
                <ul>
                    <li>Any direct, indirect, incidental, special, consequential, or punitive damages</li>
                    <li>Loss of profits, revenue, data, or business opportunities</li>
                    <li>Personal injury or property damage</li>
                    <li>Emotional distress or mental anguish</li>
                    <li>Any damages resulting from use or inability to use the Platform</li>
                    <li>Any damages resulting from contests or credit distribution</li>
                    <li>Any damages resulting from mobile device usage</li>
                    <li>Any damages resulting from third-party services or products</li>
                    <li>Any damages resulting from unauthorized access or security breaches</li>
                    <li>Any damages resulting from technical failures or errors</li>
                </ul>
                <p><strong>OUR TOTAL LIABILITY SHALL NOT EXCEED THE AMOUNT YOU PAID TO US IN THE TWELVE (12) MONTHS PRECEDING THE CLAIM, OR ONE HUNDRED DOLLARS ($100), WHICHEVER IS GREATER.</strong></p>
            </div>
            
            <h2>7. INDEMNIFICATION</h2>
            <p>You agree to indemnify, defend, and hold harmless Astraden, its owners, operators, employees, agents, and affiliates from and against any and all claims, demands, damages, losses, liabilities, costs, and expenses (including reasonable attorney fees) arising out of or relating to:</p>
            <ul>
                <li>Your use of the Platform</li>
                <li>Your participation in contests</li>
                <li>Your violation of these Terms and Conditions</li>
                <li>Your violation of any law or regulation</li>
                <li>Your infringement of any third-party rights</li>
                <li>Any content you submit or transmit through the Platform</li>
            </ul>
            
            <h2>8. PROHIBITED ACTIVITIES</h2>
            <p>You agree NOT to:</p>
            <ul>
                <li>Use the Platform for any illegal or unauthorized purpose</li>
                <li>Attempt to hack, manipulate, or exploit the Platform</li>
                <li>Use automated systems, bots, or scripts to access the Platform</li>
                <li>Interfere with or disrupt the Platform's operation</li>
                <li>Impersonate any person or entity</li>
                <li>Create multiple accounts to gain unfair advantage</li>
                <li>Share your account credentials with others</li>
                <li>Engage in any fraudulent activity</li>
                <li>Violate any applicable laws or regulations</li>
            </ul>
            
            <h2>9. ACCOUNT TERMINATION</h2>
            <p>We reserve the right to:</p>
            <ul>
                <li>Suspend or terminate your account at any time for violations of these terms</li>
                <li>Refuse service to anyone for any reason</li>
                <li>Modify or discontinue the Platform without notice</li>
                <li>Withhold credits for suspected fraudulent activity</li>
            </ul>
            <p>Upon termination, your right to use the Platform immediately ceases, and we may delete your account and data.</p>
            
            <h2>10. DISPUTE RESOLUTION</h2>
            <p>Any disputes arising from or relating to these Terms and Conditions or your use of the Platform shall be resolved through:</p>
            <ul>
                <li>Good faith negotiation between parties</li>
                <li>Binding arbitration if negotiation fails (subject to applicable law)</li>
                <li>You waive any right to participate in class action lawsuits</li>
            </ul>
            <p>These Terms and Conditions are governed by applicable law, and any legal action must be brought in the appropriate jurisdiction.</p>
            
            <h2>11. MODIFICATIONS TO TERMS</h2>
            <p>We reserve the right to modify these Terms and Conditions at any time. Material changes will be notified through the Platform. Continued use of the Platform after modifications constitutes acceptance of the updated terms.</p>
            
            <h2>12. GENERAL PROVISIONS</h2>
            <h3>12.1 No Partnership or Agency</h3>
            <p>Nothing in these Terms creates a partnership, joint venture, agency, or employment relationship between you and Astraden.</p>
            
            <h3>12.2 Entire Agreement</h3>
            <p>These Terms and Conditions constitute the entire agreement between you and Astraden regarding the Platform.</p>
            
            <h3>12.3 Severability</h3>
            <p>If any provision of these Terms is found to be unenforceable, the remaining provisions shall remain in full effect.</p>
            
            <h3>12.4 Waiver</h3>
            <p>Our failure to enforce any provision of these Terms does not constitute a waiver of that provision.</p>
            
            <h3>12.5 Force Majeure</h3>
            <p>We are not liable for any failure to perform due to circumstances beyond our reasonable control, including natural disasters, war, terrorism, labor disputes, or internet failures.</p>
            
            <h2>13. CONTACT INFORMATION</h2>
            <p>For questions about these Terms and Conditions, please contact us through the Platform's support channels.</p>
            
            <div class="important-notice">
                <strong>FINAL ACKNOWLEDGMENT</strong>
                <p>By creating an account and using Astraden, you acknowledge that:</p>
                <ul>
                    <li>You have read and understood these Terms and Conditions in their entirety</li>
                    <li>You accept all risks associated with using the Platform</li>
                    <li>You understand that Astraden is solely a contest platform provider</li>
                    <li>You agree that we are not responsible for mobile device issues, platform features, or any matters beyond contest facilitation and credit distribution</li>
                    <li>You waive any claims against Astraden except as explicitly stated in these Terms</li>
                    <li>You agree to use the Platform at your own risk</li>
                </ul>
            </div>
            
            <div class="last-updated">
                <p>Last Updated: <?php echo date('F d, Y'); ?></p>
                <p>Version: 1.0</p>
            </div>
            
            <div style="text-align: center;">
                <a href="index.php" class="back-button">← Back to Home</a>
            </div>
        </div>
    </div>
    
    <script>
        // Create space background
        function createSpaceBackground() {
            const background = document.getElementById('space-background');
            if (!background) return;
            
            // Create stars
            for (let i = 0; i < 100; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 3 + 's';
                background.appendChild(star);
            }
            
            // Create shooting stars
            for (let i = 0; i < 5; i++) {
                const shootingStar = document.createElement('div');
                shootingStar.className = 'shooting-star';
                shootingStar.style.left = Math.random() * 100 + '%';
                shootingStar.style.top = Math.random() * 50 + '%';
                shootingStar.style.animationDelay = Math.random() * 3 + 's';
                background.appendChild(shootingStar);
            }
            
            // Create nebulas
            const nebula1 = document.createElement('div');
            nebula1.className = 'nebula nebula-1';
            background.appendChild(nebula1);
            
            const nebula2 = document.createElement('div');
            nebula2.className = 'nebula nebula-2';
            background.appendChild(nebula2);
        }
        
        createSpaceBackground();
    </script>
</body>
</html>

