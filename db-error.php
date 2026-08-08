<?php
/**
 * Database Connection Error Page
 * Beautifully styled error display for Zetaphase EduCloud
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/jpeg" href="logo.png">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <script src="assets/js/ui.js"></script>
    <title>System Maintenance - Zetaphase EduCloud</title>
    <style>
        :root {
            --brand-dark: #0d1117;
            --brand-card: #161b22;
            --brand-border: #30363d;
            --indigo-500: #6366f1;
            --indigo-600: #4f46e5;
            --emerald-400: #34d399;
            --amber-400: #fbbf24;
            --red-400: #f87171;
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
                radial-gradient(ellipse at 20% 50%, rgba(99, 102, 241, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 50%, rgba(244, 114, 182, 0.05) 0%, transparent 50%);
        }

        .error-container {
            max-width: 560px;
            width: 100%;
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 1.5rem;
            padding: 3rem 2.5rem;
            text-align: center;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(99, 102, 241, 0.1);
            position: relative;
            overflow: hidden;
        }

        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #ec4899, #f59e0b);
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: rgba(251, 191, 36, 0.1);
            border: 2px solid rgba(251, 191, 36, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.3); }
            50% { box-shadow: 0 0 0 20px rgba(251, 191, 36, 0); }
        }

        .icon-wrapper svg {
            width: 40px;
            height: 40px;
            color: #fbbf24;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.2);
            padding: 0.4rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fbbf24;
            margin-bottom: 1.5rem;
            font-family: 'SF Mono', 'Fira Code', monospace;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #fbbf24;
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

        .error-details {
            background: var(--brand-dark);
            border: 1px solid var(--brand-border);
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .error-label {
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--amber-400);
            margin-bottom: 0.5rem;
            font-family: 'SF Mono', 'Fira Code', monospace;
        }

        .error-message {
            font-size: 0.8125rem;
            color: var(--slate-400);
            font-family: 'SF Mono', 'Fira Code', monospace;
            line-height: 1.5;
            word-break: break-word;
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
        }

        .btn-primary {
            background: var(--indigo-600);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
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

        .steps {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin: 1.5rem 0;
            text-align: left;
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.8125rem;
            color: var(--slate-400);
        }

        .step-number {
            width: 24px;
            height: 24px;
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #6366f1;
            flex-shrink: 0;
            font-family: 'SF Mono', 'Fira Code', monospace;
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
            
            h1 {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">

        <div class="icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        </div>

        <h1>Database Connection Unavailable</h1>
        
        <p class="subtitle">
            Zetaphase EduCloud is temporarily unable to connect to its database server. 
            Our system is experiencing configuration or connectivity issues.
        </p>

        <div class="error-details">
            <div class="error-label">Connection Diagnostic</div>
            <div class="error-message">
                <?php 
                if (isset($e)) { error_log('DB connection error: ' . $e->getMessage()); }
                echo (getenv('APP_DEBUG') === '1' && isset($e))
                    ? htmlspecialchars($e->getMessage())
                    : 'Our team has been notified. Please try again shortly.';
                ?>
            </div>
        </div>

        <p class="footer-text">
            © 2026 Zetaphase EduCloud (zetaphase.com.ng). If the issue persists, contact system administration.
        </p>
    </div>

    <script>
        // Auto-retry connection every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>