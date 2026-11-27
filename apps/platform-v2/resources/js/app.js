document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-menu-toggle]');
  const menu = document.querySelector('[data-mobile-menu]');
  if (!button || !menu) return;
  const expanded = button.getAttribute('aria-expanded') === 'true';
  button.setAttribute('aria-expanded', String(!expanded));
  menu.hidden = expanded;
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
