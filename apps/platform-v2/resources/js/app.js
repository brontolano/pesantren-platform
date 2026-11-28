const mobileDrawer = document.querySelector('[data-mobile-drawer]');
const drawerToggle = document.querySelector('[data-drawer-toggle]');
const drawerPanel = mobileDrawer?.querySelector('[role="dialog"]');
const drawerFocusables = () => [...(drawerPanel?.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])') ?? [])]
  .filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');

const closeDrawer = (restoreFocus = true) => {
  if (!mobileDrawer || mobileDrawer.hidden) return;
  mobileDrawer.hidden = true;
  drawerToggle?.setAttribute('aria-expanded', 'false');
  document.documentElement.classList.remove('pu-drawer-open');
  if (restoreFocus) drawerToggle?.focus({ preventScroll: true });
};

drawerToggle?.addEventListener('click', () => {
  if (!mobileDrawer || !drawerPanel) return;
  mobileDrawer.hidden = false;
  drawerToggle.setAttribute('aria-expanded', 'true');
  document.documentElement.classList.add('pu-drawer-open');
  (drawerFocusables()[0] ?? drawerPanel).focus({ preventScroll: true });
});

mobileDrawer?.addEventListener('click', (event) => {
  if (event.target.closest('[data-drawer-close]')) closeDrawer();
});

document.addEventListener('keydown', (event) => {
  if (!mobileDrawer || mobileDrawer.hidden) return;
  if (event.key === 'Escape') {
    event.preventDefault();
    closeDrawer();
    return;
  }
  if (event.key !== 'Tab') return;
  const focusables = drawerFocusables();
  if (!focusables.length) {
    event.preventDefault();
    drawerPanel?.focus();
    return;
  }
  const first = focusables[0];
  const last = focusables[focusables.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
});

window.matchMedia('(min-width: 48.001rem)').addEventListener('change', (event) => {
  if (event.matches) closeDrawer(false);
});

document.querySelectorAll('[data-onboarding-wizard]').forEach((form) => {
  const panels = [...form.querySelectorAll('[data-wizard-panel]')];
  const indicators = [...document.querySelectorAll('[data-wizard-indicator]')];
  const previous = form.querySelector('[data-wizard-previous]');
  const next = form.querySelector('[data-wizard-next]');
  const submit = form.querySelector('[data-wizard-submit]');
  if (!panels.length || !previous || !next || !submit) return;

  let current = Math.min(panels.length, Math.max(1, Number(form.dataset.errorStep) || 1));
  const typeLabels = {
    pesantren: 'Pesantren',
    yayasan: 'Yayasan',
    organisasi_islam: 'Organisasi Islam',
    majelis_taklim: 'Majelis taklim',
  };

  const updateReview = () => {
    for (const field of ['name', 'organization_type', 'canonical_domain', 'first_admin_email']) {
      const output = form.querySelector(`[data-review="${field}"]`);
      const input = form.elements.namedItem(field);
      if (!output || !input) continue;
      const value = input.value.trim();
      output.textContent = field === 'organization_type' ? (typeLabels[value] ?? 'Belum dipilih') : (value || 'Belum diisi');
    }
  };

  const show = (step, moveFocus = false) => {
    current = step;
    panels.forEach((panel) => { panel.hidden = Number(panel.dataset.wizardPanel) !== current; });
    indicators.forEach((indicator) => {
      if (Number(indicator.dataset.wizardIndicator) === current) indicator.setAttribute('aria-current', 'step');
      else indicator.removeAttribute('aria-current');
    });
    previous.hidden = current === 1;
    next.hidden = current === panels.length;
    submit.hidden = current !== panels.length;
    if (moveFocus) panels[current - 1].focus();
  };

  const invalidField = (panel) => [...panel.querySelectorAll('input, select, textarea')].find((field) => !field.checkValidity());
  const validatePanel = (panel) => {
    const invalid = invalidField(panel);
    if (!invalid) return true;
    invalid.reportValidity();
    invalid.focus();
    return false;
  };

  next.addEventListener('click', () => {
    if (!validatePanel(panels[current - 1])) return;
    updateReview();
    show(Math.min(panels.length, current + 1), true);
  });
  previous.addEventListener('click', () => show(Math.max(1, current - 1), true));
  form.addEventListener('input', updateReview);
  form.addEventListener('change', updateReview);
  form.addEventListener('submit', (event) => {
    for (let index = 0; index < panels.length; index++) {
      const invalid = invalidField(panels[index]);
      if (!invalid) continue;
      event.preventDefault();
      show(index + 1, true);
      invalid?.reportValidity();
      invalid?.focus();
      return;
    }
  });

  updateReview();
  show(current);
});
