(() => {
  'use strict';
  const config = window.IEOD_CONFIG || { routes: {}, currentSlug: '', storagePrefix: 'ieod_demo_' };
  const root = document.documentElement;
  const prefix = config.storagePrefix || 'ieod_demo_';
  const route = (slug) => config.routes?.[slug] || `/${slug}/`;
  const read = (key, fallback = '') => localStorage.getItem(prefix + key) ?? fallback;
  const write = (key, value) => localStorage.setItem(prefix + key, String(value));

  const storedTheme = read('theme', 'light');
  root.dataset.ieodTheme = storedTheme === 'dark' ? 'dark' : 'light';
  const updateThemeButtons = () => document.querySelectorAll('[data-ieod-theme-toggle]').forEach((button) => {
    button.textContent = root.dataset.ieodTheme === 'dark' ? '☀' : '◐';
  });
  updateThemeButtons();

  document.addEventListener('click', (event) => {
    const themeButton = event.target.closest('[data-ieod-theme-toggle]');
    if (themeButton) {
      event.preventDefault();
      event.stopPropagation();
      const next = root.dataset.ieodTheme === 'dark' ? 'light' : 'dark';
      root.dataset.ieodTheme = next;
      write('theme', next);
      updateThemeButtons();
      return;
    }

    const choice = event.target.closest('[data-ieod-go]');
    if (choice) {
      ['os', 'location', 'office'].forEach((key) => {
        const value = choice.dataset[`ieod${key[0].toUpperCase()}${key.slice(1)}`];
        if (value) write(key, value);
      });
      window.location.assign(route(choice.dataset.ieodGo));
      return;
    }

    if (event.target.closest('[data-ieod-back]')) history.back();
    if (event.target.closest('[data-ieod-next]')) goNext();
    if (event.target.closest('[data-ieod-tutorial]')) openTutorial();
    if (event.target.closest('[data-ieod-scope]')) openScope();
    if (event.target.closest('[data-ieod-modal-close]')) closeModal();
  });

  function sequence() {
    if (config.flow === 'guest') return ['guest-user', 'verify-email-code', 'accept-permissions', '2fa-guest'];
    const os = read('os', config.currentSlug.includes('mac') ? 'mac' : 'windows');
    const location = read('location', 'office');
    const software = os === 'mac' ? 'self-service' : 'company-portal';
    const base = ['onboarding', `location-${os}`];
    if (location === 'office') base.push(`office-selection-${os}`);
    base.push(`password-change-${os}`, `2fa-${os}`);
    if (location === 'remote') base.push(`vpn-${os}`);
    base.push(`signature-${os}`, software, 'toolbox', 'it-policy', 'phishing', 'it-contact');
    if (location !== 'remote') base.splice(base.indexOf(`signature-${os}`), 0, `vpn-${os}`);
    return base;
  }

  function goNext() {
    const steps = sequence();
    const index = steps.indexOf(config.currentSlug);
    if (index >= 0 && index < steps.length - 1) window.location.assign(route(steps[index + 1]));
  }

  function modal(title, body, actions = '') {
    closeModal();
    const wrapper = document.createElement('div');
    wrapper.className = 'ieod-modal';
    wrapper.innerHTML = `<div class="ieod-modal__shade" data-ieod-modal-close></div><section class="ieod-modal__card" role="dialog" aria-modal="true" aria-labelledby="ieod-modal-title"><button class="ieod-modal__x" data-ieod-modal-close aria-label="Close">×</button><h2 id="ieod-modal-title">${title}</h2>${body}<div class="ieod-modal__actions">${actions}<button class="ieod-btn ieod-btn--primary" data-ieod-modal-close>Got it</button></div></section>`;
    document.body.appendChild(wrapper);
    requestAnimationFrame(() => wrapper.classList.add('is-open'));
  }

  function closeModal() {
    document.querySelectorAll('.ieod-modal').forEach((node) => node.remove());
  }

  function openScope() {
    modal('Public demo scope', '<p>This portfolio version demonstrates the user journey and technical implementation. Real company links, media, credentials, password changes, MFA registration, VPN profiles and device-management actions are not included.</p>');
  }

  function openTutorial() {
    const items = [
      ['Flow navigation', 'The left column shows the current onboarding stage.'],
      ['Guided content', 'The main area contains fictionalized setup steps.'],
      ['Back and Next', 'Use the fixed footer to move through the selected route.'],
      ['Theme control', 'Only the moon/sun button switches the color theme.'],
    ];
    let index = 0;
    const show = () => {
      const [title, text] = items[index];
      const actions = index > 0 ? '<button class="ieod-btn" id="ieod-tour-prev">Back</button>' : '';
      modal(`${index + 1}/${items.length} · ${title}`, `<p>${text}</p>`, actions + (index < items.length - 1 ? '<button class="ieod-btn" id="ieod-tour-next">Next</button>' : ''));
      document.getElementById('ieod-tour-prev')?.addEventListener('click', () => { index -= 1; show(); });
      document.getElementById('ieod-tour-next')?.addEventListener('click', () => { index += 1; show(); });
    };
    show();
  }
})();
