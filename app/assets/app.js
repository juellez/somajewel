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

  /** A Phosphor icon from assets/icons.svg. */
  function icon(name) {
    const NS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('class', 'icon');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    const use = document.createElementNS(NS, 'use');
    use.setAttribute('href', `${window.USP.icons}#i-${name}`);
    svg.append(use);
    return svg;
  }

  /** Teal badge for today's count. */
  function todayBadge(n) {
    return el('span', { class: 'today-badge', text: String(n) });
  }

  /**
   * Screen text from content/prompts.php ('ui'), by dotted path, with
   * {placeholders} filled in: t('counters.note_grace', { until: 'noon' }).
   */
  function t(path, vars = {}) {
    let value = state && state.ui;
    for (const key of path.split('.')) value = value && value[key];
    if (typeof value !== 'string') return path;
    return value.replace(/\{(\w+)\}/g, (m, k) => (k in vars ? String(vars[k]) : m));
  }

  function greeting(name) {
    const hour = new Date().getHours();
    const part = hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening';
    return t(`greeting.${part}`, { name });
  }

  /** A mood's icon in this person's icon style (falls back to the stored one). */
  function moodIcon(mood) {
    const m = state.moods.find((x) => x.key === mood.key);
    return m ? m.icon : mood.icon;
  }

  /** Weekday (0 = Sunday) of a Y-m-d date. */
  function weekday(key) {
    const [y, m, d] = key.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d)).getUTCDay();
  }

  function toast(message) {
    document.querySelectorAll('.toast').forEach((t) => t.remove());
    const node = el('div', { class: 'toast', role: 'status', text: message });
    document.body.append(node);
    setTimeout(() => node.classList.add('is-leaving'), 3000);
    setTimeout(() => node.remove(), 3600);
  }

  /** An API error code as a sentence, or the general "not saved". */
  function errorText(code) {
    const text = t(`errors.${code}`, { max: state.max_declarations });
    return text === `errors.${code}` ? t('app.save_error') : text;
  }

  /** Disables a button while a request runs, and shows errors inline. */
  async function withButton(button, errorNode, task) {
    const label = button.textContent;
    button.disabled = true;
    button.textContent = t('app.saving');
    if (errorNode) errorNode.textContent = '';
    try {
      await task();
    } catch (err) {
      if (err.message === 'auth') return;
      button.disabled = false;
      button.textContent = label;
      if (errorNode) errorNode.textContent = errorText(err.message);
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
        done ? el('span', { class: 'card__done' }, done) : null
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
        'aria-label': opt.today ? `${opt.text}, ${opt.today} today, ${opt.count} total` : null,
      }, opt.today ? todayBadge(opt.today) : null,
        el('span', { text: opt.text }),
        opt.count ? el('span', { class: 'choice__count', text: `· ${opt.count}` }) : null);
      btn.addEventListener('click', () => {
        value = opt.value;
        group.querySelectorAll('.choice').forEach((b) => b.setAttribute('aria-checked', String(b === btn)));
        if (onChange) onChange(value);
      });
      group.append(btn);
    }
    return { node: group, get value() { return value; } };
  }

  /** 4×3 mood grid. Tap again to clear. Returns { node, value }. */
  function moodPad({ onChange } = {}) {
    let value = '';
    const pad = el('div', { class: 'moods', role: 'radiogroup', 'aria-label': t('practice.mood') });
    for (const mood of state.moods) {
      const btn = el('button', {
        type: 'button', class: 'mood', role: 'radio', 'aria-checked': 'false', 'aria-label': mood.label,
      }, icon(mood.icon), el('span', { class: 'mood__label', text: mood.label }));
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
      { label: t('practice.declaration'), ...opts }
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

  const MIC_ERRORS = ['not-allowed', 'service-not-allowed', 'audio-capture', 'network', 'no-speech'];

  function micButton(field) {
    const btn = el('button', {
      class: 'mic', type: 'button', 'aria-pressed': 'false',
      'aria-label': t('mic.label'), title: t('mic.label'),
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
      btn.setAttribute('aria-label', next === 'idle' ? t('mic.label') : t('mic.stop'));
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
          toast(t('mic.not-allowed'));
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
        if (MIC_ERRORS.includes(event.error)) toast(t(`mic.${event.error}`));
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
          toast(t('mic.stalled'));
        }
      }, 8000);

      try {
        rec.start();
      } catch (err) {
        // Some browsers only start from a direct tap; the permission is set now.
        reset();
        toast(t('mic.ready'));
      }
    });

    return btn;
  }

  // ---------- grace window: log for yesterday until noon ----------

  /**
   * "Today | Yesterday" choice, shown only before noon. Rituals (clearing,
   * gratitude) are once a day, so the choice is left out when that ritual is
   * already logged for yesterday. Returns { node, value }.
   */
  function whenPicker(ritual) {
    let value = 'today';
    const done = ritual && (state.grace.logged || []).includes(ritual);
    if (!state.grace.open || done) return { node: null, get value() { return value; } };

    const buttons = [['today', t('when.today')], ['yesterday', t('when.yesterday')]].map(([key, label]) => {
      const b = el('button', { type: 'button', 'aria-pressed': String(key === value), text: label });
      b.addEventListener('click', () => {
        value = key;
        buttons.forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      });
      return b;
    });

    return {
      node: el('div', { class: 'when' },
        el('div', { class: 'segmented', role: 'group', 'aria-label': t('when.label') }, buttons),
        el('p', { class: 'when__hint', text: t('when.hint', { until: state.grace.until }) })
      ),
      get value() { return value; },
    };
  }

  // ---------- saving entries ----------

  async function saveEntry(cardId, payload) {
    const res = await api('entry', { method: 'POST', body: payload });
    state = res.state;
    // Practice stays open, ready for the next rep; the others fold away.
    if (cardId !== 'practice') openCards.delete(cardId);
    render();

    hist.months = {};
    hist.days = {};
    const s = state.stats;
    if (res.celebrations.length) {
      toast(`🎉 ${res.celebrations.join(' · ')}`);
    } else if (res.for_yesterday) {
      toast(res.first_of_day ? t('toasts.yesterday_first', { streak: s.streak }) : t('toasts.yesterday_rep'));
    } else if (res.first_of_day) {
      toast(t('toasts.first_of_day', { days: s.days }));
    } else {
      toast(t('toasts.rep'));
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
    if (s.done_today) note = t('counters.note_done');
    else if (state.grace.open && s.last_date === dayBeforeKey) note = t('counters.note_grace', { until: state.grace.until });
    else if (s.streak > 0) note = t('counters.note_streak');
    else if (s.days > 0) note = s.days === 1 ? t('counters.note_one_day') : t('counters.note_days', { days: s.days });
    else note = t('counters.note_start');

    const counter = (label, value, hero) =>
      el('div', { class: `counter ${hero ? 'counter--hero' : ''}` },
        el('span', { class: 'counter__label', text: label }),
        el('span', { class: 'counter__value', text: String(value) })
      );

    return el('section', { class: 'counters', 'aria-label': 'Your practice' },
      el('div', { class: 'counters__row' },
        counter(t('counters.days'), s.days, true),
        counter(t('counters.streak'), s.streak, true),
        counter(t('counters.longest'), s.longest),
        counter(t('counters.reps'), s.reps)
      ),
      el('p', { class: 'counters__note', text: note })
    );
  }

  /** Today's practices by name: "Centering ×3, Conscious Rest". */
  function practicedToday() {
    return state.skills
      .filter((s) => state.today_skills[s.key])
      .map((s) => (state.today_skills[s.key] > 1 ? `${s.title} ×${state.today_skills[s.key]}` : s.title))
      .join(', ');
  }

  /** Weekly questions answered so far. */
  function weeklyAnswered() {
    return state.weekly ? state.weekly.questions.filter((q) => q.response.trim()).length : 0;
  }

  /**
   * Today's three rituals, one per line, with the practices named. On the
   * days set in content (checklist.weekly_days), a fourth line nudges toward
   * the weekly reflection until something is answered.
   */
  function ritualList() {
    const c = state.today_counts;
    const row = (name, done, detail) =>
      el('li', { class: `ritual ${done ? 'is-done' : ''}` },
        icon(done ? 'check' : 'circle'),
        el('span', { text: name }),
        detail ? el('span', { class: 'ritual__detail', text: detail }) : null
      );

    const days = (state.ui.checklist && state.ui.checklist.weekly_days) || [];
    const nudge = state.weekly && !weeklyAnswered() && days.includes(weekday(state.today))
      ? el('li', {},
        el('a', { class: 'ritual ritual--weekly', href: '#reflect' },
          icon('notebook'),
          el('span', { text: t('checklist.weekly') }),
          el('span', { class: 'ritual__detail', text: t('checklist.weekly_progress', { n: 0, total: state.weekly.questions.length }) }),
          icon('arrow-right')))
      : null;

    return el('ul', { class: 'rituals', 'aria-label': t('nav.today') },
      row(t('checklist.clearing'), c.clearing),
      row(t('checklist.practice'), c.practice, practicedToday()),
      row(t('checklist.gratitude'), c.gratitude),
      nudge
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
    const when = whenPicker('clearing');
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: t('clearing.save') });

    save.addEventListener('click', () => withButton(save, error, () => {
      const answers = Object.fromEntries(fields.map((f) => [f.key, f.field.value]));
      return saveEntry('clearing', { type: 'clearing', answers, for: when.value });
    }));

    return card('clearing', {
      eyebrow: t('clearing.eyebrow'),
      title: c.title,
      done: state.today_counts.clearing ? [icon('check'), ` ${t('cards.done')}`] : '',
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
      eyebrow: t('practice.eyebrow'),
      title: t('practice.title'),
      done: n ? [todayBadge(n), t('cards.today')] : '',
    };

    if (!state.declarations.length) {
      return card('practice', {
        ...meta,
        body: [
          el('p', { class: 'card__body', text: t('practice.first_declaration') }),
          declarationForm(null),
        ],
      });
    }

    const error = el('p', { class: 'form-error', 'aria-live': 'polite' });
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: t('practice.save') });
    const hint = el('p', { class: 'skill-hint', 'aria-live': 'polite' });
    const update = () => {
      save.disabled = !(decl.value && skill.value && mood.value);
      // The chosen skill's description, and its own note prompt (e.g. conscious rest).
      const chosen = state.skills.find((x) => x.key === skill.value);
      hint.textContent = chosen ? chosen.description : '';
      note.field.placeholder = (chosen && chosen.note_placeholder) || t('practice.note');
    };

    const decl = declarationChoices({ selected: defaultDeclaration(), onChange: update });
    const skill = choiceGroup(
      state.skills.map((s) => ({ value: s.key, text: s.title, count: s.count, today: state.today_skills[s.key] || 0 })),
      { label: t('practice.skill'), onChange: update }
    );
    const mood = moodPad({ onChange: update });
    const note = textField({ rows: 2, placeholder: t('practice.note'), label: t('practice.note') });

    // "+ New" next to the declarations, while there's room for another.
    const declSlot = el('div', { class: 'decl-slot' }, decl.node);
    if (state.declarations.length < state.max_declarations) {
      const add = el('button', { type: 'button', class: 'choice choice--new' }, icon('plus-circle'), t('practice.new_declaration'));
      add.addEventListener('click', () => {
        declSlot.replaceChildren(declarationForm(null, { onCancel: () => declSlot.replaceChildren(decl.node) }));
      });
      decl.node.append(add);
    }
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
        el('p', { class: 'step', text: t('practice.declaration') }), declSlot,
        el('p', { class: 'step', text: t('practice.skill') }), skill.node, hint,
        el('p', { class: 'step', text: t('practice.mood') }), mood.node,
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
    const when = whenPicker('gratitude');
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: t('gratitude.save') });

    save.addEventListener('click', () => withButton(save, error, () => saveEntry('gratitude', {
      type: 'gratitude', mood: mood.value, note: note.field.value, for: when.value,
    })));

    return card('gratitude', {
      eyebrow: t('gratitude.eyebrow'),
      title: g.title,
      done: state.today_counts.gratitude ? [icon('check'), ` ${t('cards.done')}`] : '',
      body: [
        el('p', { class: 'card__body', text: g.intro }),
        el('ol', { class: 'steps' }, g.steps.map((s) =>
          el('li', {}, el('strong', { text: s.title }), ' ', s.text)
        )),
        el('p', { class: 'step', text: t('gratitude.mood') }), mood.node,
        note.node,
        when.node, save, error,
      ],
    });
  }

  // ---------- reflect ----------

  const openQuestions = new Set(); // weekly questions expanded, kept across re-renders
  let questionsSeeded = false;

  /** A section card that doesn't fold (Reflect, Declarations, Progress). */
  function section({ eyebrow, title, aside, body, cls = '' }) {
    return el('section', { class: `card card--static ${cls}` },
      el('div', { class: 'card__summary' },
        el('span', { class: 'card__heading' },
          eyebrow ? el('span', { class: 'card__eyebrow', text: eyebrow }) : null,
          title ? el('span', { class: 'card__title', text: title }) : null),
        aside ? el('span', { class: 'card__done' }, aside) : null),
      el('div', { class: 'card__content' }, body));
  }

  function shortDate(key) {
    const [, m, d] = key.split('-').map(Number);
    return `${MONTHS[m - 1]} ${d}`;
  }

  function reflectView() {
    const w = state.weekly;
    const out = [
      el('h1', { class: 'top__greeting', text: t('reflect.title') }),
      el('p', { class: 'lede', text: t('reflect.lede') }),
    ];
    if (!w) return out;

    // First visit: open the first unanswered question.
    if (!questionsSeeded) {
      const first = w.questions.find((q) => !q.response.trim());
      if (first) openQuestions.add(first.key);
      questionsSeeded = true;
    }

    // "② of 7", updated in place as answers save.
    const tally = el('span', { class: 'reflect-tally' });
    const updateTally = () => {
      const n = weeklyAnswered();
      tally.replaceChildren(...(n ? [todayBadge(n), ` ${t('reflect.answered', { total: w.questions.length })}`] : []));
    };
    updateTally();

    out.push(section({
      eyebrow: t(w.this_week ? 'reflect.this_week' : 'reflect.last_week', { date: shortDate(w.week_start) }),
      title: w.title,
      aside: tally,
      body: [
        // Last week's stays editable until the next one opens.
        w.this_week ? null : el('p', { class: 'card__body', text: t('reflect.open_until', { day: WEEKDAYS[weekday(w.next_opens)] }) }),
        w.intro ? el('p', { class: 'card__body card__quote', text: w.intro }) : null,
        el('div', { class: 'qrows' }, w.questions.map((q, i) => questionRow(q, i, updateTally))),
      ],
    }));
    out.push(el('a', { class: 'more-link', href: '#history', onclick: () => { hist.mode = 'weekly'; } },
      icon('calendar-blank'), t('reflect.past')));
    return out;
  }

  /** One weekly question: a row with a check once answered; tap to write. */
  function questionRow(q, i, onSaved) {
    const id = `weekly-${i}`;
    const { node, field } = textField({ rows: 3, id });
    field.value = q.response;

    const status = el('span', { class: 'save-status', 'aria-live': 'polite' });
    const save = el('button', { class: 'btn', type: 'button', text: t('reflect.save') });
    let lastSaved = q.response;

    const row = el('details', { class: 'qrow', open: openQuestions.has(q.key) });
    row.addEventListener('toggle', () => {
      if (row.open) openQuestions.add(q.key);
      else openQuestions.delete(q.key);
    });
    const mark = el('span', { class: 'qrow__mark' }, icon(q.response.trim() ? 'check-circle' : 'circle'));

    async function doSave() {
      if (field.value === lastSaved) return;
      save.disabled = true;
      status.textContent = t('app.saving');
      try {
        await api('weekly', { method: 'POST', body: { key: q.key, response: field.value } });
        lastSaved = field.value;
        q.response = field.value;
        hist.weeks = null; // History reloads with this answer
        status.textContent = t('reflect.saved');
        row.classList.toggle('is-answered', !!q.response.trim());
        mark.replaceChildren(icon(q.response.trim() ? 'check-circle' : 'circle'));
        if (onSaved) onSaved();
        // Saved as you left the box for the History tab? Refresh what it shows.
        if (view === 'history' && hist.mode === 'weekly') render();
      } catch (err) {
        status.textContent = t('reflect.save_failed');
      } finally {
        save.disabled = false;
      }
    }

    field.addEventListener('input', () => { status.textContent = field.value === lastSaved ? '' : t('reflect.unsaved'); });
    field.addEventListener('blur', doSave);
    save.addEventListener('click', doSave);

    if (q.response.trim()) row.classList.add('is-answered');
    row.append(
      el('summary', { class: 'qrow__summary' },
        mark,
        el('span', { class: 'qrow__text' },
          q.text, q.optional ? el('span', { class: 'optional', text: ` · ${t('app.optional')}` }) : null,
          q.hint ? el('span', { class: 'qrow__hint', text: q.hint }) : null),
        icon('caret-down')),
      el('div', { class: 'qrow__body' },
        el('label', { class: 'sr-only', for: id, text: q.text }),
        node,
        el('div', { class: 'question__actions' }, save, status))
    );
    return row;
  }

  // ---------- declarations ----------

  /**
   * New declaration (no decl) or refine one. options.onCancel shows a Cancel
   * button; after saving, the whole page re-renders from the new state.
   */
  function declarationForm(decl, { onCancel } = {}) {
    const { node, field } = textField({
      rows: 2,
      placeholder: t('declarations.placeholder'),
      label: decl ? t('declarations.refine') : t('declarations.add'),
    });
    if (decl) field.value = decl.text;
    const error = el('p', { class: 'form-error', 'aria-live': 'polite' });
    const save = el('button', { class: 'btn btn--primary', type: 'button', text: decl ? t('declarations.save_refined') : t('declarations.save') });
    const cancel = onCancel ? el('button', { class: 'link', type: 'button', text: t('declarations.cancel') }) : null;
    if (cancel) cancel.addEventListener('click', onCancel);

    save.addEventListener('click', () => withButton(save, error, async () => {
      const res = await api('declaration', { method: 'POST', body: { id: decl ? decl.id : null, text: field.value } });
      state = res.state;
      render();
      toast(decl ? t('declarations.refined') : t('declarations.saved'));
    }));

    setTimeout(() => field.focus(), 0);
    return el('div', { class: 'decl-form' }, node, el('div', { class: 'decl-form__actions' }, save, cancel), error);
  }

  /** "47 reps" and a bar toward 300 (then 3,000), or "Embodied". */
  function declarationMeter(p) {
    const reps = p.count === 1 ? t('declarations.one_rep') : t('declarations.reps', { n: p.count.toLocaleString() });
    if (p.embodied) {
      return el('div', { class: 'meter' }, el('span', { text: reps }), el('span', { class: 'meter__done', text: t('declarations.embodied') }));
    }
    return el('div', { class: 'meter' },
      el('span', { text: reps }),
      el('span', { class: 'meter__bar', role: 'img', 'aria-label': `${p.count} / ${p.goal}` },
        el('span', { style: `width:${Math.max(p.pct, p.count ? 2 : 0)}%` })),
      el('span', { text: p.goal.toLocaleString() }));
  }

  async function declarationAction(action, id, message) {
    try {
      const res = await api(action, { method: 'POST', body: { id } });
      state = res.state;
      render();
      toast(message);
    } catch (err) {
      if (err.message !== 'auth') toast(errorText(err.message));
    }
  }

  function declarationsView() {
    const decls = state.declarations;
    const max = state.max_declarations;

    // The explainer starts open until there's a declaration; after that it
    // remembers whether you last left it open (on this device).
    let introOpen = !decls.length;
    try {
      const saved = localStorage.getItem('sj_decl_intro');
      if (saved && decls.length) introOpen = saved === 'open';
    } catch (e) { /* storage unavailable */ }
    const intro = el('details', { class: 'card intro', open: introOpen },
      el('summary', { class: 'card__summary' },
        el('span', { class: 'intro__icon' }, icon('compass')),
        el('span', { class: 'card__title', text: t('declarations.intro_title') })),
      el('div', { class: 'card__content' },
        el('p', { class: 'intro__text', text: t('declarations.intro') }),
        el('ul', { class: 'intro__points' }, (state.ui.declarations.intro_points || []).map((x) => el('li', { text: x })))));
    intro.addEventListener('toggle', () => {
      try { localStorage.setItem('sj_decl_intro', intro.open ? 'open' : 'closed'); } catch (e) { /* ignore */ }
    });

    const items = decls.map((d) => {
      const row = el('li', { class: 'decl' });
      const show = () => {
        const refine = el('button', { class: 'link', type: 'button', text: t('declarations.refine') });
        const retire = el('button', { class: 'link', type: 'button', text: t('declarations.retire') });
        refine.addEventListener('click', () => row.replaceChildren(declarationForm(d, { onCancel: show })));
        retire.addEventListener('click', () => {
          if (confirm(t('declarations.retire_confirm'))) declarationAction('declaration_retire', d.id, t('declarations.retired'));
        });
        row.replaceChildren(
          el('p', { class: 'decl__text', text: d.text }),
          declarationMeter(d.progress),
          el('div', { class: 'decl__actions' }, refine, retire));
      };
      show();
      return row;
    });

    const canAdd = decls.length < max;
    return [
      el('a', { class: 'back-link', href: `#${backTo}` }, icon('arrow-left'), t('app.back')),
      el('h1', { class: 'top__greeting', text: t('declarations.title') }),
      intro,
      section({
        eyebrow: t('declarations.eyebrow'),
        aside: el('span', { class: 'muted', text: t('declarations.count', { n: decls.length, max }) }),
        body: [
          items.length ? el('ul', { class: 'decls' }, items) : el('p', { class: 'card__body', text: t('declarations.empty') }),
          canAdd
            ? [el('p', { class: 'step', text: t('declarations.add') }), declarationForm(null)]
            : el('p', { class: 'card__body', text: t('declarations.full', { max }) }),
        ],
      }),
    ];
  }

  // ---------- time zone ----------

  /** This device's time zone, e.g. "America/Los_Angeles" (or '' if unknown). */
  function deviceZone() {
    try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { return ''; }
  }

  /** A friendly name: "Pacific Time", or the city for zones without one. */
  function zoneName(tz) {
    for (const style of ['longGeneric', 'long']) {
      try {
        const part = new Intl.DateTimeFormat('en-US', { timeZone: tz, timeZoneName: style })
          .formatToParts(new Date()).find((x) => x.type === 'timeZoneName');
        if (part && !/^GMT[+-]/.test(part.value)) return part.value;
      } catch (e) { /* unknown zone or option */ }
    }
    return tz.split('/').pop().replace(/_/g, ' ');
  }

  function timeIn(tz) {
    try {
      return new Date().toLocaleTimeString('en-US', { timeZone: tz, hour: 'numeric', minute: '2-digit' });
    } catch (e) { return ''; }
  }

  /** Saves settings ({ timezone, week_start }) and re-renders. */
  async function saveSettings(body, message) {
    try {
      const res = await api('settings', { method: 'POST', body });
      state = res.state;
      hist.months = {}; hist.days = {}; hist.month = null; hist.selected = null; hist.weeks = null; // "today" and the week may have moved
      render();
      if (message) toast(message);
    } catch (err) {
      if (err.message !== 'auth') toast(errorText(err.message));
    }
  }

  /**
   * On Today, when this device is in a different zone than your practice days
   * (traveling, a new phone): offer to switch. "Keep" is remembered per pair.
   */
  function timezonePrompt() {
    const device = deviceZone();
    if (!state.timezone_set || !device || device === state.timezone) return null;
    const key = `${device}|${state.timezone}`;
    try { if (localStorage.getItem('sj_tz_keep') === key) return null; } catch (e) { /* ignore */ }

    const vars = { device: zoneName(device), current: zoneName(state.timezone) };
    const box = el('div', { class: 'notice', role: 'status' });
    const switchBtn = el('button', { class: 'btn btn--small', type: 'button', text: t('timezone_prompt.switch', vars) });
    const keepBtn = el('button', { class: 'link', type: 'button', text: t('timezone_prompt.keep', vars) });
    switchBtn.addEventListener('click', () => saveSettings({ timezone: device }, t('settings.saved')));
    keepBtn.addEventListener('click', () => {
      try { localStorage.setItem('sj_tz_keep', key); } catch (e) { /* ignore */ }
      box.remove();
    });
    box.append(
      el('p', {}, icon('clock'), ` ${t('timezone_prompt.text', vars)}`),
      el('div', { class: 'notice__actions' }, switchBtn, keepBtn));
    return box;
  }

  // ---------- settings ----------

  /**
   * Invite a friend: the public welcome page. On phones, Share opens the
   * share sheet (Messages, email…); elsewhere it copies the link.
   */
  function inviteCard() {
    const url = USP.welcome;
    if (!url) return null; // turned off in config (show_invite)
    const share = el('button', { class: 'btn', type: 'button' }, icon('share-network'), ` ${t('welcome.share')}`);
    share.addEventListener('click', async () => {
      if (navigator.share) {
        try { await navigator.share({ title: state.app_name, text: t('welcome.share_text'), url }); return; } catch (e) {
          if (e && e.name === 'AbortError') return; // closed the share sheet
        }
      }
      try { await navigator.clipboard.writeText(url); toast(t('welcome.copied')); } catch (e) { window.prompt('', url); }
    });
    return section({
      eyebrow: t('welcome.share_title'),
      body: [
        el('p', { class: 'card__body', text: t('welcome.share_body') }),
        el('div', { class: 'invite-row' }, el('code', { text: url.replace(/^https?:\/\//, '') }), share),
        el('a', { class: 'more-link', href: url, target: '_blank', rel: 'noopener' }, t('welcome.preview')),
      ],
    });
  }

  /** Beta choices live in cookies, so the page header follows them too. */
  function setBeta(name, value) {
    document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=31536000; SameSite=Lax`;
    window.location.reload();
  }

  function cookie(name) {
    const m = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return m ? decodeURIComponent(m[1]) : '';
  }

  function settingsView() {
    const device = deviceZone();
    let zones = [];
    try { zones = Intl.supportedValuesOf('timeZone'); } catch (e) { /* older browser */ }
    for (const z of [state.timezone, device, 'UTC']) if (z && !zones.includes(z)) zones.push(z);
    zones.sort();

    // Time zone
    const now = el('p', { class: 'card__body' });
    const select = el('select', { class: 'field field--select', id: 'tz', 'aria-label': t('settings.timezone_title') },
      zones.map((z) => el('option', { value: z, selected: z === state.timezone, text: z.replace(/_/g, ' ') })));
    const showNow = () => { now.textContent = `${zoneName(select.value)}. ${t('settings.now', { time: timeIn(select.value) })}`; };
    select.addEventListener('change', showNow);
    showNow();
    const useDevice = device && device !== state.timezone
      ? el('button', { class: 'link', type: 'button', text: t('settings.use_device', { zone: zoneName(device) }) })
      : null;
    if (useDevice) useDevice.addEventListener('click', () => { select.value = device; showNow(); });

    // Week start
    let weekStart = state.week_start;
    const weekButtons = [[0, t('settings.sunday')], [1, t('settings.monday')]].map(([v, label]) => {
      const b = el('button', { type: 'button', 'aria-pressed': String(v === weekStart), text: label });
      b.addEventListener('click', () => {
        weekStart = v;
        weekButtons.forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      });
      return b;
    });

    const save = el('button', { class: 'btn btn--primary', type: 'button', text: t('settings.save') });
    save.addEventListener('click', () => saveSettings({ timezone: select.value, week_start: weekStart }, t('settings.saved')));

    // Beta: try a name, and an icon style. Each applies right away.
    const ui = state.ui;
    const betaRow = (label, options, current, onPick) => el('div', { class: 'beta-row' },
      el('span', { class: 'beta-row__label', text: label }),
      el('div', { class: 'segmented', role: 'group', 'aria-label': label }, options.map(([value, text]) => {
        const b = el('button', { type: 'button', 'aria-pressed': String(value === current), text });
        b.addEventListener('click', () => onPick(value));
        return b;
      })));
    const beta = [];
    if (ui.name_test && ui.name_test.options && ui.name_test.options.length > 1) {
      beta.push(betaRow(ui.name_test.label, ui.name_test.options.map((n) => [n, n]),
        cookie('sj_name') || state.app_name, (v) => setBeta('sj_name', v)));
    }
    if (ui.icon_test && ui.icon_test.b) {
      beta.push(betaRow(ui.icon_test.label, [['a', 'A'], ['b', 'B']], state.icon_variant, (v) => setBeta('sj_icons', v)));
    }

    return [
      el('a', { class: 'back-link', href: `#${backTo}` }, icon('arrow-left'), t('app.back')),
      el('h1', { class: 'top__greeting', text: t('settings.title') }),
      section({
        eyebrow: t('settings.account_title'),
        body: el('div', { class: 'account-row' },
          el('p', { class: 'card__body' }, `${t('nav.signed_in_as')} `, el('strong', { text: state.user.name })),
          el('a', { class: 'account-row__out', href: USP.logout }, icon('sign-out'), ` ${t('nav.log_out')}`)),
      }),
      el('a', { class: 'btn btn--feedback', href: '#feedback' }, icon('chat-circle-dots'), ` ${t('nav.feedback_form')}`),
      inviteCard(),
      section({
        eyebrow: t('settings.timezone_title'),
        body: [el('p', { class: 'card__body', text: t('settings.timezone_body') }), select, now, useDevice],
      }),
      section({
        eyebrow: t('settings.week_title'),
        body: [
          el('p', { class: 'card__body', text: t('settings.week_body') }),
          el('div', { class: 'segmented', role: 'group', 'aria-label': t('settings.week_start') }, weekButtons),
        ],
      }),
      save,
      beta.length ? section({ eyebrow: t('settings.beta_title'), body: [el('p', { class: 'card__body', text: t('settings.beta_body') }), beta] }) : null,
      section({
        eyebrow: t('settings.about_title'),
        body: el('p', { class: 'card__body', text: t('settings.version', { version: state.build.label }) }),
      }),
    ];
  }

  // ---------- feedback ----------

  function feedbackView() {
    const f = state.ui.feedback;
    const error = el('p', { class: 'form-error', 'aria-live': 'polite' });
    const note = textField({ rows: 4, label: f.title });

    const kind = choiceGroup(
      Object.entries(f.kinds).map(([value, text]) => ({ value, text })),
      { label: f.kind_label, selected: 'issue', onChange: (k) => { note.field.placeholder = f.placeholders[k] || ''; } }
    );
    note.field.placeholder = f.placeholders.issue || '';

    // The name poll, with this person's last vote preselected.
    const mine = state.my_vote;
    const other = el('input', { class: 'field field--line', type: 'text', maxlength: 100, placeholder: f.other_hint, 'aria-label': f.other });
    if (mine && mine.vote === 'other') other.value = mine.other;
    const vote = choiceGroup(
      [...((state.ui.name_test && state.ui.name_test.options) || []).map((name) => ({ value: name, text: name })), { value: 'other', text: f.other }],
      { label: f.poll_title, selected: mine ? mine.vote : null, onChange: (v) => { other.hidden = v !== 'other'; if (v === 'other') other.focus(); } }
    );
    other.hidden = !(mine && mine.vote === 'other');

    const send = el('button', { class: 'btn btn--primary', type: 'button', text: f.send });
    send.addEventListener('click', () => withButton(send, error, async () => {
      const res = await api('feedback', {
        method: 'POST',
        body: { kind: kind.value, message: note.field.value, vote: vote.value || '', other: other.value, screen: backTo },
      });
      state.my_vote = res.my_vote;
      toast(f.sent);
      location.hash = backTo;
    }));

    return [
      el('a', { class: 'back-link', href: `#${backTo}` }, icon('arrow-left'), t('app.back')),
      el('h1', { class: 'top__greeting', text: f.title }),
      el('p', { class: 'lede', text: f.intro }),
      section({ eyebrow: f.kind_label, body: [kind.node, note.node] }),
      section({
        eyebrow: f.poll_title,
        body: [
          el('p', { class: 'card__body', text: f.poll_body }),
          vote.node, other,
          mine ? el('p', { class: 'muted small', text: t('feedback.your_vote', { vote: mine.vote === 'other' ? mine.other : mine.vote }) }) : null,
        ],
      }),
      el('p', { class: 'muted small', text: t('feedback.privacy', { version: state.build.label }) }),
      send, error,
    ];
  }

  // ---------- progress ----------

  function progressView() {
    // A ring with "count / next" under it, or just the count when there's no
    // milestone (conscious rest). The caption line stays so tiles line up.
    const tile = ({ label, time, r, count, today }) => el('div', { class: 'rep' },
      r ? ring(r, 'ring--lg') : el('span', { class: 'rep__plain' }, el('span', { class: 'ring__n', text: String(count) })),
      el('span', { class: 'rep__label' }, time ? el('span', { class: 'rep__time', text: time }) : null, label),
      el('span', { class: 'rep__next' }, today ? todayBadge(today) : null, r ? `${r.count} / ${r.next}` : '')
    );

    const rituals = Object.entries(state.rituals).map(([type, r]) =>
      tile({ label: t(`checklist.${type}`), time: t(`progress.time_${type}`), r: r.ring, today: state.today_counts[type] }));
    const skills = state.skills.map((s) =>
      tile({ label: s.title, r: s.ring, count: s.count, today: state.today_skills[s.key] }));

    // Each declaration with the practices done under it (this declaration only).
    const toward = (p) => (p.embodied
      ? t('progress.embodied', { n: p.count.toLocaleString() })
      : t('progress.toward', { n: p.count.toLocaleString(), goal: p.goal.toLocaleString() }));
    const byDecl = (d, retired) => el('div', { class: `bydecl ${retired ? 'bydecl--retired' : ''}` },
      el('p', { class: 'bydecl__text', text: d.text }),
      // Active: the bar says it all. Retired or embodied: a line of text.
      !retired && !d.progress.embodied ? declarationMeter(d.progress) : el('p', { class: 'bydecl__toward', text: toward(d.progress) }),
      d.skills.length
        ? el('ul', { class: 'bydecl__skills' }, d.skills.map((s) =>
          el('li', {}, el('span', { text: s.title }), s.today ? todayBadge(s.today) : null, el('b', { text: String(s.count) }))))
        : el('p', { class: 'card__body', text: t('progress.no_reps') }),
      retired ? reactivateButton(d) : null);

    function reactivateButton(d) {
      const b = el('button', { class: 'link', type: 'button' }, icon('arrow-counter-clockwise'), ` ${t('declarations.reactivate')}`);
      b.addEventListener('click', () => {
        if (state.declarations.length >= state.max_declarations) {
          toast(t('declarations.full', { max: state.max_declarations }));
          return;
        }
        declarationAction('declaration_reactivate', d.id, t('declarations.reactivated'));
      });
      return b;
    }

    const declSection = state.declarations.length || state.retired.length
      ? section({
        eyebrow: t('progress.declarations_eyebrow'),
        title: t('progress.declarations_title'),
        body: [
          state.declarations.map((d) => byDecl(d, false)),
          state.retired.length
            ? [el('p', { class: 'step bydecl__heading', text: t('progress.retired') }), state.retired.map((d) => byDecl(d, true))]
            : null,
        ],
      })
      : null;

    return [
      counters(),
      section({ eyebrow: t('progress.rituals_eyebrow'), title: t('progress.rituals_title'), body: el('div', { class: 'reps reps--rituals' }, rituals) }),
      declSection,
      section({
        eyebrow: t('progress.skills_eyebrow'),
        title: t('progress.skills_title'),
        body: [el('p', { class: 'card__body', text: t('progress.skills_body') }), el('div', { class: 'reps' }, skills)],
      }),
      el('a', { class: 'more-link', href: '#history', onclick: () => { hist.mode = 'daily'; } },
        icon('calendar-blank'), ` ${t('progress.notes_link')}`),
    ];
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
    const toggle = el('div', { class: 'segmented', role: 'group', 'aria-label': t('nav.history') },
      [['daily', t('history.daily')], ['weekly', t('history.weekly')]].map(([key, label]) => {
        const b = el('button', { type: 'button', 'aria-pressed': String(hist.mode === key), text: label });
        b.addEventListener('click', () => { hist.mode = key; render(); });
        return b;
      })
    );
    const body = el('div', { class: 'weeks' }, el('p', { class: 'loading', text: t('history.loading') }));

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
      if (err.message !== 'auth') body.replaceChildren(el('p', { class: 'daylog__empty', text: t('history.load_error') }));
    }
  }

  function calendar(data) {
    const [y, m] = data.month.split('-').map(Number);
    const ws = state.week_start; // 0 = Sunday first, 1 = Monday first
    const firstDow = (new Date(Date.UTC(y, m - 1, 1)).getUTCDay() - ws + 7) % 7;
    const daysInMonth = new Date(Date.UTC(y, m, 0)).getUTCDate();

    const prev = el('button', { class: 'calendar__nav', type: 'button', 'aria-label': t('history.previous_month'), text: '‹' });
    const next = el('button', { class: 'calendar__nav', type: 'button', 'aria-label': t('history.next_month'), text: '›' });
    prev.disabled = data.month <= data.first_month;
    next.disabled = data.month >= data.current_month;
    prev.addEventListener('click', () => { hist.month = shiftMonth(data.month, -1); render(); });
    next.addEventListener('click', () => { hist.month = shiftMonth(data.month, 1); render(); });

    const grid = el('div', { class: 'calendar__grid' },
      [0, 1, 2, 3, 4, 5, 6].map((i) => WEEKDAYS[(i + ws) % 7]).map((d) =>
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
        'aria-label': `${longDate(key)}: ${n === 1 ? t('history.one_rep') : n ? t('history.reps', { n }) : t('history.no_entries')}`,
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
        swatch('border:1.5px solid var(--rasp)', t('history.one_rep')),
        swatch('background:var(--rasp-soft)', t('history.legend_some')),
        swatch('background:var(--rasp)', t('history.legend_more')),
        swatch('outline:1.5px solid var(--teal);outline-offset:1px', t('history.legend_today'))
      )
    );
  }

  /** A mood chip: icon and label. */
  function moodTag(mood) {
    return mood ? el('span', { class: 'daylog__mood' }, icon(moodIcon(mood)), mood.label) : null;
  }

  /**
   * A day in two parts. Rituals: clearing, then gratitude. Practices: grouped
   * by declaration, each rep on its own line in the order it was logged, so
   * the flow of the day shows. Empty parts are left out.
   */
  function daySections(items) {
    const quote = (text) => (text ? el('p', { class: 'daylog__line', text: `“${text}”` }) : null);
    const row = (name, item, ...lines) => el('div', { class: 'daylog__item' },
      el('div', { class: 'daylog__name' }, el('span', { text: name }), moodTag(item.mood)),
      lines);

    const rituals = ['clearing', 'gratitude'].flatMap((type) => items.filter((i) => i.type === type))
      .map((i) => row(i.title, i, i.summary ? el('p', { class: 'daylog__line', text: i.summary }) : null, quote(i.note)));

    // Declarations in the order first practiced under; reps in logged order.
    const groups = new Map();
    for (const item of items.filter((i) => i.type === 'practice')) {
      const key = item.decl_id || 0;
      if (!groups.has(key)) groups.set(key, { text: item.decl_text, reps: [] });
      groups.get(key).reps.push(item);
    }
    const practices = [...groups.values()].map((g) => el('div', { class: 'daylog__group' },
      g.text ? el('p', { class: 'daylog__decl', text: g.text }) : null,
      g.reps.map((r) => row(r.skill || r.title, r, quote(r.note)))));

    const section = (label, body) => (body.length
      ? el('div', { class: 'daylog__section' }, el('h3', { class: 'daylog__heading', text: label }), body)
      : null);

    return [
      section(t('history.section_rituals'), rituals),
      section(t('history.section_practices'), practices),
    ].filter(Boolean);
  }

  function dayLog() {
    const date = hist.selected;
    const box = el('section', { class: 'daylog', 'aria-live': 'polite' },
      el('h2', { class: 'daylog__title', text: date === state.today ? t('history.today') : longDate(date) }),
      el('p', { class: 'daylog__empty', text: t('history.loading') })
    );

    loadDay(date).then((day) => {
      const title = box.firstChild;
      if (!day.items.length) {
        box.replaceChildren(title, el('p', { class: 'daylog__empty', text: t('history.no_entries') }));
        return;
      }
      box.replaceChildren(title, ...daySections(day.items));
    }).catch((err) => {
      if (err.message !== 'auth') box.replaceChildren(box.firstChild, el('p', { class: 'daylog__empty', text: t('history.day_error') }));
    });

    return box;
  }

  async function fillWeekly(body) {
    try {
      if (!hist.weeks) hist.weeks = (await api('history_weekly')).weeks;

      // The open reflection always comes first (even before it has answers),
      // then the rest newest first. Weeks are matched by their Thursday key.
      const current = state.weekly ? state.weekly.week_key : null;
      const weeks = hist.weeks.filter((w) => w.week_key !== current);
      if (current) weeks.unshift(hist.weeks.find((w) => w.week_key === current) || { week_key: current, week_start: state.weekly.week_start, answers: [] });
      if (!weeks.length) {
        body.replaceChildren(el('p', { class: 'daylog__empty', text: t('history.no_reflections') }));
        return;
      }

      body.replaceChildren(...weeks.map((w) => {
        const [y, m, d] = w.week_start.split('-').map(Number);
        const isCurrent = w.week_key === current;
        const n = w.answers.length;
        const total = isCurrent ? state.weekly.questions.length : 0;
        return el('details', { class: `week ${isCurrent ? 'week--current' : ''}`, open: isCurrent || weeks.length === 1 },
          el('summary', {},
            el('span', { class: 'week__heading' },
              isCurrent ? el('span', { class: 'week__badge', text: t(state.weekly.this_week ? 'history.this_week' : 'history.last_week') }) : null,
              el('span', { class: 'week__title', text: t('history.week_of', { date: `${MONTHS[m - 1]} ${d}, ${y}` }) })
            ),
            el('span', { class: 'week__count', text: isCurrent ? t('checklist.weekly_progress', { n, total }) : (n === 1 ? t('history.one_answer') : t('history.answers', { n })) })
          ),
          el('div', { class: 'week__answers' },
            n
              ? w.answers.map((a) => el('div', {}, el('p', { class: 'week__q', text: a.question }), el('p', { class: 'week__a', text: a.answer })))
              : el('p', { class: 'daylog__empty' }, `${t('history.not_answered')} `, el('a', { href: '#reflect', text: t('history.go_reflect') }))
          )
        );
      }));
    } catch (err) {
      if (err.message !== 'auth') body.replaceChildren(el('p', { class: 'daylog__empty', text: t('history.load_error') }));
    }
  }

  // ---------- render ----------

  function todayView() {
    return [
      el('h1', { class: 'top__greeting', text: greeting(state.user.name) }),
      timezonePrompt(),
      counters(),
      ritualList(),
      clearingCard(),
      practiceCard(),
      gratitudeCard(),
    ];
  }

  function render() {
    // Keep the scroll position steady across re-renders of the same view.
    const y = window.scrollY;
    const views = { today: todayView, progress: progressView, reflect: reflectView, history: historyView, declarations: declarationsView, settings: settingsView, feedback: feedbackView };
    // Skip empty slots (e.g. no time zone prompt today), which would otherwise show as "null".
    root.replaceChildren(...views[view]().flat(Infinity).filter(Boolean));
    window.scrollTo(0, y);
  }

  const VIEWS = ['today', 'progress', 'reflect', 'history', 'declarations', 'settings', 'feedback'];
  const SCREENS = ['declarations', 'settings', 'feedback']; // opened from the top bar, with Back
  let backTo = 'today'; // where Back goes from those screens

  function setView() {
    const hash = location.hash.slice(1);
    const next = VIEWS.includes(hash) ? hash : 'today';
    const changed = next !== view;
    if (SCREENS.includes(next) && !SCREENS.includes(view)) backTo = view;
    view = next;
    document.querySelectorAll('.tabbar__tab, .masthead__icon').forEach((tab) => {
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
      openCards.add('practice'); // open on arrival: the main thing you come to do
      render();
      // First visit: practice days follow this device's time zone.
      const device = deviceZone();
      if (!state.timezone_set && device) saveSettings({ timezone: device });
    })
    .catch((err) => {
      if (err.message !== 'auth') {
        root.replaceChildren(el('p', { class: 'loading', text: USP.loadError }));
      }
    });
})();
