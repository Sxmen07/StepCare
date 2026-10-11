// signup.js
(function () {
  const form      = document.getElementById('signupForm');
  const step1     = document.getElementById('step1');
  const step2     = document.getElementById('step2');
  const toStep2   = document.getElementById('toStep2');
  const toStep1   = document.getElementById('toStep1');
  const ind1      = document.getElementById('stepIndicator1');
  const ind2      = document.getElementById('stepIndicator2');

  const password    = document.getElementById('password');
  const confirmPwd  = document.getElementById('confirmPassword');

  /* ============================================= */
  /* STEP NAVIGATION                                */
  /* ============================================= */
  function showStep2() {
    // Validate step 1 fields
    if (!validateStep1()) return;

    step1.classList.add('hidden');
    step2.classList.remove('hidden');
    step2.classList.add('flex');

    // Update indicators
    ind1.querySelector('span:first-child').className =
      'w-8 h-8 rounded-full bg-[#10B981] text-white flex items-center justify-center text-sm font-bold';
    ind1.querySelector('span:last-child').className = 'text-sm font-semibold text-[#10B981]';

    ind2.querySelector('span:first-child').className =
      'w-8 h-8 rounded-full bg-[#3C52C3] text-white flex items-center justify-center text-sm font-bold';
    ind2.querySelector('span:last-child').className = 'text-sm font-semibold text-[#3C52C3]';
  }

  function showStep1() {
    step2.classList.add('hidden');
    step2.classList.remove('flex');
    step1.classList.remove('hidden');

    ind1.querySelector('span:first-child').className =
      'w-8 h-8 rounded-full bg-[#3C52C3] text-white flex items-center justify-center text-sm font-bold';
    ind1.querySelector('span:last-child').className = 'text-sm font-semibold text-[#3C52C3]';

    ind2.querySelector('span:first-child').className =
      'w-8 h-8 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-sm font-bold';
    ind2.querySelector('span:last-child').className = 'text-sm font-semibold text-slate-400';
  }

  toStep2.addEventListener('click', showStep2);
  toStep1.addEventListener('click', showStep1);

  /* ============================================= */
  /* STEP 1 VALIDATION                              */
  /* ============================================= */
  function validateStep1() {
    const required = [
      ['firstName',           'first name'],
      ['lastName',            'last name'],
      ['email',               'email'],
      ['phoneNumber',         'phone number'],
      ['emergencyContactName','emergency contact name'],
      ['emergencyContactPhone','emergency contact phone'],
    ];

    for (const [id, label] of required) {
      const el = document.getElementById(id);
      if (!el.value.trim()) {
        alert('Please enter your ' + label + '.');
        el.focus();
        return false;
      }
    }

    const email = document.getElementById('email').value.trim();
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!re.test(email)) {
      alert('Please enter a valid email address.');
      document.getElementById('email').focus();
      return false;
    }

    return true;
  }

  /* ============================================= */
  /* PASSWORD REQUIREMENTS LIVE CHECK               */
  /* ============================================= */
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
      li.classList.remove('text-slate-400');
      li.classList.add('text-green-600', 'font-medium');
      dot.classList.remove('bg-slate-300');
      dot.classList.add('bg-green-500');
    } else {
      li.classList.remove('text-green-600', 'font-medium');
      li.classList.add('text-slate-400');
      dot.classList.remove('bg-green-500');
      dot.classList.add('bg-slate-300');
    }
  }

  password.addEventListener('input', function () {
    const v = password.value;
    for (const key in reqs) {
      updateReq(key, reqs[key].test(v));
    }
  });

  /* ============================================= */
  /* SUBMIT VALIDATION                              */
  /* ============================================= */
  form.addEventListener('submit', function (e) {
    // Make sure we're on step 2 (submit can only come from there)
    const v = password.value;

    if (v === '') {
      e.preventDefault();
      alert('Please enter your password.');
      password.focus();
      return;
    }
    if (v.length < 8) {
      e.preventDefault();
      alert('Password must be at least 8 characters.');
      password.focus();
      return;
    }
    if (!/[a-zA-Z]/.test(v) || !/\d/.test(v) || !/[^a-zA-Z0-9]/.test(v)) {
      e.preventDefault();
      alert('Please satisfy all password requirements.');
      password.focus();
      return;
    }
    if (confirmPwd.value === '') {
      e.preventDefault();
      alert('Please confirm your password.');
      confirmPwd.focus();
      return;
    }
    if (v !== confirmPwd.value) {
      e.preventDefault();
      alert('Passwords do not match.');
      confirmPwd.focus();
      return;
    }
  });

  /* ============================================= */
  /* SHOW SERVER-SIDE ERRORS (?error=...)           */
  /* ============================================= */
  const params = new URLSearchParams(location.search);
  const error  = params.get('error');
  if (error) {
    const messages = {
      exists:    'That email is already registered. Try logging in instead.',
      reserved:  'That email domain is reserved for staff.',
      mismatch:  'Passwords did not match. Please try again.',
      short:     'Password must be at least 8 characters.',
      weak:      'Password must include a letter, a number, and a symbol.',
      invalid:   'Please enter a valid email address.',
      empty:     'Please fill in all required fields.',
      server:    'Something went wrong. Please try again later.',
    };
    alert(messages[error] || 'Something went wrong. Please try again.');
    history.replaceState({}, document.title, location.pathname);
  }
})();