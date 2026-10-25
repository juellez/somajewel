// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Jewel Mlnarik · https://github.com/juellez/somajewel
/* Unstruck Somatics practice app: front end. No build step, no dependencies. */
(() => {
  'use strict';

  const root = document.getElementById('app');
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

  let state = null;
  const openCards = new Set(); // which cards are expanded, kept across re-renders

  // ---------- helpers ----------

  async function api(action, { method = 'GET', body } = {}) {
    const res = await fetch(`${USP.api}?action=${encodeURIComponent(action)}`, {
      method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-USP-Nonce': USP.nonce },
      body: body ? JSON.stringify(body) : undefined,
    });
    if (res.status === 401 || res.status === 403) {
      window.location.reload(); // session or nonce expired: reload sends them to login
      throw new Error('auth');
    }
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'request_failed');
    return data;
  }

  /** Tiny element builder. Text is always set via textContent (safe from HTML injection). */
  function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
      if (key === 'class') node.className = value;
      else if (key === 'text') node.textContent = value;
      else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
      else if (value !== false && value != null) node.setAttribute(key, value === true ? '' : value);
    }
    for (const child of children.flat(Infinity)) {
      if (child) node.append(child);
    }
    return node;
  }

  function greeting(name) {
    const hour = new Date().getHours();
    const part = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    return `${part}, ${name}`;
  }

  function toast(message) {
    document.querySelectorAll('.toast').forEach((t) => t.remove());
    const node = el('div', { class: 'toast', role: 'status', text: message });
    document.body.append(node);
    setTimeout(() => node.classList.add('is-leaving'), 3000);
    setTimeout(() => node.remove(), 3600);
  }

  const ERRORS = {
    declaration_required: 'Choose a declaration first.',
    skill_required: 'Choose what you practiced.',
    mood_required: 'Tap how you feel now.',
    empty: 'Write something in at least one box.',
    limit: 'You can hold up to 3 declarations. Archive one to add another.',
  };

  /** Disables a button while a request runs, and shows errors inline. */
  async function withButton(button, errorNode, task) {
    const label = button.textContent;
    button.disabled = true;
    button.textContent = 'Saving…';
    if (errorNode) errorNode.textContent = '';
    try {
      await task();
    } catch (err) {
      if (err.message === 'auth') return;
      button.disabled = false;
      button.textContent = label;
      if (errorNode) errorNode.textContent = ERRORS[err.message] || 'Not saved. Please try again.';
    }
  }

  // ---------- shared pieces ----------

  /** A collapsible card. Its open/closed state survives re-renders. */
  function card(id, { eyebrow, title, done, body }) {
    const details = el('details', { class: 'card', open: openCards.has(id) });
    details.addEventListener('toggle', () => {
      if (details.open) openCards.add(id);
      else openCards.delete(id);
    });
    details.append(
      el('summary', { class: 'card__summary' },
        el('span', { class: 'card__heading' },
          el('span', { class: 'card__eyebrow', text: eyebrow }),
          el('span', { class: 'card__title', text: title })
        ),
        done ? el('span', { class: 'card__done', text: done }) : null
      ),
      el('div', { class: 'card__content' }, body)
    );
    return details;
  }

  function ring(r, size = '') {
    return el('span', {
      class: `ring ${size}`,
      style: `--p:${r.pct}`,
      title: `${r.count} reps · next milestone at ${r.next}`,
      'aria-label': `${r.count} reps, next milestone at ${r.next}`,
    }, el('span', { class: 'ring__n', text: String(r.count) }));
  }

  /** Textarea with a mic button. Returns { node, field }. */
  function textField({ placeholder = '', rows = 3, label, id } = {}) {
    const field = el('textarea', {
      class: 'field', rows, placeholder, id,
      'aria-label': label && !id ? label : null,
    });
    return { node: el('div', { class: 'answer' }, field, micButton(field)), field };
  }

  /** Single-choice pill group. Returns { node, value }. */
  function choiceGroup(options, { label, selected = null, onChange } = {}) {
    let value = selected;
    const group = el('div', { class: 'choices', role: 'radiogroup', 'aria-label': label });
    for (const opt of options) {
      const btn = el('button', {
        type: 'button', class: 'choice', role: 'radio',
        'aria-checked': String(opt.value === value), title: opt.hint || null,
      }, opt.ring ? ring(opt.ring, 'ring--sm') : null, el('span', { text: opt.text }));
      btn.addEventListener('click', () => {
        value = opt.value;
        group.querySelectorAll('.choice').forEach((b) => b.setAttribute('aria-checked', String(b === btn)));
        if (onChange) onChange(value);
      });
      group.append(btn);
    }
    return { node: group, get value() { return value; } };
  }

  /** 3×3 mood keypad. Tap again to clear. Returns { node, value }. */
  function moodPad({ onChange } = {}) {
    let value = '';
    const pad = el('div', { class: 'moods', role: 'radiogroup', 'aria-label': 'How do you feel now?' });
    for (const mood of state.moods) {
      const btn = el('button', {
        type: 'button', class: 'mood', role: 'radio', 'aria-checked': 'false', 'aria-label': mood.label,
      }, el('span', { class: 'mood__emoji', 'aria-hidden': 'true', text: mood.emoji }),
         el('span', { class: 'mood__label', text: mood.label }));
      btn.addEventListener('click', () => {
        value = value === mood.key ? '' : mood.key;
        pad.querySelectorAll('.mood').forEach((b) => b.setAttribute('aria-checked', String(b === btn && value !== '')));
        if (onChange) onChange(value);
      });
      pad.append(btn);
    }
    return { node: pad, get value() { return value; } };
  }

  /** The declaration practiced most recently, or the only one. */
  function defaultDeclaration() {
    const decls = state.declarations;
    const last = decls.find((d) => d.id === state.last_declaration);
    if (last) return last.id;
    return decls.length === 1 ? decls[0].id : null;
  }

  function declarationChoices(opts) {
    return choiceGroup(
      state.declarations.map((d) => ({ value: d.id, text: d.text })),
      { label: 'Declaration', ...opts }
    );
  }

  // ---------- voice to text ----------

  function micButton(field) {
    const btn = el('button', {
      class: 'mic', type: 'button', 'aria-pressed': 'false',
      'aria-label': 'Speak your answer', title: 'Speak your answer',
    }, el('span', { 'aria-hidden': 'true', text: '🎙' }));

    if (!SpeechRecognition) {
      btn.hidden = true; // e.g. Firefox: typing still works
      return btn;
    }

    let rec = null;

    btn.addEventListener('click', () => {
      if (rec) { rec.stop(); return; }

      rec = new SpeechRecognition();
      rec.lang = navigator.language || 'en-US';
      rec.continuous = true;
      rec.interimResults = true;

      const before = field.value ? field.value.replace(/\s*$/, ' ') : '';
      let finalText = '';

      rec.onresult = (event) => {
        let interim = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
          const result = event.results[i];
          if (result.isFinal) finalText += result[0].transcript;
          else interim += result[0].transcript;
        }
        field.value = before + finalText + interim;
        field.dispatchEvent(new Event('input'));
      };

      rec.onerror = (event) => {
        if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
          alert('Microphone access was blocked. Allow it in your browser settings, or type instead.');
        }
      };

      rec.onend = () => {
        field.value = before + finalText;
        field.dispatchEvent(new Event('input'));
        rec = null;
        btn.classList.remove('is-listening');
        btn.setAttribute('aria-pressed', 'false');
        field.focus(); // ready to edit the transcript
      };

      rec.start();
      btn.classList.add('is-listening');
      btn.setAttribute('aria-pressed', 'true');
    });

    return btn;
  }

  // ---------- saving entries ----------

  async function saveEntry(cardId, payload) {
    const res = await api('entry', { method: 'POST', body: payload });
    state = res.state;
    openCards.delete(cardId);
    render();

    if (res.celebrations.length) {
      toast(`🎉 ${res.celebrations.join(' · ')}`);
    } else if (res.first_today) {
      const days = state.streak.streak;
      toast(`🔥 ${days} ${days === 1 ? 'day' : 'days'}. You showed up.`);
    } else {
      toast('Rep logged. Nicely done.');
    }
  }

  // ---------- header ----------

  function streakBadge() {
    const s = state.streak;
    const t = state.today_counts;
    let message;
    if (s.done_today) message = `You showed up today. Longest streak: ${s.longest}.`;
    else if (s.freezes_needed > 0) message = 'A streak freeze is covering your missed day. Any practice today keeps you going.';
    else if (s.streak > 0) message = 'Any practice today keeps your streak. Even one breath.';
    else message = 'Every streak starts with one breath.';

    const chip = (label, count) =>
      el('span', { class: `chip ${count ? 'is-done' : ''}`, text: count > 1 ? `${label} ×${count}` : label });

    return el('section', { class: `streak ${s.done_today ? 'is-done' : ''}`, 'aria-label': 'Your streak' },
      el('div', { class: 'streak__count' },
        el('span', { class: 'streak__flame', 'aria-hidden': 'true', text: '🔥' }),
        el('span', { class: 'streak__number', text: String(s.streak) }),
        el('span', { class: 'streak__label', text: s.streak === 1 ? 'day' : 'days' }),
        s.freezes ? el('span', { class: 'streak__freezes', title: 'Streak freezes', text: `❄️ ${s.freezes}` }) : null
      ),
      el('p', { class: 'streak__meta', text: message }),
      el('div', { class: 'chips', 'aria-label': 'Today' },
        chip('Clearing', t.clearing),
        chip('Practice', t.practice),
        chip('Gratitude', t.gratitude),
        t.rest ? chip('Rest', t.rest) : null
      )
    );
  }

  // ---------- morning clearing ----------

  function clearingCard() {
    const c = state.clearing;
    const fields = c.questions.map((q, i) => {
      const id = `clear-${i}`;
      const f = textField({ rows: 2, id });
      return { key: q.key, ...f, label: el('label', { class: 'question__text', for: id, text: q.text }) };
    });
    const error = el('p', { class: 'form-error', 'aria-live': 'polite' });
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: 'Save clearing' });

    save.addEventListener('click', () => withButton(save, error, () => {
      const answers = Object.fromEntries(fields.map((f) => [f.key, f.field.value]));
      return saveEntry('clearing', { type: 'clearing', answers });
    }));

    return card('clearing', {
      eyebrow: 'Morning',
      title: c.title,
      done: state.today_counts.clearing ? '✓ Done' : '',
      body: [
        el('p', { class: 'card__body', text: c.intro }),
        fields.map((f) => el('div', { class: 'question' }, f.label, f.node)),
        save, error,
      ],
    });
  }

  // ---------- practice ----------

  function practiceCard() {
    const n = state.today_counts.practice;
    const meta = {
      eyebrow: 'Practice',
      title: 'Log a practice',
      done: n ? `✓ ${n} today` : '',
    };

    if (!state.declarations.length) {
      return card('practice', {
        ...meta,
        body: [
          el('p', { class: 'card__body', text: 'Start with a declaration, in your own words. Every practice builds toward it, and you can refine it anytime.' }),
          declarationForm(null, 'practice'),
        ],
      });
    }

    const error = el('p', { class: 'form-error', 'aria-live': 'polite' });
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: 'Log practice' });
    const update = () => { save.disabled = !(decl.value && skill.value && mood.value); };

    const decl = declarationChoices({ selected: defaultDeclaration(), onChange: update });
    const skill = choiceGroup(
      state.skills.map((s) => ({ value: s.key, text: s.title, hint: s.description, ring: s.ring })),
      { label: 'Skill', onChange: update }
    );
    const mood = moodPad({ onChange: update });
    const note = textField({ rows: 2, placeholder: 'Optional note', label: 'Practice note' });
    update();

    save.addEventListener('click', () => withButton(save, error, () => saveEntry('practice', {
      type: 'practice',
      declaration_id: decl.value,
      skill_key: skill.value,
      mood: mood.value,
      note: note.field.value,
    })));

    return card('practice', {
      ...meta,
      body: [
        el('p', { class: 'step', text: 'Declaration' }), decl.node,
        el('p', { class: 'step', text: 'What did you practice?' }), skill.node,
        el('p', { class: 'step', text: 'How do you feel now?' }), mood.node,
        note.node,
        save, error,
      ],
    });
  }

  // ---------- evening gratitude ----------

  function gratitudeCard() {
    const g = state.gratitude;
    const error = el('p', { class: 'form-error', 'aria-live': 'polite' });
    const mood = moodPad();
    const note = textField({ rows: 2, placeholder: g.note_placeholder, label: 'Gratitude note' });
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: 'I practiced gratitude' });

    save.addEventListener('click', () => withButton(save, error, () => saveEntry('gratitude', {
      type: 'gratitude', mood: mood.value, note: note.field.value,
    })));

    return card('gratitude', {
      eyebrow: 'Evening',
      title: g.title,
      done: state.today_counts.gratitude ? '✓ Done' : '',
      body: [
        el('p', { class: 'card__body', text: g.intro }),
        el('ol', { class: 'steps' }, g.steps.map((s) =>
          el('li', {}, el('strong', { text: s.title }), ' ', s.text)
        )),
        el('p', { class: 'step', text: 'How do you feel now? (optional)' }), mood.node,
        note.node,
        save, error,
      ],
    });
  }

  // ---------- conscious rest ----------

  function restCard() {
    const r = state.rest;
    const error = el('p', { class: 'form-error', 'aria-live': 'polite' });
    const decl = state.declarations.length ? declarationChoices() : null;
    const note = textField({ rows: 2, placeholder: r.note_placeholder, label: 'Rest note' });
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: 'I\'m choosing rest today' });

    save.addEventListener('click', () => withButton(save, error, () => saveEntry('rest', {
      type: 'rest', declaration_id: decl ? decl.value : null, note: note.field.value,
    })));

    return card('rest', {
      eyebrow: 'Anytime',
      title: r.title,
      done: state.today_counts.rest ? '✓ Chosen' : '',
      body: [
        el('p', { class: 'card__body', text: r.intro }),
        decl ? [el('p', { class: 'step', text: 'Resting in service of… (optional)' }), decl.node] : null,
        note.node,
        save, error,
      ],
    });
  }

  // ---------- weekly reflection ----------

  function weeklyCard() {
    const w = state.weekly;
    if (!w) return null;
    const answered = w.questions.filter((q) => q.response.trim()).length;

    return card('weekly', {
      eyebrow: 'This week',
      title: w.title,
      done: answered ? `${answered}/${w.questions.length}` : '',
      body: [
        w.intro ? el('p', { class: 'card__body card__quote', text: w.intro }) : null,
        w.questions.map(questionBlock),
      ],
    });
  }

  function questionBlock(q, i) {
    const id = `weekly-${i}`;
    const { node, field } = textField({ rows: 3, id });
    field.value = q.response;

    const status = el('span', { class: 'save-status', 'aria-live': 'polite' });
    const save = el('button', { class: 'btn', type: 'button', text: 'Save' });
    let lastSaved = q.response;

    async function doSave() {
      if (field.value === lastSaved) return;
      save.disabled = true;
      status.textContent = 'Saving…';
      try {
        await api('weekly', { method: 'POST', body: { key: q.key, response: field.value } });
        lastSaved = field.value;
        q.response = field.value;
        status.textContent = 'Saved';
      } catch (err) {
        status.textContent = 'Not saved. Try again.';
      } finally {
        save.disabled = false;
      }
    }

    field.addEventListener('input', () => { status.textContent = field.value === lastSaved ? '' : 'Unsaved'; });
    field.addEventListener('blur', doSave);
    save.addEventListener('click', doSave);

    return el('div', { class: 'question' },
      el('label', { class: 'question__text', for: id },
        q.text, q.optional ? el('span', { class: 'optional', text: ' · optional' }) : null),
      q.hint ? el('p', { class: 'question__hint', text: q.hint }) : null,
      node,
      el('div', { class: 'question__actions' }, save, status)
    );
  }

  // ---------- declarations ----------

  function declarationForm(decl, cardId) {
    const { node, field } = textField({
      rows: 2,
      placeholder: 'I am a stand for…  /  I declare…',
      label: decl ? 'Refine declaration' : 'New declaration',
    });
    if (decl) field.value = decl.text;
    const error = el('p', { class: 'form-error', 'aria-live': 'polite' });
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: decl ? 'Save' : 'Save declaration' });

    save.addEventListener('click', () => withButton(save, error, async () => {
      const res = await api('declaration', { method: 'POST', body: { id: decl ? decl.id : null, text: field.value } });
      state = res.state;
      if (cardId) openCards.add(cardId);
      render();
      toast(decl ? 'Declaration refined.' : 'Declaration saved.');
    }));

    return el('div', { class: 'decl-form' }, node, save, error);
  }

  function declarationsCard() {
    const items = state.declarations.map((d) => {
      const row = el('li', { class: 'decl' });
      const show = () => {
        const refine = el('button', { class: 'link', type: 'button', text: 'Refine' });
        const archive = el('button', { class: 'link', type: 'button', text: 'Archive' });
        refine.addEventListener('click', () => {
          row.replaceChildren(declarationForm(d, 'declarations'));
        });
        archive.addEventListener('click', async () => {
          if (!confirm('Archive this declaration? Its history and reps are kept.')) return;
          try {
            const res = await api('declaration_archive', { method: 'POST', body: { id: d.id } });
            state = res.state;
            render();
          } catch (err) {
            if (err.message !== 'auth') toast('Could not archive. Please try again.');
          }
        });
        row.replaceChildren(
          ring(d.ring),
          el('div', { class: 'decl__body' },
            el('p', { class: 'decl__text', text: d.text }),
            el('div', { class: 'decl__actions' }, refine, archive)
          )
        );
      };
      show();
      return row;
    });

    const canAdd = state.declarations.length < state.max_declarations;

    return card('declarations', {
      eyebrow: 'Your declarations',
      title: state.declarations.length ? `${state.declarations.length} of ${state.max_declarations}` : 'Write your first',
      body: [
        items.length ? el('ul', { class: 'decls' }, items) : null,
        canAdd ? [el('p', { class: 'step', text: 'Add a declaration' }), declarationForm(null, 'declarations')] : null,
      ],
    });
  }

  // ---------- reps ----------

  function repsCard() {
    const tile = (r, label) => el('div', { class: 'rep' }, ring(r, 'ring--lg'), el('span', { class: 'rep__label', text: label }));

    return card('reps', {
      eyebrow: 'Your reps',
      title: 'Skills & rituals',
      body: [
        el('p', { class: 'card__body', text: 'Every rep fills a ring. Milestones at 10, 20, 50, 100, then every 100.' }),
        el('div', { class: 'reps' }, state.skills.map((s) => tile(s.ring, s.title))),
        el('div', { class: 'reps' }, Object.values(state.rituals).map((r) => tile(r.ring, r.label))),
      ],
    });
  }

  // ---------- render ----------

  function render() {
    // Keep the scroll position steady across re-renders.
    const y = window.scrollY;
    root.replaceChildren(
      el('header', { class: 'top' },
        el('h1', { class: 'top__greeting', text: greeting(state.user.name) })
      ),
      streakBadge(),
      clearingCard(),
      practiceCard(),
      gratitudeCard(),
      restCard(),
      weeklyCard(),
      declarationsCard(),
      repsCard()
    );
    window.scrollTo(0, y);
  }

  api('state')
    .then((data) => {
      state = data;
      if (!state.declarations.length) openCards.add('practice');
      render();
    })
    .catch((err) => {
      if (err.message !== 'auth') {
        root.replaceChildren(el('p', { class: 'loading', text: 'Something went wrong loading your practice. Please refresh.' }));
      }
    });
})();
