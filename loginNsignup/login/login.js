document.addEventListener("DOMContentLoaded", function() {
    // Get the URL parameters (e.g., ?error=invalid)
    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.has('error')) {
        const errorType = urlParams.get('error');

        if (errorType === 'invalid') {
            alert("Login Failed: Incorrect email or password. Please try again.");
        } 
        else if (errorType === 'locked') {
            const time = urlParams.get('time') || '3';
            alert(`Security Lock: Too many failed attempts. Your account is locked. Please try again in ${time} minute(s).`);
        }

        // Clean the URL so the alert doesn't pop up again if they refresh the page
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    // Handle signup success message
if (urlParams.get('signup') === 'success') {
    const box = document.createElement('p');
    box.className = 'mt-2 mb-2 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700 text-center';
    box.textContent = '✅ Account created! Please log in with your new credentials.';
    document.querySelector('form').prepend(box);

    // Clean the URL
    window.history.replaceState({}, document.title, window.location.pathname);
}
});