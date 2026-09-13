((Drupal, once) => {
  Drupal.behaviors.webformConfirmation = {
    attach: (context) => {
      const forms = once('webform-confirmation', 'form.webform-submission-update-webform-form', context);
      
      forms.forEach((form) => {
        const submitBtn = form.querySelector('[type="submit"]');
        
        if (submitBtn) {
          submitBtn.addEventListener('click', () => {
             // Create the confirmation message element
             const confirmationMsg = document.createElement('div');
             confirmationMsg.className = 'webform-confirmation-message';
             confirmationMsg.style.textAlign = 'center';
             confirmationMsg.style.padding = '30px 20px';
             confirmationMsg.style.background = '#ffffff';
             confirmationMsg.style.border = '1px solid #e0e0e0';
             confirmationMsg.style.borderRadius = '12px';
             confirmationMsg.style.boxShadow = '0 8px 24px rgba(0,0,0,0.08)';
             confirmationMsg.style.animation = 'conveneFadeIn 0.6s ease-out';
             confirmationMsg.style.width = '100%';
             confirmationMsg.style.boxSizing = 'border-box';
             
             confirmationMsg.innerHTML = `
                <h3 style="color: #0056b3; margin-bottom: 12px; font-family: sans-serif; font-size: 1.25rem;">Thank you for subscribing!</h3>
                <p style="color: #555; font-size: 0.95rem; line-height: 1.6; margin: 0;">We have received your email and will keep you updated with the latest event announcements and community news.</p>
             `;

             if (!document.getElementById('convene-webform-styles')) {
               const style = document.createElement('style');
               style.id = 'convene-webform-styles';
               style.textContent = `
                 @keyframes conveneFadeIn {
                   from { opacity: 0; transform: translateY(10px); }
                   to { opacity: 1; transform: translateY(0); }
                 }
               `;
               document.head.appendChild(style);
             }

             // Replace the form contents and remove the "circle" (pill) styles
             setTimeout(() => {
                form.innerHTML = '';
                form.appendChild(confirmationMsg);
                
                // Remove the container styles that cause the "background circle"
                form.style.background = 'transparent';
                form.style.border = 'none';
                form.style.padding = '0';
                form.style.borderRadius = '0';
                form.style.boxShadow = 'none';
                form.style.maxWidth = '480px'; // Keep the same width alignment
                form.style.margin = '0 auto 50px 0'; // Maintain spacing
             }, 10);
          });
        }
      });
    },
  };
})(Drupal, once);
