/* Unstruck Somatics practice app: front end. No build step, no dependencies. */
(() => {
  'use strict';

  const root = document.getElementById('app');
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

  let state = null;
  let view = 'today';
  const hist = { mode: 'daily', month: null, months: {}, selected: null, days: {}, weeks: null };
  const openCards = new Set(); // which cards are expanded, kept across re-renders

  // ---------- helpers ----------

  async function api(action, { method = 'GET', body, params = {} } = {}) {
    const query = new URLSearchParams({ action, ...params });
    const res = await fetch(`${USP.api}?${query}`, {
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
    grace_closed: 'It\'s past noon, so this can only be logged for today.',
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

  // Microphone permission, checked once. Asking for it on its own first means
  // the browser's permission prompts can't stall speech recognition (Safari).
  let micGranted = false;
  if (navigator.permissions && navigator.permissions.query) {
    navigator.permissions.query({ name: 'microphone' })
      .then((p) => { micGranted = p.state === 'granted'; })
      .catch(() => {});
  }
  let activeMic = null; // only one field listens at a time

  const MIC_ERRORS = {
    'not-allowed': 'Microphone access is blocked. Allow it in your browser\'s settings for this site, or type instead.',
    'service-not-allowed': 'Voice input is turned off for this browser. Check its settings, or type instead.',
    'audio-capture': 'No microphone was found. Check that one is connected, or type instead.',
    'network': 'Voice input needs an internet connection. Try again, or type instead.',
    'no-speech': 'Didn\'t catch anything. Tap the mic and try again.',
  };

  function micButton(field) {
    const btn = el('button', {
      class: 'mic', type: 'button', 'aria-pressed': 'false',
      'aria-label': 'Speak your answer', title: 'Speak your answer',
    }, el('span', { 'aria-hidden': 'true', text: '🎙' }));

    if (!SpeechRecognition) {
      btn.hidden = true; // e.g. Firefox: typing still works
      return btn;
    }

    let phase = 'idle'; // idle → starting → listening
    let rec = null;
    let watchdog = null;

    function setPhase(next) {
      phase = next;
      btn.classList.toggle('is-starting', next === 'starting');
      btn.classList.toggle('is-listening', next === 'listening');
      btn.setAttribute('aria-pressed', String(next !== 'idle'));
      btn.setAttribute('aria-label', next === 'idle' ? 'Speak your answer' : 'Stop listening');
    }

    // Safe to call any number of times, from any state.
    function reset() {
      clearTimeout(watchdog);
      if (rec) {
        const r = rec;
        rec = null;
        r.onresult = r.onerror = r.onend = r.onstart = null;
        try { r.abort(); } catch (e) { /* already stopped */ }
      }
      if (activeMic === stopThis) activeMic = null;
      setPhase('idle');
    }
    const stopThis = () => {
      if (rec && phase === 'listening') {
        clearTimeout(watchdog);
        try { rec.stop(); } catch (e) { reset(); } // onend keeps the final text
      } else {
        reset();
      }
    };

    btn.addEventListener('click', async () => {
      if (phase !== 'idle') { stopThis(); return; }
      if (activeMic) activeMic();
      activeMic = stopThis;
      setPhase('starting');

      if (!micGranted && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        try {
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          stream.getTracks().forEach((t) => t.stop());
          micGranted = true;
        } catch (err) {
          reset();
          toast(MIC_ERRORS['not-allowed']);
          return;
        }
        if (phase !== 'starting') return; // tapped again while the prompt was open
      }

      const before = field.value ? field.value.replace(/\s*$/, ' ') : '';
      let finalText = '';

      rec = new SpeechRecognition();
      rec.lang = navigator.language || 'en-US';
      rec.continuous = true;
      rec.interimResults = true;

      rec.onstart = () => { clearTimeout(watchdog); setPhase('listening'); };

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
        if (event.error !== 'aborted' && MIC_ERRORS[event.error]) toast(MIC_ERRORS[event.error]);
        if (event.error === 'not-allowed') micGranted = false;
      };

      rec.onend = () => {
        field.value = before + finalText;
        field.dispatchEvent(new Event('input'));
        reset();
        field.focus(); // ready to edit the transcript
      };

      // If recognition hasn't started in a few seconds, don't leave the button stuck.
      watchdog = setTimeout(() => {
        if (phase === 'starting') {
          reset();
          toast('Voice input didn\'t start. Tap the mic to try again.');
        }
      }, 8000);

      try {
        rec.start();
      } catch (err) {
        // Some browsers only start from a direct tap; the permission is set now.
        reset();
        toast('Microphone is ready. Tap the mic again to speak.');
      }
    });

    return btn;
  }

  // ---------- grace window: log for yesterday until noon ----------

  /** "Today | Yesterday" choice, shown only before noon. Returns { node, value }. */
  function whenPicker() {
    let value = 'today';
    if (!state.grace.open) return { node: null, get value() { return value; } };

    const buttons = [['today', 'Today'], ['yesterday', 'Yesterday']].map(([key, label]) => {
      const b = el('button', { type: 'button', 'aria-pressed': String(key === value), text: label });
      b.addEventListener('click', () => {
        value = key;
        buttons.forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      });
      return b;
    });

    return {
      node: el('div', { class: 'when' },
        el('div', { class: 'segmented', role: 'group', 'aria-label': 'Log this for' }, buttons),
        el('p', { class: 'when__hint', text: `Late night? Until ${state.grace.until}, you can log yesterday's practice.` })
      ),
      get value() { return value; },
    };
  }

  // ---------- saving entries ----------

  async function saveEntry(cardId, payload) {
    const res = await api('entry', { method: 'POST', body: payload });
    state = res.state;
    openCards.delete(cardId);
    render();

    hist.months = {};
    hist.days = {};
    const s = state.stats;
    if (res.celebrations.length) {
      toast(`🎉 ${res.celebrations.join(' · ')}`);
    } else if (res.for_yesterday) {
      toast(res.first_of_day ? `Logged for yesterday. Streak: ${s.streak}.` : 'Rep logged for yesterday.');
    } else if (res.first_of_day) {
      toast(`Day ${s.days}. You showed up.`);
    } else {
      toast('Rep logged. Nicely done.');
    }
  }

  // ---------- header ----------

  function counters() {
    const s = state.stats;
    const yesterday = state.grace.yesterday;
    const dayBefore = new Date(`${yesterday}T12:00:00`);
    dayBefore.setDate(dayBefore.getDate() - 1);
    const dayBeforeKey = dayBefore.toISOString().slice(0, 10);

    let note;
    if (s.done_today) note = 'You showed up today.';
    else if (state.grace.open && s.last_date === dayBeforeKey) note = `Missed yesterday? You can still log it until ${state.grace.until}.`;
    else if (s.streak > 0) note = 'Any practice today keeps your streak. Even one breath.';
    else if (s.days > 0) note = `${s.days} ${s.days === 1 ? 'day' : 'days'} of practice, and counting. Every streak starts with one breath.`;
    else note = 'Every streak starts with one breath.';

    const counter = (label, value, hero) =>
      el('div', { class: `counter ${hero ? 'counter--hero' : ''}` },
        el('span', { class: 'counter__label', text: label }),
        el('span', { class: 'counter__value', text: String(value) })
      );

    return el('section', { class: 'counters', 'aria-label': 'Your practice' },
      el('div', { class: 'counters__row' },
        counter('Days', s.days, true),
        counter('Streak', s.streak, true),
        counter('Longest', s.longest),
        counter('Reps', s.reps)
      ),
      el('p', { class: 'counters__note', text: note })
    );
  }

  function todayChips() {
    const t = state.today_counts;
    const chip = (label, count) =>
      el('span', { class: `chip ${count ? 'is-done' : ''}`, text: count > 1 ? `${label} ×${count}` : label });
    return el('div', { class: 'chips', 'aria-label': 'Today' },
      chip('Clearing', t.clearing),
      chip('Practice', t.practice),
      chip('Gratitude', t.gratitude)
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
    const when = whenPicker();
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: 'Save clearing' });

    save.addEventListener('click', () => withButton(save, error, () => {
      const answers = Object.fromEntries(fields.map((f) => [f.key, f.field.value]));
      return saveEntry('clearing', { type: 'clearing', answers, for: when.value });
    }));

    return card('clearing', {
      eyebrow: 'Morning',
      title: c.title,
      done: state.today_counts.clearing ? '✓ Done' : '',
      body: [
        el('p', { class: 'card__body', text: c.intro }),
        fields.map((f) => el('div', { class: 'question' }, f.label, f.node)),
        when.node, save, error,
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
    const hint = el('p', { class: 'skill-hint', 'aria-live': 'polite' });
    const update = () => {
      save.disabled = !(decl.value && skill.value && mood.value);
      // The chosen skill's description, and its own note prompt (e.g. conscious rest).
      const chosen = state.skills.find((x) => x.key === skill.value);
      hint.textContent = chosen ? chosen.description : '';
      note.field.placeholder = (chosen && chosen.note_placeholder) || 'Optional note';
    };

    const decl = declarationChoices({ selected: defaultDeclaration(), onChange: update });
    const skill = choiceGroup(
      state.skills.map((s) => ({ value: s.key, text: s.title, hint: s.description, ring: s.ring })),
      { label: 'Skill', onChange: update }
    );
    const mood = moodPad({ onChange: update });
    const note = textField({ rows: 2, placeholder: 'Optional note', label: 'Practice note' });
    const when = whenPicker();
    update();

    save.addEventListener('click', () => withButton(save, error, () => saveEntry('practice', {
      type: 'practice',
      declaration_id: decl.value,
      skill_key: skill.value,
      mood: mood.value,
      note: note.field.value,
      for: when.value,
    })));

    return card('practice', {
      ...meta,
      body: [
        el('p', { class: 'step', text: 'Declaration' }), decl.node,
        el('p', { class: 'step', text: 'What did you practice?' }), skill.node, hint,
        el('p', { class: 'step', text: 'How do you feel now?' }), mood.node,
        note.node,
        when.node, save, error,
      ],
    });
  }

  // ---------- evening gratitude ----------

  function gratitudeCard() {
    const g = state.gratitude;
    const error = el('p', { class: 'form-error', 'aria-live': 'polite' });
    const mood = moodPad();
    const note = textField({ rows: 2, placeholder: g.note_placeholder, label: 'Gratitude note' });
    const when = whenPicker();
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: 'I practiced gratitude' });

    save.addEventListener('click', () => withButton(save, error, () => saveEntry('gratitude', {
      type: 'gratitude', mood: mood.value, note: note.field.value, for: when.value,
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
        when.node, save, error,
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
        hist.weeks = null; // History reloads with this answer
        status.textContent = 'Saved';
        // Saved as you left the box for the History tab? Refresh what it shows.
        if (view === 'history' && hist.mode === 'weekly') render();
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

  // ---------- history ----------

  const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  function shiftMonth(month, by) {
    const [y, m] = month.split('-').map(Number);
    const d = new Date(Date.UTC(y, m - 1 + by, 1));
    return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`;
  }

  function longDate(key) {
    const [y, m, d] = key.split('-').map(Number);
    const date = new Date(Date.UTC(y, m - 1, d));
    return `${WEEKDAYS[date.getUTCDay()]}, ${MONTHS[m - 1]} ${d}`;
  }

  async function loadMonth(month) {
    if (!hist.months[month]) hist.months[month] = await api('history_month', { params: { month } });
    return hist.months[month];
  }

  async function loadDay(date) {
    if (!hist.days[date]) hist.days[date] = await api('history_day', { params: { date } });
    return hist.days[date];
  }

  function historyView() {
    const toggle = el('div', { class: 'segmented', role: 'group', 'aria-label': 'History view' },
      [['daily', 'Daily practice'], ['weekly', 'Weekly reflections']].map(([key, label]) => {
        const b = el('button', { type: 'button', 'aria-pressed': String(hist.mode === key), text: label });
        b.addEventListener('click', () => { hist.mode = key; render(); });
        return b;
      })
    );
    const body = el('div', { class: 'weeks' }, el('p', { class: 'loading', text: 'Loading…' }));

    if (hist.mode === 'daily') fillDaily(body);
    else fillWeekly(body);

    return [counters(), toggle, body];
  }

  async function fillDaily(body) {
    try {
      const month = hist.month || state.today.slice(0, 7);
      const data = await loadMonth(month);
      hist.month = month;
      if (!hist.selected) hist.selected = state.today;
      body.replaceChildren(calendar(data), dayLog());
    } catch (err) {
      if (err.message !== 'auth') body.replaceChildren(el('p', { class: 'daylog__empty', text: 'Couldn\'t load your history. Please refresh.' }));
    }
  }

  function calendar(data) {
    const [y, m] = data.month.split('-').map(Number);
    const firstDow = (new Date(Date.UTC(y, m - 1, 1)).getUTCDay() + 6) % 7; // Monday first
    const daysInMonth = new Date(Date.UTC(y, m, 0)).getUTCDate();

    const prev = el('button', { class: 'calendar__nav', type: 'button', 'aria-label': 'Previous month', text: '‹' });
    const next = el('button', { class: 'calendar__nav', type: 'button', 'aria-label': 'Next month', text: '›' });
    prev.disabled = data.month <= data.first_month;
    next.disabled = data.month >= data.current_month;
    prev.addEventListener('click', () => { hist.month = shiftMonth(data.month, -1); render(); });
    next.addEventListener('click', () => { hist.month = shiftMonth(data.month, 1); render(); });

    const grid = el('div', { class: 'calendar__grid' },
      ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) =>
        el('div', { class: 'calendar__dow', 'aria-hidden': 'true', text: d.slice(0, 1) })
      ),
      Array.from({ length: firstDow }, () => el('div'))
    );

    for (let d = 1; d <= daysInMonth; d++) {
      const key = `${data.month}-${String(d).padStart(2, '0')}`;
      const n = data.days[key] || 0;
      const future = key > data.today;
      const level = n >= 4 ? 'day--l3' : n >= 2 ? 'day--l2' : n === 1 ? 'day--l1' : '';
      const btn = el('button', {
        type: 'button',
        class: `day ${level} ${key === data.today ? 'day--today' : ''} ${future ? 'day--future' : ''}`,
        'aria-pressed': String(key === hist.selected),
        'aria-label': `${longDate(key)}: ${n ? `${n} ${n === 1 ? 'rep' : 'reps'}` : 'no entries'}`,
        text: String(d),
      });
      if (future) btn.disabled = true;
      else btn.addEventListener('click', () => { hist.selected = key; render(); });
      grid.append(btn);
    }

    const swatch = (style, label) => el('span', {}, el('i', { style }), label);

    return el('section', { class: 'calendar', 'aria-label': 'Calendar' },
      el('div', { class: 'calendar__head' },
        prev,
        el('span', { class: 'calendar__month', text: `${MONTHS[m - 1]} ${y}` }),
        next
      ),
      grid,
      el('div', { class: 'legend' },
        swatch('border:1.5px solid var(--rasp)', '1 rep'),
        swatch('background:var(--rasp-soft)', '2–3'),
        swatch('background:var(--rasp)', '4+'),
        swatch('outline:1.5px solid var(--teal);outline-offset:1px', 'Today')
      )
    );
  }

  function dayLog() {
    const date = hist.selected;
    const box = el('section', { class: 'daylog', 'aria-live': 'polite' },
      el('h2', { class: 'daylog__title', text: date === state.today ? 'Today' : longDate(date) }),
      el('p', { class: 'daylog__empty', text: 'Loading…' })
    );

    loadDay(date).then((day) => {
      const title = box.firstChild;
      if (!day.items.length) {
        box.replaceChildren(title, el('p', { class: 'daylog__empty', text: 'No entries. Rest counts too.' }));
        return;
      }
      box.replaceChildren(title, ...day.items.map((item) =>
        el('div', { class: 'daylog__item' },
          el('div', { class: 'daylog__name' },
            el('span', { text: item.title }),
            item.mood ? el('span', { class: 'daylog__mood', text: `${item.mood.emoji} ${item.mood.label}` }) : null
          ),
          item.declaration ? el('p', { class: 'daylog__line', text: item.declaration }) : null,
          item.summary ? el('p', { class: 'daylog__line', text: item.summary }) : null,
          item.note ? el('p', { class: 'daylog__line', text: `“${item.note}”` }) : null
        )
      ));
    }).catch((err) => {
      if (err.message !== 'auth') box.replaceChildren(box.firstChild, el('p', { class: 'daylog__empty', text: 'Couldn\'t load this day.' }));
    });

    return box;
  }

  async function fillWeekly(body) {
    try {
      if (!hist.weeks) hist.weeks = (await api('history_weekly')).weeks;

      // This week is always first, even before it has answers.
      const current = state.weekly ? state.weekly.week_start : null;
      const weeks = hist.weeks.slice();
      if (current && !weeks.some((w) => w.week_start === current)) {
        weeks.unshift({ week_start: current, answers: [] });
      }
      if (!weeks.length) {
        body.replaceChildren(el('p', { class: 'daylog__empty', text: 'Your weekly reflections will collect here.' }));
        return;
      }

      body.replaceChildren(...weeks.map((w) => {
        const [y, m, d] = w.week_start.split('-').map(Number);
        const isCurrent = w.week_start === current;
        const n = w.answers.length;
        const total = isCurrent ? state.weekly.questions.length : 0;
        return el('details', { class: `week ${isCurrent ? 'week--current' : ''}`, open: isCurrent || weeks.length === 1 },
          el('summary', {},
            el('span', { class: 'week__heading' },
              isCurrent ? el('span', { class: 'week__badge', text: 'This week' }) : null,
              el('span', { class: 'week__title', text: `Week of ${MONTHS[m - 1]} ${d}, ${y}` })
            ),
            el('span', { class: 'week__count', text: isCurrent ? `${n} of ${total}` : `${n} ${n === 1 ? 'answer' : 'answers'}` })
          ),
          el('div', { class: 'week__answers' },
            n
              ? w.answers.map((a) => el('div', {}, el('p', { class: 'week__q', text: a.question }), el('p', { class: 'week__a', text: a.answer })))
              : el('p', { class: 'daylog__empty' }, 'Not answered yet. ', el('a', { href: '#today', text: 'Reflect on the Today tab' }), '.')
          )
        );
      }));
    } catch (err) {
      if (err.message !== 'auth') body.replaceChildren(el('p', { class: 'daylog__empty', text: 'Couldn\'t load your reflections. Please refresh.' }));
    }
  }

  // ---------- render ----------

  function todayView() {
    return [
      el('h1', { class: 'top__greeting', text: greeting(state.user.name) }),
      counters(),
      todayChips(),
      clearingCard(),
      practiceCard(),
      gratitudeCard(),
      weeklyCard(),
      declarationsCard(),
      repsCard(),
    ];
  }

  function render() {
    // Keep the scroll position steady across re-renders of the same view.
    const y = window.scrollY;
    root.replaceChildren(...(view === 'history' ? historyView() : todayView()));
    window.scrollTo(0, y);
  }

  function setView() {
    const next = location.hash === '#history' ? 'history' : 'today';
    const changed = next !== view;
    view = next;
    document.querySelectorAll('.tabs__tab').forEach((tab) => {
      if (tab.dataset.view === view) tab.setAttribute('aria-current', 'page');
      else tab.removeAttribute('aria-current');
    });
    if (state) {
      render();
      if (changed) window.scrollTo(0, 0);
    }
  }

  window.addEventListener('hashchange', setView);
  setView();

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
