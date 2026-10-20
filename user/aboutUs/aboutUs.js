// user/aboutUs/aboutUs.js
(function () {
  'use strict';

  /* ============================================================
     1. SCROLL REVEAL ANIMATIONS (Intersection Observer)
     ============================================================ */
  const observerOptions = {
    root: null,
    rootMargin: '0px',
    threshold: 0.15 // Trigger when 15% of the element is visible
  };

  const observer = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        // Remove the utility classes that hide it, and add ones that show it
        entry.target.classList.remove('opacity-0', 'translate-y-12', 'translate-y-8', 'translate-x-[-50px]');
        entry.target.classList.add('opacity-100', 'translate-y-0', 'translate-x-0');
        
        // If it's the stats section, trigger the counter
        if (entry.target.classList.contains('counter-trigger')) {
          startCounters();
        }

        // Stop observing once animated
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  document.querySelectorAll('.reveal-on-scroll').forEach(el => {
    observer.observe(el);
  });

  // Observe the stats section specifically
  const statsSection = document.querySelector('.counter');
  if (statsSection) {
    const statsObserver = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting) {
        startCounters();
        statsObserver.disconnect();
      }
    }, { threshold: 0.5 });
    
    // Observe the parent container of the counters
    statsObserver.observe(statsSection.closest('.grid'));
  }

  /* ============================================================
     2. NUMBER COUNT-UP ANIMATION
     ============================================================ */
  let countersStarted = false;

  function startCounters() {
    if (countersStarted) return;
    countersStarted = true;

    const counters = document.querySelectorAll('.counter');
    const speed = 2000; // 2 seconds duration

    counters.forEach(counter => {
      const target = parseFloat(counter.getAttribute('data-target'));
      const decimals = parseInt(counter.getAttribute('data-decimals') || 0);
      const start = 0;
      const startTime = performance.now();

      function updateNumber(currentTime) {
        const elapsedTime = currentTime - startTime;
        if (elapsedTime < speed) {
          // Ease out quad
          const progress = elapsedTime / speed;
          const easeOutProgress = 1 - Math.pow(1 - progress, 3);
          const current = start + (target - start) * easeOutProgress;
          
          counter.textContent = current.toFixed(decimals);
          requestAnimationFrame(updateNumber);
        } else {
          counter.textContent = target.toFixed(decimals);
        }
      }
      requestAnimationFrame(updateNumber);
    });
  }

  /* ============================================================
     3. BUILD VERTICAL IMAGE MARQUEE (Section 1)
     ============================================================ */
  const imageMarquee = document.getElementById('imageMarquee');
  if (imageMarquee && window.marqueeImages) {
    // Duplicate images for seamless looping
    const allImages = [...window.marqueeImages, ...window.marqueeImages];
    
    imageMarquee.innerHTML = allImages.map(src => `
      <div class="w-full h-64 md:h-80 flex-shrink-0 rounded-2xl overflow-hidden shadow-lg mb-4">
        <img src="${src}" class="w-full h-full object-cover" 
             onerror="this.src='../../images/services/placeholder.jpg'">
      </div>
    `).join('');
  }

  /* ============================================================
     4. BUILD HORIZONTAL REVIEW MARQUEE (Section 4)
     ============================================================ */
  const reviewMarquee = document.getElementById('reviewMarquee');
  if (reviewMarquee && window.reviewData) {
    // Duplicate reviews for seamless scrolling
    const allReviews = [...window.reviewData, ...window.reviewData, ...window.reviewData];
    
    reviewMarquee.innerHTML = allReviews.map(r => {
      const stars = '★'.repeat(r.rating) + '☆'.repeat(5 - r.rating);
      return `
        <div class="w-80 md:w-96 flex-shrink-0 bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-2xl">
          <div class="text-amber-400 text-lg mb-3">${stars}</div>
          <p class="text-white text-sm leading-relaxed mb-6 min-h-[80px]">"${r.comment}"</p>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-[#3C52C3] flex items-center justify-center text-white font-bold">
              ${r.firstName.charAt(0)}
            </div>
            <div>
              <p class="text-white text-sm font-bold">${r.firstName} ${r.lastName}</p>
              <p class="text-slate-400 text-xs">Verified Customer</p>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

})();