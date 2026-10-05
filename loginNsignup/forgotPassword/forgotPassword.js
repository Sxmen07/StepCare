// forgotPassword.js
(function () {
  const params = new URLSearchParams(location.search);
  const form   = document.querySelector('form');

  /* ============ SUCCESS MESSAGE ============ */
  if (params.get('sent') === '1') {
    const box = document.createElement('p');
    box.className = 'mt-6 rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700 text-center';
    box.textContent = '✅ If that email is registered, a reset link is on its way. Check your inbox (and spam folder).';
    form.after(box);

    // Clean the URL so refresh doesn't keep showing the message
    history.replaceState({}, document.title, location.pathname);
  }

  /* ============ ERROR MESSAGES ============ */
  const error = params.get('error');
  if (error) {
    const messages = {
      mail_failed: 'We could not send the email right now. Please try again later.',
      server:      'Something went wrong on our side. Please try again.',
      bad_input:   'Please enter a valid email address.',
    };

    const box = document.createElement('p');
    box.className = 'mt-6 rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700 text-center';
    box.textContent = messages[error] || 'Something went wrong. Please try again.';
    form.after(box);

    history.replaceState({}, document.title, location.pathname);
  }

  /* ============ CLIENT-SIDE VALIDATION ============ */
  form.addEventListener('submit', function (e) {
    const email = document.getElementById('email').value.trim();

    if (!email) {
      e.preventDefault();
      alert('Please enter your email address.');
      return;
    }

    // Optional: basic format check (the input type="email" already helps)
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!re.test(email)) {
      e.preventDefault();
      alert('Please enter a valid email address.');
    }
  });
})();