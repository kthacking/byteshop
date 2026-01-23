<?php
/**
 * ByteShop - UI Effects Include
 * Contains: Preloader, Custom Cursor, and Mouse Effects
 * Included in headers and auth pages for consistent UX
 */
?>
<!-- Preloader & Custom Cursor HTML -->
<div id="preloader">
    <div class="loader-content">
        <div class="loader-spinner"></div>
        <div class="loader-text">ByteShop</div>
    </div>
</div>
<div class="cursor-dot"></div>
<div class="cursor-circle"></div>

<style>
    /* UI Effects Styles - Scoped to avoid conflicts */
    
    /* Preloader */
    #preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: #ffffff;
        z-index: 999999; /* Highest priority */
        display: flex;
        justify-content: center;
        align-items: center;
        transition: opacity 0.5s ease, visibility 0.5s ease;
    }

    .loader-content {
        text-align: center;
    }

    .loader-spinner {
        width: 50px;
        height: 50px;
        border: 3px solid rgba(255, 107, 53, 0.2);
        border-radius: 50%;
        border-top-color: #FF6B35;
        animation: spin 1s ease-in-out infinite;
        margin: 0 auto 15px;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .loader-text {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        color: #333;
        font-size: 1.2rem;
        letter-spacing: 2px;
        animation: pulse 2s infinite;
        text-transform: uppercase;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* Custom Cursor */
    body {
        cursor: none; /* Hide default cursor */
    }

    a, button, input, select, .btn, .clickable {
        cursor: none; /* Ensure elements don't show default cursor */
    }

    .cursor-dot,
    .cursor-circle {
        position: fixed;
        top: 0;
        left: 0;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        pointer-events: none;
        z-index: 10000;
    }

    .cursor-dot {
        width: 8px;
        height: 8px;
        background-color: #FF6B35;
        box-shadow: 0 0 10px rgba(255, 107, 53, 0.5);
    }

    .cursor-circle {
        width: 40px;
        height: 40px;
        border: 1.5px solid rgba(255, 107, 53, 0.5);
        transition: width 0.3s, height 0.3s, background-color 0.3s, border-color 0.3s;
    }

    /* Hover Effects for Cursor */
    body:has(a:hover) .cursor-circle,
    body:has(button:hover) .cursor-circle,
    body:has(input:hover) .cursor-circle,
    body:has(select:hover) .cursor-circle,
    body:has(.clickable:hover) .cursor-circle,
    body:has(.btn:hover) .cursor-circle {
        width: 60px;
        height: 60px;
        background-color: rgba(255, 107, 53, 0.1);
        border-color: #FF6B35;
    }

    /* Mobile Handling */
    @media (max-width: 768px) {
        .cursor-dot, .cursor-circle { display: none !important; }
        body, a, button, input, select { cursor: auto !important; }
        /* Keep preloader on mobile? User requested loading design. Yes. */
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preloader Logic
    const preloader = document.getElementById('preloader');
    if(preloader) {
        window.addEventListener('load', () => {
             // Slight delay for smooth visual
            setTimeout(() => {
                preloader.style.opacity = '0';
                preloader.style.visibility = 'hidden';
                setTimeout(() => { preloader.remove(); }, 500);
            }, 600);
        });
        
        // Fallback: Force remove after 5 seconds if window.load hangs (external assets)
        setTimeout(() => {
            if(document.body.contains(preloader)) {
                 preloader.style.opacity = '0';
                 preloader.style.visibility = 'hidden';
                 setTimeout(() => { preloader.remove(); }, 500);
            }
        }, 5000);
    }

    // Custom Cursor Logic
    const cursorDot = document.querySelector('.cursor-dot');
    const cursorCircle = document.querySelector('.cursor-circle');

    // Only run cursor effect on desktop
    if (window.matchMedia("(min-width: 769px)").matches && cursorDot && cursorCircle) {
        let mouseX = 0;
        let mouseY = 0;
        let circleX = 0;
        let circleY = 0;

        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
            
            // Dot follows instantly
            cursorDot.style.left = mouseX + 'px';
            cursorDot.style.top = mouseY + 'px';
        });

        // Circle follows with delay/momentum
        function animateCircle() {
            circleX += (mouseX - circleX) * 0.15;
            circleY += (mouseY - circleY) * 0.15;
            
            cursorCircle.style.left = circleX + 'px';
            cursorCircle.style.top = circleY + 'px';
            
            requestAnimationFrame(animateCircle);
        }
        animateCircle();
    }
});
</script>
