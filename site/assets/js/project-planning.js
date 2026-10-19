/* One bounded submitted milestone value; no customer-side build/runtime dependencies. */
(() => {
  'use strict';
  document.querySelectorAll('[data-planning-editor]').forEach(form => {
    if (form.dataset.editable !== '1') return;
    const raw = form.querySelector('[data-planning-data]');
    const items = JSON.parse(raw.value);
    const retained = new Set(JSON.parse(form.dataset.retained));
    const list = form.querySelector('[data-planning-list]');
    const panel = form.querySelector('[data-planning-selected]');
    const fields = [...form.querySelectorAll('[data-planning-field]')];
    const dependencies = form.querySelector('[data-planning-dependencies]');
    const status = form.querySelector('[data-planning-status]');
    const add = form.querySelector('[data-planning-add]');
    const remove = form.querySelector('[data-planning-remove]');
    const earlier = form.querySelector('[data-planning-earlier]');
    const later = form.querySelector('[data-planning-later]');
    let selected = items.length ? items[0].id : null;
    const current = () => items.find(item => item.id === selected);
    const serialize = () => {
      raw.value = JSON.stringify(items).replace(/\u2028/g, '\\u2028').replace(/\u2029/g, '\\u2029');
    };
    const choices = () => {
      list.replaceChildren();
      items.forEach((item, index) => {
        const li = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'planning-choice';
        button.textContent = `${index + 1}. ${item.title || 'Untitled milestone'}`;
        button.setAttribute('aria-pressed', String(item.id === selected));
        button.addEventListener('click', () => { selected = item.id; show(); });
        li.append(button); list.append(li);
      });
      add.disabled = items.length >= 100 || !globalThis.crypto?.getRandomValues;
    };
    const show = () => {
      choices(); const item = current(); panel.hidden = !item;
      if (!item) return;
      fields.forEach(control => {
        const value = item[control.dataset.planningField] ?? '';
        if (control.tagName === 'SELECT') {
          control.querySelectorAll('[data-invalid-option]').forEach(option => option.remove());
          if (![...control.options].some(option => option.value === value)) {
            const option = document.createElement('option'); option.value = value;
            option.textContent = `Choose a valid value (was: ${value})`; option.dataset.invalidOption = '1'; control.append(option);
          }
        }
        control.value = value;
      });
      dependencies.replaceChildren();
      const available = items.filter(other => other.id !== item.id);
      item.depends_on.forEach(id => {
        if (!available.some(other => other.id === id)) available.push({id, title: 'Invalid prerequisite — clear this choice'});
      });
      if (!available.length) {
        const p = document.createElement('p'); p.textContent = 'Add another milestone to choose a prerequisite.'; dependencies.append(p);
      }
      available.forEach(other => {
        const label = document.createElement('label'); const checkbox = document.createElement('input');
        checkbox.type = 'checkbox'; checkbox.value = other.id; checkbox.checked = item.depends_on.includes(other.id);
        checkbox.addEventListener('change', () => {
          const selectedIds = [...dependencies.querySelectorAll('input:checked')].map(input => input.value);
          if (selectedIds.length > 8) { checkbox.checked = false; status.textContent = 'Choose no more than eight prerequisites.'; return; }
          item.depends_on = [...new Set(selectedIds)].sort(); serialize(); status.textContent = 'Prerequisites updated in your unsaved plan.';
        });
        label.append(checkbox, document.createTextNode(other.title || 'Untitled milestone')); dependencies.append(label);
      });
      remove.hidden = retained.has(item.id);
      earlier.disabled = items.indexOf(item) === 0; later.disabled = items.indexOf(item) === items.length - 1;
    };
    fields.forEach(control => control.addEventListener('input', () => {
      const item = current(); if (!item) return;
      const key = control.dataset.planningField;
      item[key] = key === 'due_date' && control.value === '' ? null : control.value;
      serialize(); if (key === 'title') choices();
    }));
    add.addEventListener('click', () => {
      if (items.length >= 100 || !globalThis.crypto?.getRandomValues) return;
      const bytes = new Uint8Array(16); crypto.getRandomValues(bytes);
      const id = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
      if (items.some(item => item.id === id)) { status.textContent = 'Please try adding this milestone again.'; return; }
      items.push({id, title: '', details: '', state: 'planned', owner: 'studio', due_date: null, depends_on: []});
      selected = id; serialize(); show(); fields[0].focus(); status.textContent = 'New milestone added to your unsaved plan. Enter its title.';
    });
    remove.addEventListener('click', () => {
      const item = current(); if (!item || retained.has(item.id)) return;
      if (items.some(other => other.depends_on.includes(item.id))) { status.textContent = 'Clear prerequisites referring to this milestone before removing it.'; return; }
      const index = items.indexOf(item); items.splice(index, 1); selected = items[Math.min(index, items.length - 1)]?.id ?? null;
      serialize(); show(); status.textContent = 'Unsaved milestone removed.'; (list.querySelector('[aria-pressed="true"]') || add).focus();
    });
    const move = direction => {
      const item = current(); if (!item) return; const index = items.indexOf(item); const target = index + direction;
      if (target < 0 || target >= items.length) return;
      [items[index], items[target]] = [items[target], items[index]]; serialize(); show(); status.textContent = 'Milestone order updated in your unsaved plan.';
    };
    earlier.addEventListener('click', () => move(-1)); later.addEventListener('click', () => move(1));
    form.addEventListener('submit', event => {
      serialize();
      if (new TextEncoder().encode(raw.value).length > 262144) { event.preventDefault(); status.textContent = 'Milestone details are too large. Shorten the details before saving.'; }
    });
    form.querySelector('[data-planning-controls]').disabled = false;
    form.querySelector('[data-planning-help]').textContent = 'Edit milestones below. Changes are published only when you save the project plan.';
    show();
  });
})();
