document.addEventListener("DOMContentLoaded", function () {
  /* ============================================================
     ELEMENT REFERENCES
     ============================================================ */
  const loginPanel  = document.getElementById('loginPanel');
  const signupPanel = document.getElementById('signupPanel');
  const toSignupBtn = document.getElementById('toSignupBtn');
  const toLoginBtn  = document.getElementById('toLoginBtn');
  const backHomeBtn = document.getElementById('backHomeBtn');

  const step1   = document.getElementById('step1');
  const step2   = document.getElementById('step2');
  const toStep2 = document.getElementById('toStep2');
  const toStep1 = document.getElementById('toStep1');
  const ind1    = document.getElementById('stepIndicator1');
  const ind2    = document.getElementById('stepIndicator2');

  const loginToastArea  = document.getElementById('loginToastArea');
  const signupToastArea = document.getElementById('signupToastArea');

  /* Which panel is currently visible — determines where toasts land */
  let activePanel = 'login';

  function getToastArea() {
    return activePanel === 'signup' ? signupToastArea : loginToastArea;
  }

  /* ============================================================
     1. TOAST SYSTEM  (floating above the title, non-blocking)
     ============================================================ */
  const ICONS = {
    error: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>`,
    lock: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>`,
    success: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>`,
    close: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>`,
  };

  const TONES = {
    error: {
      border: 'border-orange-200',
      iconWrap: 'bg-orange-50 text-orange-600',
      title: 'text-orange-900',
      msg: 'text-orange-700',
      bar: 'bg-orange-400',
    },
    lock: {
      border: 'border-slate-300',
      iconWrap: 'bg-slate-100 text-slate-600',
      title: 'text-slate-900',
      msg: 'text-slate-600',
      bar: 'bg-slate-400',
    },
    success: {
      border: 'border-emerald-200',
      iconWrap: 'bg-emerald-50 text-emerald-600',
      title: 'text-emerald-900',
      msg: 'text-emerald-700',
      bar: 'bg-emerald-400',
    },
  };

  function dismissToast(el) {
    if (!el.isConnected || el.classList.contains('toast-out')) return;
    el.classList.add('toast-out');
    const remove = () => el.isConnected && el.remove();
    el.addEventListener('animationend', remove, { once: true });
    setTimeout(remove, 500);
  }

  function clearArea(area) {
    if (area) area.innerHTML = '';
  }

  /**
   * Show a standard toast above the current panel's title.
   * @param {{ title: string, message?: string, tone?: 'error'|'lock'|'success', duration?: number }} opts
   */
  function showToast({ title, message = '', tone = 'error', duration = 6000 }) {
    const t = TONES[tone];
    const icon = tone === 'success' ? ICONS.success : tone === 'lock' ? ICONS.lock : ICONS.error;

    const el = document.createElement('div');
    el.className =
      `pointer-events-auto relative w-full overflow-hidden rounded-xl border bg-white shadow-lg toast-in ${t.border}`;
    el.setAttribute('role', tone === 'success' || tone === 'lock' ? 'status' : 'alert');

    el.innerHTML = `
      <div class="flex items-start gap-3 p-4 pr-11">
        <div class="flex-shrink-0 flex h-9 w-9 items-center justify-center rounded-full ${t.iconWrap}">
          ${icon}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold ${t.title}">${title}</p>
          ${message ? `<p class="mt-0.5 text-sm ${t.msg}">${message}</p>` : ''}
        </div>
      </div>
      <button type="button" class="toast-close absolute right-2 top-2 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition" aria-label="Dismiss notification">
        ${ICONS.close}
      </button>
      <div class="h-1 w-full bg-slate-100">
        <div class="toast-bar h-full ${t.bar}" style="--toast-duration:${duration}ms"></div>
      </div>
    `;

    let dismissTimer = null;
    const close = () => {
      clearTimeout(dismissTimer);
      dismissToast(el);
    };

    el.querySelector('.toast-close').addEventListener('click', close);
    getToastArea().appendChild(el);

    if (duration > 0) {
      dismissTimer = setTimeout(close, duration);
    }
  }

  /**
   * Persistent lockout toast with a big countdown on the right.
   * @param {{ minutes: number }} opts
   */
  function showLockoutToast({ minutes }) {
    let totalSeconds     = Math.max(1, Math.round(minutes * 60));
    const initialSeconds = totalSeconds;

    const el = document.createElement('div');
    el.className =
      'pointer-events-auto relative w-full overflow-hidden rounded-xl border border-slate-300 bg-white shadow-lg toast-in';
    el.setAttribute('role', 'alert');

    el.innerHTML = `
      <button type="button" class="toast-close absolute right-2 top-2 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition" aria-label="Dismiss notification">
        ${ICONS.close}
      </button>
      <div class="flex items-center gap-4 p-4 pr-11">
        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600">
          ${ICONS.lock}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-slate-900">Account temporarily locked</p>
          <p class="mt-0.5 text-sm text-slate-600">Too many failed attempts.</p>
        </div>
        <div class="flex-shrink-0 text-right">
          <p class="text-[10px] uppercase tracking-wider font-semibold text-slate-400">Try again in</p>
          <div class="lock-timer mt-1 text-4xl font-bold tabular-nums leading-none text-slate-900">--:--</div>
        </div>
      </div>
      <div class="h-1 w-full bg-slate-100">
        <div class="lock-bar h-full bg-slate-400 transition-[width] duration-1000 ease-linear" style="width:100%"></div>
      </div>
    `;

    const timerEl = el.querySelector('.lock-timer');
    const barEl   = el.querySelector('.lock-bar');

    const render = () => {
      const m = Math.floor(totalSeconds / 60);
      const s = totalSeconds % 60;
      timerEl.textContent = `${m}:${String(s).padStart(2, '0')}`;
      barEl.style.width = `${(totalSeconds / initialSeconds) * 100}%`;
    };
    render();

    let intervalId = setInterval(() => {
      totalSeconds--;
      if (totalSeconds <= 0) {
        clearInterval(intervalId);
        timerEl.textContent = 'Ready';
        timerEl.classList.remove('text-slate-900', 'text-4xl');
        timerEl.classList.add('text-emerald-600', 'text-2xl');
        barEl.style.width = '0%';
        setTimeout(() => dismissToast(el), 4000);
        return;
      }
      render();
    }, 1000);

    el.querySelector('.toast-close').addEventListener('click', () => {
      clearInterval(intervalId);
      dismissToast(el);
    });

    getToastArea().appendChild(el);
  }

  /* ============================================================
     2. PANEL SWITCHING (LOGIN <-> SIGNUP)
     ============================================================ */
  function updateIndicators(activeStep) {
    if (activeStep === 1) {
      ind1.querySelector('span:first-child').className = 'w-7 h-7 rounded-full bg-[#3C52C3] text-white flex items-center justify-center text-xs font-bold transition-colors';
      ind1.querySelector('span:last-child').className  = 'text-xs font-semibold text-[#3C52C3] transition-colors';
      ind2.querySelector('span:first-child').className = 'w-7 h-7 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-xs font-bold transition-colors';
      ind2.querySelector('span:last-child').className  = 'text-xs font-semibold text-slate-400 transition-colors';
    } else {
      ind1.querySelector('span:first-child').className = 'w-7 h-7 rounded-full bg-[#10B981] text-white flex items-center justify-center text-xs font-bold transition-colors';
      ind1.querySelector('span:last-child').className  = 'text-xs font-semibold text-[#10B981] transition-colors';
      ind2.querySelector('span:first-child').className = 'w-7 h-7 rounded-full bg-[#3C52C3] text-white flex items-center justify-center text-xs font-bold transition-colors';
      ind2.querySelector('span:last-child').className  = 'text-xs font-semibold text-[#3C52C3] transition-colors';
    }
  }

  function showSignup() {
    activePanel = 'signup';
    clearArea(loginToastArea);

    step2.classList.add('hidden');
    step2.classList.remove('flex');
    step1.classList.remove('hidden');
    updateIndicators(1);

    backHomeBtn.classList.add('hidden');
    backHomeBtn.classList.remove('flex');
    toLoginBtn.classList.remove('hidden');
    toLoginBtn.classList.add('flex');

    loginPanel.classList.remove('translate-x-0', 'opacity-100', 'relative');
    loginPanel.classList.add('-translate-x-full', 'opacity-0', 'pointer-events-none', 'absolute', 'inset-0');

    signupPanel.classList.remove('translate-x-full', 'opacity-0', 'pointer-events-none', 'absolute');
    signupPanel.classList.add('translate-x-0', 'opacity-100', 'relative');
  }

  function showLogin() {
    activePanel = 'login';
    clearArea(signupToastArea);

    backHomeBtn.classList.remove('hidden');
    backHomeBtn.classList.add('flex');
    toLoginBtn.classList.add('hidden');
    toLoginBtn.classList.remove('flex');

    signupPanel.classList.remove('translate-x-0', 'opacity-100', 'relative');
    signupPanel.classList.add('translate-x-full', 'opacity-0', 'pointer-events-none', 'absolute');

    loginPanel.classList.remove('-translate-x-full', 'opacity-0', 'pointer-events-none', 'absolute');
    loginPanel.classList.add('translate-x-0', 'opacity-100', 'relative');
  }

  toSignupBtn.addEventListener('click', showSignup);
  toLoginBtn.addEventListener('click', showLogin);

  /* ============================================================
     3. PASSWORD VISIBILITY TOGGLE
     ============================================================ */
  document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function () {
      const targetId = this.getAttribute('data-target');
      const input    = document.getElementById(targetId);
      const eyeOpen  = this.querySelector('.icon-eye');
      const eyeClosed= this.querySelector('.icon-eye-slash');

      if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.classList.add('hidden');
        eyeClosed.classList.remove('hidden');
      } else {
        input.type = 'password';
        eyeOpen.classList.remove('hidden');
        eyeClosed.classList.add('hidden');
      }
    });
  });

  /* ============================================================
     4. SIGNUP STEP NAVIGATION
     ============================================================ */
  toStep2.addEventListener('click', () => {
    const firstName      = document.getElementById('firstName').value.trim();
    const lastName       = document.getElementById('lastName').value.trim();
    const email          = document.getElementById('email').value.trim();
    const phoneNumber    = document.getElementById('phoneNumber').value.trim();
    const ic             = document.getElementById('IC').value.trim();
    const address        = document.getElementById('address').value.trim();
    const emergencyName  = document.getElementById('emergencyContactName').value.trim();
    const emergencyPhone = document.getElementById('emergencyContactPhone').value.trim();

    if (!firstName || !lastName || !email || !phoneNumber || !emergencyName || !emergencyPhone) {
      showToast({ title: 'Missing fields', message: 'Please fill out all required fields.', tone: 'error' });
      return;
    }

    const nameRegex = /^[A-Za-z\s]+$/;
    if (!nameRegex.test(firstName) || !nameRegex.test(lastName)) {
      showToast({ title: 'Invalid name', message: 'Names must not contain numbers.', tone: 'error' });
      return;
    }

    if (!email.endsWith('@gmail.com')) {
      showToast({ title: 'Invalid email', message: 'Email must end with @gmail.com', tone: 'error' });
      return;
    }

    const phoneRegex = /^\d+$/;
    if (!phoneRegex.test(phoneNumber)) {
      showToast({ title: 'Invalid phone', message: 'Phone number must be numeric only.', tone: 'error' });
      return;
    }

    if (ic !== '') {
      if (!/^\d{12}$/.test(ic)) {
        showToast({ title: 'Invalid IC', message: 'IC must be 12 digits numeric.', tone: 'error' });
        return;
      }
      const yy       = parseInt(ic.substring(0, 2), 10);
      const mm       = ic.substring(2, 4);
      const dd       = ic.substring(4, 6);
      const fullYear = yy <= 50 ? 2000 + yy : 1900 + yy;
      document.getElementById('dateOfBirth').value = `${fullYear}-${mm}-${dd}`;
    }

    if (address !== '') {
      if (/^\d+$/.test(address) || !/[A-Za-z]/.test(address)) {
        showToast({ title: 'Invalid address', message: 'Address must contain text characters.', tone: 'error' });
        return;
      }
    }

    if (!nameRegex.test(emergencyName)) {
      showToast({ title: 'Invalid emergency name', message: 'Must contain text only.', tone: 'error' });
      return;
    }

    if (!phoneRegex.test(emergencyPhone)) {
      showToast({ title: 'Invalid emergency phone', message: 'Must be numeric only.', tone: 'error' });
      return;
    }

    step1.classList.add('hidden');
    step2.classList.remove('hidden');
    step2.classList.add('flex');
    updateIndicators(2);
  });

  toStep1.addEventListener('click', () => {
    step2.classList.add('hidden');
    step2.classList.remove('flex');
    step1.classList.remove('hidden');
    updateIndicators(1);
  });

  /* ============================================================
     5. PASSWORD REQUIREMENTS LIVE CHECK
     ============================================================ */
  const password = document.getElementById('password');
  const reqs = {
    length: { el: document.getElementById('req-length'), test: v => v.length >= 8 },
    letter: { el: document.getElementById('req-letter'), test: v => /[a-zA-Z]/.test(v) },
    number: { el: document.getElementById('req-number'), test: v => /\d/.test(v) },
    symbol: { el: document.getElementById('req-symbol'), test: v => /[^a-zA-Z0-9]/.test(v) },
  };

  function updateReq(key, met) {
    const li  = reqs[key].el;
    const dot = li.querySelector('.dot');
    if (met) {
      li.className = 'flex items-center gap-1.5 text-green-600 font-medium transition';
      dot.className = 'dot w-3.5 h-3.5 rounded-full bg-green-500 flex items-center justify-center text-white text-[9px]';
    } else {
      li.className = 'flex items-center gap-1.5 text-slate-400 transition';
      dot.className = 'dot w-3.5 h-3.5 rounded-full bg-slate-300 flex items-center justify-center text-white text-[9px]';
    }
  }

  password.addEventListener('input', function () {
    const v = password.value;
    for (const key in reqs) updateReq(key, reqs[key].test(v));
  });

  /* ============================================================
     6. SIGNUP FORM SUBMIT VALIDATION
     ============================================================ */
  document.getElementById('signupForm').addEventListener('submit', function (e) {
    const v          = password.value;
    const confirmPwd = document.getElementById('confirmPassword').value;
    const termsBox   = document.getElementById('terms');

    if (v.length < 8 || !/[a-zA-Z]/.test(v) || !/\d/.test(v) || !/[^a-zA-Z0-9]/.test(v)) {
      e.preventDefault();
      showToast({ title: 'Weak password', message: 'Please satisfy all password requirements.', tone: 'error' });
      return;
    }

    if (v !== confirmPwd) {
      e.preventDefault();
      showToast({ title: 'Password mismatch', message: 'Passwords do not match.', tone: 'error' });
      return;
    }

    if (!termsBox.checked) {
      e.preventDefault();
      showToast({ title: 'Terms required', message: 'You must agree to the Terms and Conditions.', tone: 'error' });
      return;
    }
  });

  /* ============================================================
     7. HANDLE URL ERRORS / SUCCESS ON LOAD
        (runs LAST — every const/function above is initialised)
     ============================================================ */
  const params = new URLSearchParams(location.search);
  const error  = params.get('error');
  let handled  = false;

  if (error) {
    handled = true;

    if (error === 'locked') {
      showLogin();
      const mins = parseInt(params.get('time'), 10) || 3;
      showLockoutToast({ minutes: mins });

    } else if (error === 'wrong_email') {
      showLogin();
      showToast({
        title: 'Email not found',
        message: 'We could not find an account with that email address.',
        tone: 'error',
      });

    } else if (error === 'wrong_password') {
      showLogin();
      const attempt   = parseInt(params.get('attempt'), 10) || 1;
      const remaining = Math.max(0, 3 - attempt);
      showToast({
        title: 'Incorrect password',
        message: `The password you entered is incorrect. ${remaining} attempt(s) remaining before lockout.`,
        tone: 'error',
      });

    } else if ([
      'invalid', 'exists', 'reserved', 'mismatch', 'terms', 'weak', 'short',
      'empty', 'server', 'invalid_name', 'gmail_only', 'invalid_phone',
      'invalid_ic', 'invalid_address', 'invalid_emergency_name', 'invalid_emergency_phone',
    ].includes(error)) {
      showSignup();
      const messages = {
        exists:                 'This email is already registered. Try logging in instead.',
        reserved:               'This email domain is reserved for staff.',
        mismatch:               'Passwords do not match.',
        terms:                  'You must agree to the Terms and Conditions.',
        weak:                   'Password must be 8+ chars with a letter, number, and symbol.',
        short:                  'Password must be at least 8 characters.',
        empty:                  'Please fill out all required fields.',
        invalid:                'Please enter a valid email address.',
        server:                 'A server error occurred. Please try again.',
        invalid_name:           'First and Last name must contain letters only.',
        gmail_only:             'Email must end with @gmail.com',
        invalid_phone:          'Phone numbers must be numeric only.',
        invalid_ic:             'IC must be exactly 12 digits.',
        invalid_address:        'Address must contain text.',
        invalid_emergency_name: 'Emergency contact name must contain text.',
        invalid_emergency_phone:'Emergency contact phone must be numeric.',
      };
      showToast({
        title: 'Validation error',
        message: messages[error] || 'Please check your input.',
        tone: 'error',
      });
    }
  }

  if (params.get('signup') === 'success') {
    handled = true;
    showLogin();
    showToast({
      title: 'Account created successfully',
      message: 'You can now sign in with your new credentials.',
      tone: 'success',
      duration: 6000,
    });
  }

  if (handled) {
    window.history.replaceState({}, document.title, location.pathname);
  }
});