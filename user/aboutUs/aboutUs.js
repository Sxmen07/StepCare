// user/aboutUs/aboutUs.js
(function () {
  'use strict';

  /* ============================================================
     1. SCROLL REVEAL ANIMATIONS (Intersection Observer)
     ============================================================ */
  const observerOptions = {
    root: null,
    rootMargin: '0px',
    threshold: 0.15
  };

  const observer = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.remove('opacity-0', 'translate-y-12', 'translate-y-8', 'translate-x-[-50px]');
        entry.target.classList.add('is-revealed', 'opacity-100', 'translate-y-0', 'translate-x-0');
        
        if (entry.target.classList.contains('counter-trigger')) {
          startCounters();
        }

        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  document.querySelectorAll('.reveal-on-scroll').forEach(el => {
    observer.observe(el);
  });

  const statsSection = document.querySelector('.counter');
  if (statsSection) {
    const statsObserver = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting) {
        startCounters();
        statsObserver.disconnect();
      }
    }, { threshold: 0.5 });
    
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
    const speed = 2000;

    counters.forEach(counter => {
      const target = parseFloat(counter.getAttribute('data-target')) || 0;
      const decimals = parseInt(counter.getAttribute('data-decimals') || 0);
      const start = 0;
      const startTime = performance.now();

      function updateNumber(currentTime) {
        const elapsedTime = currentTime - startTime;
        if (elapsedTime < speed) {
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
  if (imageMarquee) {
    if (window.marqueeImages && window.marqueeImages.length > 0) {
      const allImages = [...window.marqueeImages, ...window.marqueeImages];
      imageMarquee.innerHTML = allImages.map(src => `
        <div class="marquee-card w-full h-64 md:h-80 flex-shrink-0 rounded-2xl overflow-hidden shadow-md mb-2 relative group border border-slate-100">
          <img src="${src}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out" 
               onerror="this.src='../../images/services/placeholder.jpg'">
          <div class="absolute inset-0 bg-gradient-to-t from-slate-900/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
        </div>
      `).join('');
    } else {
      // Graceful message if database has no service images uploaded yet
      imageMarquee.innerHTML = `
        <div class="w-full h-full flex items-center justify-center p-6 text-center bg-slate-100 rounded-2xl text-slate-400 text-sm">
          No service preview images available yet.
        </div>
      `;
    }
  }

  /* ============================================================
     4. BUILD HORIZONTAL REVIEW MARQUEE (Section 4)
     ============================================================ */
  const reviewMarquee = document.getElementById('reviewMarquee');
  if (reviewMarquee) {
    if (window.reviewData && window.reviewData.length > 0) {
      const allReviews = [...window.reviewData, ...window.reviewData, ...window.reviewData];
      reviewMarquee.innerHTML = allReviews.map(r => {
        const ratingNum = parseInt(r.rating) || 5;
        const stars = '★'.repeat(ratingNum) + '☆'.repeat(5 - ratingNum);
        const firstName = r.firstName || 'Anonymous';
        const lastName = r.lastName ? r.lastName.charAt(0) + '.' : '';
        return `
          <div class="review-card w-80 md:w-96 flex-shrink-0 bg-slate-900/60 backdrop-blur-xl border border-slate-800/80 p-7 rounded-3xl shadow-xl flex flex-col justify-between">
            <div>
              <div class="flex items-center justify-between mb-4">
                <div class="text-amber-400 text-sm tracking-widest bg-amber-400/10 px-3 py-1 rounded-full border border-amber-400/20">${stars}</div>
                <span class="text-xs text-slate-500 font-medium">Verified Care</span>
              </div>
              <p class="text-slate-200 text-sm leading-relaxed mb-6 italic font-light min-h-[75px]">"${r.comment}"</p>
            </div>
            <div class="flex items-center gap-3.5 pt-4 border-t border-slate-800/60">
              <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-[#3C52C3] to-indigo-500 flex items-center justify-center text-white font-bold text-sm shadow-md shadow-[#3C52C3]/30">
                ${firstName.charAt(0)}
              </div>
              <div>
                <p class="text-white text-sm font-bold tracking-wide">${firstName} ${lastName}</p>
                <p class="text-emerald-400 text-xs font-medium flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Verified Customer
                </p>
              </div>
            </div>
          </div>
        `;
      }).join('');
    } else {
      // Graceful message if database has no customer reviews yet
      reviewMarquee.innerHTML = `
        <div class="w-full py-12 text-center text-slate-500 text-sm">
          No reviews have been submitted yet. Be the first to review our services!
        </div>
      `;
    }
  }

})();