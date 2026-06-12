// Show/hide mailer-specific sections
function toggleMailerOptions() {
    const mailer = document.getElementById('mailer').value;
    const sendmailSection = document.getElementById('sendmail-path-section');
    const smtpSection = document.getElementById('smtp-section');

    // Hide both sections by default
    sendmailSection.style.display = 'none';
    smtpSection.style.display = 'none';

    if (mailer === 'sendmail') {
        sendmailSection.style.display = 'block';
    } else if (mailer === 'smtp') {
        smtpSection.style.display = 'block';
    }
}

// On page load, call toggleMailerOptions to set initial visibility
document.addEventListener('DOMContentLoaded', () => {
    toggleMailerOptions();
});