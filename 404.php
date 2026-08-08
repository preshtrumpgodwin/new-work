<?php
/**
 * 404 Not Found Page
 * Beautifully styled 404 error for Zetaphase EduCloud
 */
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" type="image/jpeg" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <script src="assets/js/ui.js"></script>
    <title>404 - Page Not Found | Zetaphase EduCloud</title>
    <style>
        :root {
            --brand-dark: #0d1117;
            --brand-card: #161b22;
            --brand-border: #30363d;
            --indigo-400: #818cf8;
            --indigo-500: #6366f1;
            --indigo-600: #4f46e5;
            --purple-400: #c084fc;
            --pink-400: #f472b6;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--brand-dark);
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-image: 
                radial-gradient(ellipse at 30% 30%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 70% 70%, rgba(244, 114, 182, 0.08) 0%, transparent 50%);
            position: relative;
            overflow: hidden;
        }

        /* Animated background particles */
        .bg-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .particle {
            position: absolute;
            border-radius: 50%;
            background: var(--indigo-500);
            opacity: 0.1;
            animation: float 20s infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0) scale(1);
                opacity: 0.1;
            }
            25% {
                transform: translateY(-100px) translateX(50px) scale(1.5);
                opacity: 0.2;
            }
            50% {
                transform: translateY(-200px) translateX(-50px) scale(1);
                opacity: 0.05;
            }
            75% {
                transform: translateY(-100px) translateX(-100px) scale(1.5);
                opacity: 0.15;
            }
        }

        .error-container {
            position: relative;
            z-index: 1;
            max-width: 600px;
            width: 100%;
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 1.5rem;
            padding: 3.5rem 2.5rem;
            text-align: center;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(99, 102, 241, 0.1),
                0 0 100px -20px rgba(99, 102, 241, 0.15);
            overflow: hidden;
        }

        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899);
        }

        .error-code {
            font-size: 8rem;
            font-weight: 900;
            line-height: 1;
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #ec4899 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            letter-spacing: -0.04em;
            filter: drop-shadow(0 0 30px rgba(99, 102, 241, 0.3));
        }

        .glitch-wrapper {
            position: relative;
            display: inline-block;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.2);
            padding: 0.4rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #f87171;
            margin-bottom: 1.5rem;
            font-family: 'SF Mono', 'Fira Code', monospace;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #f87171;
            border-radius: 50%;
            animation: blink 1.5s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }

        .subtitle {
            font-size: 0.875rem;
            color: var(--slate-400);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .url-display {
            background: var(--brand-dark);
            border: 1px solid var(--brand-border);
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            margin-bottom: 2rem;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 0.8125rem;
            color: var(--slate-400);
            word-break: break-all;
            text-align: left;
        }

        .url-label {
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--indigo-400);
            margin-bottom: 0.5rem;
            display: block;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-size: 0.8125rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--indigo-600), #7c3aed);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: var(--slate-400);
            border: 1px solid var(--brand-border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            border-color: #475569;
        }

        .suggestions {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--brand-border);
            text-align: left;
        }

        .suggestions-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--slate-400);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }

        .suggestion-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .suggestion-list li a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--slate-400);
            text-decoration: none;
            font-size: 0.8125rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .suggestion-list li a:hover {
            background: rgba(99, 102, 241, 0.1);
            color: #ffffff;
        }

        .suggestion-icon {
            width: 16px;
            height: 16px;
            opacity: 0.5;
        }

        .footer-text {
            font-size: 0.75rem;
            color: var(--slate-500);
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--brand-border);
        }

        @media (max-width: 640px) {
            .error-container {
                padding: 2rem 1.5rem;
            }
            
            .error-code {
                font-size: 5rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Animated background particles -->
    <div class="bg-particles" id="particles"></div>

    <div class="error-container">

        <div class="error-code">404</div>
        
        <h1>Ooops, Page Not Found</h1>
        
        <p class="subtitle">
            The page you're looking for doesn't exist or has been moved. 
            It might have been renamed, relocated, or is temporarily unavailable on zetaphase.com.ng.
        </p>

        <div class="action-buttons">
            <a href="/" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Return Home
            </a>
            <button onclick="history.back()" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Go Back
            </button>
        </div>

        <p class="footer-text">
            © 2026 Zetaphase EduCloud (zetaphase.com.ng) · Enterprise Cloud School OS
        </p>
    </div>

    <script>
        // Display current URL
        document.getElementById('currentUrl').textContent = window.location.href;

        // Generate animated background particles
        const particlesContainer = document.getElementById('particles');
        for (let i = 0; i < 15; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');
            const size = Math.random() * 100 + 50;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 20 + 's';
            particle.style.animationDuration = (Math.random() * 20 + 15) + 's';
            particlesContainer.appendChild(particle);
        }
    </script>
</body>
</html>