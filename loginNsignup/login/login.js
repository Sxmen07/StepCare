document.addEventListener("DOMContentLoaded", function () {
  /* ============================================================
     1. SLIDE ANIMATION (LOGIN <-> SIGNUP)
     ============================================================ */
  const loginPanel  = document.getElementById('loginPanel');
  const signupPanel = document.getElementById('signupPanel');
  const toSignupBtn = document.getElementById('toSignupBtn');
  const toLoginBtn  = document.getElementById('toLoginBtn');
  const backHomeBtn = document.getElementById('backHomeBtn');

  function showSignup() {
    // 1. Force the form back to Step 1
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step2').classList.remove('flex');
    document.getElementById('step1').classList.remove('hidden');
    updateIndicators(1);

    // 2. Toggle Top-Left Buttons (Strictly remove 'flex' when hiding)
    backHomeBtn.classList.add('hidden');
    backHomeBtn.classList.remove('flex');
    
    toLoginBtn.classList.remove('hidden');
    toLoginBtn.classList.add('flex');

    // 3. Slide Login out to left & fade
    loginPanel.classList.remove('translate-x-0', 'opacity-100', 'relative');
    loginPanel.classList.add('-translate-x-full', 'opacity-0', 'pointer-events-none', 'absolute', 'inset-0');
    
    // 4. Slide Signup in from right
    signupPanel.classList.remove('translate-x-full', 'opacity-0', 'pointer-events-none', 'absolute', 'inset-0');
    signupPanel.classList.add('translate-x-0', 'opacity-100', 'relative');
    
    history.replaceState({}, document.title, location.pathname + "?mode=signup");
  }

  function showLogin() {
    // 1. Toggle Top-Left Buttons (Strictly remove 'flex' when hiding)
    backHomeBtn.classList.remove('hidden');
    backHomeBtn.classList.add('flex');
    
    toLoginBtn.classList.add('hidden');
    toLoginBtn.classList.remove('flex');

    // 2. Slide Signup out to right & fade
    signupPanel.classList.remove('translate-x-0', 'opacity-100', 'relative');
    signupPanel.classList.add('translate-x-full', 'opacity-0', 'pointer-events-none', 'absolute', 'inset-0');
    
    // 3. Slide Login in from left
    loginPanel.classList.remove('-translate-x-full', 'opacity-0', 'pointer-events-none', 'absolute', 'inset-0');
    loginPanel.classList.add('translate-x-0', 'opacity-100', 'relative');
    
    history.replaceState({}, document.title, location.pathname);
  }

  toSignupBtn.addEventListener('click', showSignup);
  toLoginBtn.addEventListener('click', showLogin);



  // If URL has ?mode=signup (e.g. returning from an error), show signup
  const params = new URLSearchParams(location.search);
  if (params.get('mode') === 'signup' || params.has('error')) {
    // If it's a signup error, show signup
    if (params.get('error') && !params.get('error').includes('invalid')) {
      showSignup();
    }
    // If it's a login error, stay on login but show alert
        if (params.get('error')) {
        const errorType = params.get('error');
        if (errorType === 'invalid') alert("Login Failed: Incorrect email or password.");
        else if (errorType === 'locked') alert(`Security Lock: Too many failed attempts. Try again in ${params.get('time') || 3} mins.`);
        else if (errorType === 'exists') alert("Email already registered. Try logging in.");
        else if (errorType === 'reserved') alert("This email domain is reserved.");
        else if (errorType === 'mismatch') alert("Passwords do not match.");
        else if (errorType === 'terms') alert("You must agree to the Terms and Conditions.");
        else if (errorType === 'weak') alert("Password must contain at least 1 letter, 1 number, and 1 symbol.");
        else if (errorType === 'short') alert("Password must be at least 8 characters.");
        
        window.history.replaceState({}, document.title, location.pathname);
    }
  }

  /* ============================================================
     2. TOGGLE PASSWORD VISIBILITY (EYE ICON)
     ============================================================ */
  document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function() {
      const targetId = this.getAttribute('data-target');
      const input = document.getElementById(targetId);
      const eyeOpen = this.querySelector('.icon-eye');
      const eyeClosed = this.querySelector('.icon-eye-slash');

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
     3. SIGNUP STEP NAVIGATION
     ============================================================ */
  const form      = document.getElementById('signupForm');
  const step1     = document.getElementById('step1');
  const step2     = document.getElementById('step2');
  const toStep2   = document.getElementById('toStep2');
  const toStep1   = document.getElementById('toStep1');
  const ind1      = document.getElementById('stepIndicator1');
  const ind2      = document.getElementById('stepIndicator2');

  const password    = document.getElementById('password');
  const confirmPwd  = document.getElementById('confirmPassword');
  const termsBox    = document.getElementById('terms');

  function updateIndicators(activeStep) {
    if (activeStep === 1) {
      ind1.querySelector('span:first-child').className = 'w-8 h-8 rounded-full bg-[#3C52C3] text-white flex items-center justify-center text-sm font-bold transition-colors';
      ind1.querySelector('span:last-child').className = 'text-sm font-semibold text-[#3C52C3] transition-colors';
      ind2.querySelector('span:first-child').className = 'w-8 h-8 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-sm font-bold transition-colors';
      ind2.querySelector('span:last-child').className = 'text-sm font-semibold text-slate-400 transition-colors';
    } else {
      ind1.querySelector('span:first-child').className = 'w-8 h-8 rounded-full bg-[#10B981] text-white flex items-center justify-center text-sm font-bold transition-colors';
      ind1.querySelector('span:last-child').className = 'text-sm font-semibold text-[#10B981] transition-colors';
      ind2.querySelector('span:first-child').className = 'w-8 h-8 rounded-full bg-[#3C52C3] text-white flex items-center justify-center text-sm font-bold transition-colors';
      ind2.querySelector('span:last-child').className = 'text-sm font-semibold text-[#3C52C3] transition-colors';
    }
  }

  toStep2.addEventListener('click', () => {
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const email = document.getElementById('email').value.trim();
    const phoneNumber = document.getElementById('phoneNumber').value.trim();
    const ic = document.getElementById('IC').value.trim();
    const address = document.getElementById('address').value.trim();
    const emergencyName = document.getElementById('emergencyContactName').value.trim();
    const emergencyPhone = document.getElementById('emergencyContactPhone').value.trim();

    // 1. Required fields check
    if (!firstName || !lastName || !email || !phoneNumber || !emergencyName || !emergencyPhone) {
      alert('Please fill out all required fields.');
      return;
    }

    // 2. First name & Last name: No numbers
    const nameRegex = /^[A-Za-z\s]+$/;
    if (!nameRegex.test(firstName) || !nameRegex.test(lastName)) {
      alert('First name and Last name must not contain numbers or special characters.');
      document.getElementById('firstName').focus();
      return;
    }

    // 3. Email must end with @gmail.com
    if (!email.endsWith('@gmail.com')) {
      alert('Email must end with @gmail.com');
      document.getElementById('email').focus();
      return;
    }

    // 4. Phone number: Numeric only (no hyphens)
    const phoneRegex = /^\d+$/;
    if (!phoneRegex.test(phoneNumber)) {
      alert('Phone number must be numeric only (no dashes/hyphens).');
      document.getElementById('phoneNumber').focus();
      return;
    }

    // 5. IC validation & Auto-fill DOB
    if (ic !== '') {
      if (!/^\d{12}$/.test(ic)) {
        alert('IC must be 12 digits numeric without hyphens.');
        document.getElementById('IC').focus();
        return;
      }

      // Extract Year, Month, Day from IC (e.g., 031109010001 -> yy = 03, mm = 11, dd = 09)
      const yy = parseInt(ic.substring(0, 2), 10);
      const mm = ic.substring(2, 4);
      const dd = ic.substring(4, 6);

      // Assume 20xx for 00-49, 19xx for 50-99
      const fullYear = yy <= 50 ? 2000 + yy : 1900 + yy;
      
      // Auto-set DOB field if it exists in your form
      const dobInput = document.getElementById('dateOfBirth');
      if (dobInput) {
        dobInput.value = `${fullYear}-${mm}-${dd}`;
      }
    }

    // 6. Address: Cannot be all numbers, must contain text and commas
    if (address !== '') {
      if (/^\d+$/.test(address)) {
        alert('Address cannot be all numbers.');
        document.getElementById('address').focus();
        return;
      }
      if (!/[A-Za-z]/.test(address)) {
        alert('Address must contain text characters.');
        document.getElementById('address').focus();
        return;
      }
    }

    // 7. Emergency contact name: Text only
    if (!nameRegex.test(emergencyName)) {
      alert('Emergency contact name must contain text only.');
      document.getElementById('emergencyContactName').focus();
      return;
    }

    // 8. Emergency contact phone: Numeric only
    if (!phoneRegex.test(emergencyPhone)) {
      alert('Emergency contact phone must be numeric only.');
      document.getElementById('emergencyContactPhone').focus();
      return;
    }

    // Proceed to Step 2 if all checks pass
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
     4. PASSWORD REQUIREMENTS LIVE CHECK
     ============================================================ */
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
      dot.className = 'dot w-4 h-4 rounded-full bg-green-500 flex items-center justify-center text-white text-[9px]';
    } else {
      li.className = 'flex items-center gap-1.5 text-slate-400 transition';
      dot.className = 'dot w-4 h-4 rounded-full bg-slate-300 flex items-center justify-center text-white text-[9px]';
    }
  }

  password.addEventListener('input', function () {
    const v = password.value;
    for (const key in reqs) updateReq(key, reqs[key].test(v));
  });

  /* ============================================================
     5. FORM SUBMIT VALIDATION
     ============================================================ */
  form.addEventListener('submit', function (e) {
    const v = password.value;
    
    if (v.length < 8 || !/[a-zA-Z]/.test(v) || !/\d/.test(v) || !/[^a-zA-Z0-9]/.test(v)) {
      e.preventDefault();
      alert('Please satisfy all password requirements.');
      password.focus();
      return;
    }
    
    if (v !== confirmPwd.value) {
      e.preventDefault();
      alert('Passwords do not match.');
      confirmPwd.focus();
      return;
    }

    if (!termsBox.checked) {
      e.preventDefault();
      alert('You must agree to the Terms and Conditions to create an account.');
      termsBox.focus();
      return;
    }
  });
});