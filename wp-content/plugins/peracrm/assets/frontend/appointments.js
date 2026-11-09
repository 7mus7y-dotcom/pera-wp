/* Native CRM calendar and one shared booking dialog; no third-party runtime. */
(function (root) {
  "use strict";
  const dateKey = (d) => d.toISOString().slice(0, 10);
  const day = (s) => new Date(s.slice(0, 10) + "T00:00:00Z");
  const addDays = (s, n) => {
    const d = day(s);
    d.setUTCDate(d.getUTCDate() + n);
    return dateKey(d);
  };
  function period(date, view) {
    let start = date,
      end;
    if (view === "week") {
      start = addDays(date, -((day(date).getUTCDay() + 6) % 7));
      end = addDays(start, 7);
    } else if (view === "month" || view === "list") {
      const d = day(date);
      d.setUTCDate(1);
      start = dateKey(d);
      d.setUTCMonth(d.getUTCMonth() + 1);
      end = dateKey(d);
    } else {
      end = addDays(start, 1);
    }
    return { start, end };
  }
  function local(instant, zone) {
    const parts = new Intl.DateTimeFormat("en-CA", {
      timeZone: zone,
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      hourCycle: "h23",
    }).formatToParts(new Date(instant));
    const p = {};
    parts.forEach((v) => {
      p[v.type] = v.value;
    });
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
  }
  function layout(events, date, zone) {
    const items = events
      .map((e) => {
        const s = local(e.start_at, zone),
          end = local(e.end_at, zone);
        return {
          event: e,
          start:
            s.slice(0, 10) < date
              ? 0
              : Number(s.slice(11, 13)) * 60 + Number(s.slice(14)),
          end:
            end.slice(0, 10) > date
              ? 1440
              : Number(end.slice(11, 13)) * 60 + Number(end.slice(14)),
        };
      })
      .filter(
        (e) =>
          local(e.event.start_at, zone).slice(0, 10) <= date &&
          local(e.event.end_at, zone).slice(0, 10) >= date &&
          e.end > e.start,
      )
      .sort((a, b) => a.start - b.start || b.end - a.end);
    let cluster = [],
      active = [],
      width = 1;
    function finish() {
      cluster.forEach((e) => (e.columns = width));
      cluster = [];
      width = 1;
    }
    items.forEach((e) => {
      active = active.filter((a) => a.end > e.start);
      if (!active.length) finish();
      let col = 0;
      while (active.some((a) => a.column === col)) col++;
      e.column = col;
      active.push(e);
      cluster.push(e);
      width = Math.max(width, col + 1);
    });
    finish();
    return items;
  }
  const helpers = { period, local, layout, addDays };
  if (typeof module === "object" && module.exports) {
    module.exports = helpers;
    return;
  }
  const requestKey = () =>
    Array.from(root.crypto.getRandomValues(new Uint8Array(16)), (b) =>
      b.toString(16).padStart(2, "0"),
    ).join("");
  const cfg = root.peraAppointments;
  if (!cfg) return;
  const $ = (s, scope = document) => scope.querySelector(s);
  const $$ = (s, scope = document) => Array.from(scope.querySelectorAll(s));
  function el(tag, text, cls) {
    const e = document.createElement(tag);
    if (text !== undefined) e.textContent = text;
    if (cls) e.className = cls;
    return e;
  }
  function button(text, fn, cls = "btn btn--ghost btn--blue") {
    const b = el("button", text, cls);
    b.type = "button";
    b.addEventListener("click", fn);
    return b;
  }
  async function api(payload) {
    const body = new FormData();
    body.set("action", "peracrm_appointments");
    body.set("nonce", cfg.nonce);
    body.set("payload", JSON.stringify(payload));
    const abort = new AbortController();
    const timer = setTimeout(() => abort.abort(), 30000);
    try {
      const r = await fetch(cfg.url, {
        method: "POST",
        credentials: "same-origin",
        body,
        signal: abort.signal,
      });
      const j = await r.json();
      if (!j.success) {
        const e = new Error(j.data?.message || "Unable to load appointments.");
        e.code = j.data?.code;
        e.details = j.data?.details;
        throw e;
      }
      return j.data;
    } finally {
      clearTimeout(timer);
    }
  }
  const label = (e) =>
    e.restricted
      ? "Reserved · " + (cfg.types[e.type] || e.type)
      : (e.title || cfg.types[e.type]) + " · " + e.client_name;
  const when = (e, zone = cfg.timezone) =>
    local(e.start_at, zone).replace("T", " ") +
    " – " +
    local(e.end_at, zone).replace("T", " ");
  function summary(e, zone = cfg.timezone) {
    return (
      label(e) +
      (e.title ? " · " + cfg.types[e.type] : "") +
      " · " +
      when(e, zone) +
      " · " +
      e.advisor_name +
      " · " +
      cfg.statuses[e.status]
    );
  }
  function eventButton(e, zone, compact = false) {
    const text = compact
      ? local(e.start_at, zone).slice(11) +
        " · " +
        (e.restricted ? "Reserved" : e.title || cfg.types[e.type]) +
        "\n" +
        (e.restricted ? "" : e.client_name + "\n") +
        e.advisor_name +
        " · " +
        cfg.statuses[e.status]
      : summary(e, zone);
    const b = button(text, () => details(e.id), "appointment-event");
    b.setAttribute("aria-label", summary(e, zone));
    b.title = summary(e, zone);
    b.dataset.status = e.status;
    return b;
  }
  let dialog,
    form,
    feedback,
    context,
    returnFocus,
    saving = false,
    lastPayload = null;
  function close() {
    if (saving) return;
    dialog.close();
    if (context?.statusForm) {
      const s = $('[name="lead_pipeline_stage"]', context.statusForm);
      if (s && context.originalStage) s.value = context.originalStage;
    }
    returnFocus?.focus();
  }
  function field(name, labelText, type = "text", required = false) {
    const wrap = el("label", undefined, "appointment-field");
    wrap.append(el("span", labelText));
    const input = el(
      type === "textarea" ? "textarea" : type === "select" ? "select" : "input",
    );
    if (!["textarea", "select"].includes(type)) input.type = type;
    input.name = name;
    input.required = required;
    wrap.append(input);
    form.append(wrap);
    return input;
  }
  function options(input, values) {
    Object.entries(values).forEach(([value, text]) => {
      const o = el("option", text);
      o.value = value;
      input.append(o);
    });
  }
  function ensureDialog() {
    if (dialog) return;
    dialog = el("dialog", undefined, "appointment-dialog crm-page");
    dialog.setAttribute("aria-labelledby", "appointment-dialog-title");
    const header = el("header", undefined, "appointment-toolbar");
    const h = el("h2", "Appointment");
    h.id = "appointment-dialog-title";
    header.append(h, button("Close", close));
    dialog.append(header);
    const intro = el("div");
    intro.dataset.bookingIntro = "";
    dialog.append(intro);
    form = el("form", undefined, "appointment-form crm-form-stack");
    const clientSearch = field("client_search", "Search client", "search");
    clientSearch.autocomplete = "off";
    const results = el("div");
    results.dataset.clientResults = "";
    form.append(results);
    const selected = el("p");
    selected.dataset.selectedClient = "";
    form.append(selected);
    field("client_id", "Client ID", "hidden", true);
    field("id", "", "hidden");
    field("version", "", "hidden");
    options(field("type", "Appointment type", "select", true), cfg.types);
    field("title", "Title");
    field("date", "Date", "date", true);
    field("start_time", "Start time", "time", true);
    field("end_date", "End date", "date", true);
    field("end_time", "End time", "time", true);
    const timezone = field("timezone", "Timezone", "select", true);
    const zones = Intl.supportedValuesOf
      ? Intl.supportedValuesOf("timeZone")
      : ["Europe/Istanbul", "Europe/London", "America/New_York", "UTC"];
    [...new Set(["Europe/Istanbul", "UTC", ...zones])]
      .sort()
      .forEach((z) => options(timezone, { [z]: z }));
    const advisor = field("advisor_id", "Primary advisor", "select", true);
    cfg.staff.forEach((u) => options(advisor, { [u.id]: u.name }));
    const participants = field(
      "participant_ids",
      "Additional employees (Ctrl / Command to select multiple)",
      "select",
    );
    participants.multiple = true;
    participants.size = 4;
    cfg.staff.forEach((u) => options(participants, { [u.id]: u.name }));
    options(field("status", "Status", "select", true), cfg.statuses);
    options(field("location_type", "Location", "select"), {
      other: "Other",
      property: "Property",
      office: "Office",
      hotel: "Hotel",
      online: "Online",
    });
    field("location_name", "Location name");
    field("address", "Street address");
    field("map_url", "Map URL", "url");
    field("meeting_url", "Online meeting URL", "url");
    field("hotel_name", "Hotel name");
    const attendees = field("attendees", "Number attending", "number", true);
    attendees.min = 1;
    attendees.step = 1;
    const property = field(
      "property_search",
      "Search properties / tour stops",
      "search",
    );
    property.autocomplete = "off";
    const propertyHelp = el(
      "p",
      "Properties appear in tour order. Use Move up to reorder.",
    );
    propertyHelp.dataset.propertyHelp = "";
    form.append(propertyHelp);
    const propertyResults = el("div");
    propertyResults.dataset.propertyResults = "";
    form.append(propertyResults);
    const properties = el("ol");
    properties.dataset.selectedProperties = "";
    form.append(properties);
    field("internal_notes", "Internal notes", "textarea");
    field("outcome_notes", "Outcome notes", "textarea");
    feedback = el("div", undefined, "appointment-feedback");
    feedback.tabIndex = -1;
    feedback.setAttribute("role", "status");
    feedback.setAttribute("aria-live", "polite");
    form.append(feedback);
    const actions = el("div", undefined, "appointment-toolbar");
    const submit = el("button", "Save appointment", "btn btn--solid btn--blue");
    submit.type = "submit";
    actions.append(submit, button("Dismiss", close));
    form.append(actions);
    dialog.append(form);
    document.body.append(dialog);
    form.addEventListener("submit", save);
    dialog.addEventListener("cancel", (e) => {
      e.preventDefault();
      close();
    });
    form.elements.location_type.addEventListener("change", conditional);
    form.elements.type.addEventListener("change", conditional);
    form.elements.start_time.addEventListener("change", () => {
      const [h, m] = form.elements.start_time.value.split(":").map(Number);
      const total = h * 60 + m + 60;
      form.elements.end_time.value =
        String(Math.floor(total / 60) % 24).padStart(2, "0") +
        ":" +
        String(total % 60).padStart(2, "0");
      form.elements.end_date.value = addDays(
        form.elements.date.value,
        total >= 1440 ? 1 : 0,
      );
    });
    form.elements.date.addEventListener("change", () => {
      form.elements.end_date.value = form.elements.date.value;
    });
    search(clientSearch, "client", results, async (item) => {
      try {
        await selectClient(item.id);
      } catch (e) {
        error(e);
      }
    });
    search(property, "property", propertyResults, (item) => {
      if (!context.properties.some((p) => p.id === item.id)) {
        context.properties.push(item);
        renderProperties();
      }
      property.value = "";
    });
    // Keep one command key for the life of the form, including uncertain network retries.
    form.addEventListener("input", () => {
      if (context) {
        lastPayload = null;
      }
    });
  }
  function conditional() {
    const f = form.elements;
    f.hotel_name.closest("label").hidden = f.location_type.value !== "hotel";
    f.meeting_url.closest("label").hidden = f.location_type.value !== "online";
    $("[data-property-help]", form).textContent =
      f.type.value === "property_tour"
        ? "Multiple property tour: add and order all stops below."
        : "Optional properties, in tour order.";
  }
  function search(input, kind, results, onSelect) {
    let timer,
      sequence = 0;
    input.addEventListener("input", () => {
      clearTimeout(timer);
      const n = ++sequence;
      results.replaceChildren();
      if (input.value.trim().length < 2) return;
      timer = setTimeout(async () => {
        try {
          const items = await api({
            operation: "search",
            kind,
            term: input.value,
          });
          if (n !== sequence) return;
          results.replaceChildren();
          items.forEach((i) =>
            results.append(
              button(
                i.title +
                  (i.detail ? " · " + i.detail : "") +
                  " (#" +
                  i.id +
                  ")",
                () => {
                  sequence++;
                  results.replaceChildren();
                  onSelect(i);
                },
                "appointment-search-result",
              ),
            ),
          );
          if (!items.length) results.append(el("p", "No matching records."));
        } catch (e) {
          if (n === sequence) results.textContent = e.message;
        }
      }, 250);
    });
  }
  function renderProperties() {
    const list = $("[data-selected-properties]", form);
    list.replaceChildren();
    context.properties.forEach((p, i) => {
      const row = el("li");
      row.append(
        el("span", p.title + " (#" + p.id + ")"),
        button("Move up", () => {
          if (i) {
            [context.properties[i - 1], context.properties[i]] = [
              context.properties[i],
              context.properties[i - 1],
            ];
            lastPayload = null;
            renderProperties();
          }
        }),
        button("Remove", () => {
          context.properties.splice(i, 1);
          lastPayload = null;
          renderProperties();
        }),
      );
      list.append(row);
    });
  }
  async function selectClient(id) {
    const c = await api({ operation: "client", client_id: id });
    context.client = c;
    form.elements.client_id.value = c.id;
    form.elements.advisor_id.value = c.advisor_id;
    form.elements.client_search.value = c.name;
    $("[data-selected-client]", form).textContent = "Client: " + c.name;
    return c;
  }
  function error(e) {
    feedback.replaceChildren(el("p", e.message || "Unable to save."));
    feedback.focus();
  }
  async function openBooking(clientId = 0, opts = {}) {
    ensureDialog();
    if (!dialog.open) returnFocus = document.activeElement;
    form.reset();
    form.hidden = false;
    feedback.replaceChildren();
    context = { ...opts, properties: [], key: requestKey() };
    lastPayload = null;
    const f = form.elements;
    f.id.value = "";
    f.version.value = "";
    f.client_id.value = "";
    f.timezone.value = cfg.timezone;
    f.status.value = "confirmed";
    f.attendees.value = "2";
    f.type.value = opts.pipeline ? "property_viewing" : "office_consultation";
    const now = local(new Date().toISOString(), cfg.timezone);
    f.date.value = now.slice(0, 10);
    f.end_date.value = f.date.value;
    f.start_time.value = "10:00";
    f.end_time.value = "11:00";
    f.client_search.disabled = !!clientId;
    f.client_search.closest("label").hidden = !!clientId;
    $("[data-selected-client]", form).textContent = "Select a client.";
    $("[data-booking-intro]", dialog).replaceChildren();
    renderProperties();
    conditional();
    dialog.showModal();
    try {
      if (clientId) await selectClient(clientId);
      if (opts.row) {
        const e = opts.row;
        for (const name of [
          "id",
          "version",
          "type",
          "title",
          "status",
          "timezone",
          "advisor_id",
          "location_type",
          "location_name",
          "address",
          "map_url",
          "meeting_url",
          "hotel_name",
          "attendees",
          "internal_notes",
          "outcome_notes",
        ]) {
          if (f[name]) f[name].value = e[name] ?? "";
        }
        const s = local(e.start_at, e.timezone),
          end = local(e.end_at, e.timezone);
        f.date.value = s.slice(0, 10);
        f.start_time.value = s.slice(11);
        f.end_date.value = end.slice(0, 10);
        f.end_time.value = end.slice(11);
        Array.from(f.participant_ids.options).forEach(
          (o) =>
            (o.selected =
              e.participant_ids.includes(Number(o.value)) &&
              Number(o.value) !== Number(e.advisor_id)),
        );
        context.properties = e.properties.map((p) => ({ ...p }));
        renderProperties();
      }
      if (opts.pipeline) {
        const intro = $("[data-booking-intro]", dialog);
        intro.append(
          el(
            "p",
            "Book an appointment to enter Viewing arranged. Dismissing leaves the original stage unchanged.",
          ),
        );
        if (context.client?.upcoming.length) {
          intro.append(
            el(
              "p",
              "Upcoming appointments: select one to reuse, or book another below.",
            ),
          );
          context.client.upcoming.forEach((e) => {
            intro.append(
              button("Use " + summary(e), () => reuse(e)),
              button("View / edit " + label(e), () =>
                details(e.id, { pipelineContext: { ...context } }),
              ),
            );
          });
        }
      }
      conditional();
      if (opts.focus && f[opts.focus]) f[opts.focus].focus();
      else f.type.focus();
    } catch (e) {
      error(e);
    }
  }
  async function reuse(e) {
    if (saving) return;
    const payload = {
      operation: "save",
      request_key: context.key,
      id: e.id,
      version: e.version,
      client_id: e.client_id,
      reuse: true,
      pipeline: true,
      expected_stage: context.client.stage,
      ...(context.statusValues || {}),
    };
    await performSave(payload);
  }
  function collect() {
    const f = form.elements;
    return {
      operation: "save",
      request_key: context.key,
      id: Number(f.id.value) || 0,
      version: Number(f.version.value) || 0,
      client_id: Number(f.client_id.value),
      type: f.type.value,
      title: f.title.value,
      start_local: f.date.value + "T" + f.start_time.value,
      end_local: f.end_date.value + "T" + f.end_time.value,
      timezone: f.timezone.value,
      advisor_id: Number(f.advisor_id.value),
      participant_ids: Array.from(f.participant_ids.selectedOptions)
        .map((o) => Number(o.value))
        .filter((id) => id !== Number(f.advisor_id.value)),
      property_ids: context.properties.map((p) => p.id),
      status: f.status.value,
      location_type: f.location_type.value,
      location_name: f.location_name.value,
      address: f.address.value,
      map_url: f.map_url.value,
      meeting_url: f.meeting_url.value,
      hotel_name: f.hotel_name.value,
      attendees: Number(f.attendees.value),
      internal_notes: f.internal_notes.value,
      outcome_notes: f.outcome_notes.value,
      pipeline: !!context.pipeline,
      expected_stage: context.client?.stage,
      ...(context.statusValues || {}),
    };
  }
  async function save(event) {
    event.preventDefault();
    if (saving || !form.reportValidity()) return;
    await performSave(lastPayload || collect());
  }
  async function performSave(payload) {
    if (saving) return;
    saving = true;
    lastPayload = payload;
    $$("button", dialog).forEach((b) => (b.disabled = true));
    feedback.textContent = "Saving…";
    try {
      await api(payload);
      saving = false;
      const prior = context;
      context = null;
      dialog.close();
      returnFocus?.focus();
      document.dispatchEvent(new CustomEvent("peracrm:appointment-saved"));
      if (prior.statusForm) {
        const select = $('[name="lead_pipeline_stage"]', prior.statusForm);
        if (select) select.dataset.originalStage = "viewing_arranged";
        prior.statusForm.dataset.appointmentBooked = "1";
        let status = prior.statusForm.querySelector("[data-crm-form-status]");
        if (!status) {
          status = el("p");
          status.dataset.crmFormStatus = "";
          status.setAttribute("role", "status");
          prior.statusForm.append(status);
        }
        status.textContent = "Appointment and Viewing arranged stage saved.";
      }
      refreshClient(payload.client_id, !!payload.pipeline);
      if (prior.queue?.length > 1) {
        const next = prior.queue.slice(1);
        await openBooking(next[0], { pipeline: true, queue: next });
      }
    } catch (e) {
      error(e);
      if (e.code === "conflict" && Array.isArray(e.details)) {
        e.details.forEach((c) =>
          feedback.append(
            el(
              "p",
              when(c) +
                " · " +
                (c.conflict_user_name || c.advisor_name) +
                " · " +
                cfg.types[c.type] +
                " · " +
                (c.restricted ? "Reserved" : label(c)),
            ),
          ),
        );
        feedback.append(
          button("Confirm intentional overlap", () => {
            payload.allow_overlap = true;
            performSave(payload);
          }),
        );
      }
    } finally {
      saving = false;
      $$("button", dialog).forEach((b) => (b.disabled = false));
    }
  }
  async function refreshClient(clientId, pipeline) {
    if (!$("[data-appointment-history]")) return;
    try {
      const data = await api({ operation: "refresh", client_id: clientId });
      const parsed = new DOMParser();
      for (const name of pipeline ? ["status", "timeline"] : ["timeline"]) {
        const current = $('[data-crm-panel="' + name + '"]');
        if (!current || !data[name]) continue;
        const incoming = parsed
          .parseFromString(data[name], "text/html")
          .querySelector('[data-crm-panel="' + name + '"]');
        if (incoming) {
          current.replaceWith(incoming);
          document.dispatchEvent(
            new CustomEvent("peracrm:panel-replaced", {
              detail: { panel: name, element: incoming },
            }),
          );
        }
      }
      if (pipeline)
        document.dispatchEvent(
          new CustomEvent("peracrm:workspace-panels", {
            detail: data.workspace,
          }),
        );
    } catch (e) {
      /* Booking is already committed. Existing panels remain usable; histories refresh independently. */
    }
  }
  async function details(id, opts = {}) {
    ensureDialog();
    if (!dialog.open) returnFocus = document.activeElement;
    form.hidden = true;
    const intro = $("[data-booking-intro]", dialog);
    intro.textContent = "Loading appointment…";
    if (!dialog.open) dialog.showModal();
    try {
      const e = await api({ operation: "get", id });
      intro.replaceChildren(
        el("h3", label(e)),
        el(
          "p",
          when(e, e.timezone || cfg.timezone) +
            " · " +
            (e.timezone || cfg.timezone),
        ),
        el("p", e.advisor_name + " · " + cfg.statuses[e.status]),
      );
      if (e.restricted) {
        intro.append(
          el("p", "Reserved team schedule. Client details are restricted."),
        );
        return;
      }
      if (e.participant_ids.length > 1)
        intro.append(
          el(
            "p",
            "Team: " +
              e.participant_ids
                .map(
                  (id) =>
                    cfg.staff.find((u) => Number(u.id) === id)?.name ||
                    "Employee",
                )
                .join(", "),
          ),
        );
      const link = el("a", "Open client");
      link.href = e.client_url;
      intro.append(link);
      [
        "location_name",
        "address",
        "hotel_name",
        "internal_notes",
        "outcome_notes",
      ].forEach((k) => {
        if (e[k]) intro.append(el("p", k.replaceAll("_", " ") + ": " + e[k]));
      });
      ["map_url", "meeting_url"].forEach((k) => {
        if (e[k]) {
          const a = el(
            "a",
            k === "map_url" ? "Open map" : "Join online meeting",
          );
          a.href = e[k];
          a.target = "_blank";
          a.rel = "noopener noreferrer";
          intro.append(a);
        }
      });
      e.properties.forEach((p) => {
        const a = el("a", p.title);
        a.href = p.url;
        intro.append(a);
      });
      if (e.can_manage) {
        const options = { ...opts.pipelineContext, row: e };
        intro.append(
          button("Edit", () => openBooking(e.client_id, options)),
          button("Reschedule", () =>
            openBooking(e.client_id, { ...options, focus: "date" }),
          ),
          button("Cancel appointment", async () => {
            await openBooking(e.client_id, options);
            form.elements.status.value = "cancelled";
          }),
          button("Complete", async () => {
            await openBooking(e.client_id, {
              ...options,
              focus: "outcome_notes",
            });
            form.elements.status.value = "completed";
          }),
        );
      }
    } catch (e) {
      intro.textContent = e.message;
    }
  }
  document.addEventListener("click", (e) => {
    const b = e.target.closest("[data-appointment-new]");
    if (b) {
      e.preventDefault();
      openBooking(Number(b.dataset.appointmentNew));
    }
  });
  // Capture before the existing delegated AJAX handler, keeping the status form intact.
  document.addEventListener(
    "submit",
    (e) => {
      const sf = e.target.closest('form[data-crm-ajax-form="status"]');
      if (!sf) return;
      const stage = $('[name="lead_pipeline_stage"]', sf);
      if (!stage) return;
      if (
        stage.value === "viewing_arranged" &&
        stage.dataset.originalStage !== "viewing_arranged" &&
        sf.dataset.appointmentBooked !== "1"
      ) {
        e.preventDefault();
        e.stopImmediatePropagation();
        const values = {};
        ["engagement_state", "disposition", "peracrm_client_type"].forEach(
          (k) => {
            const input = $('[name="' + k + '"]', sf);
            if (input)
              values[k === "peracrm_client_type" ? "client_type" : k] =
                input.value;
          },
        );
        openBooking(Number($('[name="peracrm_client_id"]', sf).value), {
          pipeline: true,
          statusForm: sf,
          statusValues: values,
          originalStage: stage.dataset.originalStage,
        });
      }
    },
    true,
  );
  function rememberStages() {
    $$(
      'form[data-crm-ajax-form="status"] [name="lead_pipeline_stage"]',
    ).forEach((s) => {
      if (!s.dataset.originalStage) s.dataset.originalStage = s.value;
    });
  }
  rememberStages();
  document.addEventListener("peracrm:panel-replaced", rememberStages);
  // Open booking immediately on selecting the transition, not only on submit.
  document.addEventListener("change", (e) => {
    const s = e.target;
    if (
      s.name !== "lead_pipeline_stage" ||
      s.value !== "viewing_arranged" ||
      s.dataset.originalStage === "viewing_arranged"
    )
      return;
    const sf = s.closest('form[data-crm-ajax-form="status"]');
    if (sf) sf.requestSubmit();
  });
  async function histories() {
    for (const container of $$("[data-appointment-history]")) {
      let offset = 0;
      container.replaceChildren();
      const list = el("div");
      container.append(list);
      const more = button("Older appointments", load);
      container.append(more);
      async function load() {
        more.disabled = true;
        try {
          const result = await api({
            operation: "history",
            client_id: Number(container.dataset.appointmentHistory),
            offset,
          });
          if (!offset && !result.items.length)
            list.append(el("p", "No appointments yet."));
          result.items.forEach((e) => {
            const row = el("div", undefined, "appointment-history-row");
            row.append(eventButton(e, cfg.timezone));
            if (e.location_name) row.append(el("span", e.location_name));
            if (e.properties.length)
              row.append(
                el("span", e.properties.map((p) => p.title).join(" → ")),
              );
            list.append(row);
          });
          offset += result.items.length;
          more.hidden = !result.more;
        } catch (e) {
          list.append(el("p", e.message));
        } finally {
          more.disabled = false;
        }
      }
      load();
    }
  }
  histories();
  document.addEventListener("peracrm:appointment-saved", histories);
  async function upcoming() {
    const node = $("[data-appointment-upcoming]");
    if (!node) return;
    try {
      const now = local(new Date().toISOString(), cfg.timezone);
      const result = await api({
        operation: "list",
        start: now,
        end: addDays(now, 90) + "T00:00",
        timezone: cfg.timezone,
        advisor_id: Number(node.dataset.appointmentUpcoming),
      });
      node.replaceChildren();
      const events = result.items
        .filter((e) => ["confirmed", "tentative"].includes(e.status))
        .slice(0, 5);
      if (!events.length) node.append(el("p", "No upcoming appointments."));
      events.forEach((e) => {
        const row = el("div", undefined, "appointment-history-row");
        row.append(eventButton(e, cfg.timezone));
        if (e.location_name) row.append(el("span", e.location_name));
        node.append(row);
      });
    } catch (e) {
      node.textContent = e.message;
    }
  }
  upcoming();
  document.addEventListener("peracrm:appointment-saved", upcoming);
  const calendar = $("[data-appointment-calendar]");
  if (calendar) {
    const filters = $("[data-calendar-filters]", calendar),
      f = filters.elements,
      content = $("[data-calendar-content]", calendar),
      message = $("[data-calendar-message]", calendar);
    let events = [],
      current,
      sequence = 0,
      clientId = 0;
    cfg.staff.forEach((u) => options(f.advisor_id, { [u.id]: u.name }));
    f.date.value = local(new Date().toISOString(), cfg.timezone).slice(0, 10);
    async function load() {
      const n = ++sequence;
      current = period(f.date.value, f.view.value);
      message.textContent = "Loading…";
      $("[data-calendar-heading]", calendar).textContent =
        current.start + " – " + addDays(current.end, -1);
      try {
        let offset = 0,
          all = [],
          result;
        do {
          result = await api({
            operation: "list",
            start: current.start + "T00:00",
            end: current.end + "T00:00",
            timezone: f.timezone.value,
            advisor_id: Number(f.advisor_id.value),
            client_id: clientId,
            type: f.type.value,
            status: f.status.value,
            offset,
          });
          if (n !== sequence) return;
          all.push(...result.items);
          offset += result.items.length;
        } while (result.more);
        events = all;
        render();
        message.textContent =
          events.length + " appointments · " + f.timezone.value;
      } catch (e) {
        if (n === sequence) {
          content.replaceChildren();
          message.textContent = e.message;
        }
      }
    }
    function render() {
      content.replaceChildren();
      const mobile = matchMedia("(max-width: 680px)").matches;
      const view = f.view.value;
      if (view === "list" || mobile) {
        const list = el("div", undefined, "appointment-list");
        events.forEach((e) => {
          const row = el("div", undefined, "appointment-history-row");
          row.append(eventButton(e, f.timezone.value));
          if (e.location_name) row.append(el("span", e.location_name));
          list.append(row);
        });
        if (!events.length)
          list.append(el("p", "No appointments in this period."));
        content.append(list);
        return;
      }
      if (view === "month") {
        const grid = el("div", undefined, "appointment-month");
        const start = addDays(
          current.start,
          -((day(current.start).getUTCDay() + 6) % 7),
        );
        ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].forEach((d) =>
          grid.append(el("div", d, "appointment-month-heading")),
        );
        for (
          let date = start;
          date < current.end || day(date).getUTCDay() !== 1;
          date = addDays(date, 1)
        ) {
          const cell = el("section", undefined, "appointment-month-day");
          cell.append(el("h3", date.slice(5)));
          events
            .filter(
              (e) =>
                local(e.start_at, f.timezone.value).slice(0, 10) <= date &&
                local(e.end_at, f.timezone.value).slice(0, 10) >= date,
            )
            .forEach((e) =>
              cell.append(eventButton(e, f.timezone.value, true)),
            );
          grid.append(cell);
        }
        content.append(grid);
        return;
      }
      const days = [];
      for (
        let date = current.start;
        date < current.end;
        date = addDays(date, 1)
      )
        days.push(date);
      const wrap = el("div", undefined, "appointment-time-scroll");
      const grid = el("div", undefined, "appointment-time-grid");
      grid.style.setProperty("--days", days.length);
      const times = el("div", undefined, "appointment-hours");
      times.append(el("div", "Time", "appointment-day-heading"));
      for (let hour = 0; hour < 24; hour++)
        times.append(
          el("div", String(hour).padStart(2, "0") + ":00", "appointment-hour"),
        );
      grid.append(times);
      days.forEach((date) => {
        const col = el("section", undefined, "appointment-day");
        col.append(el("h3", date.slice(5), "appointment-day-heading"));
        const slots = el("div", undefined, "appointment-slots");
        for (let i = 0; i < 24; i++)
          slots.append(el("div", undefined, "appointment-slot"));
        layout(events, date, f.timezone.value).forEach((item) => {
          const b = eventButton(item.event, f.timezone.value, true);
          b.classList.add("appointment-positioned");
          b.style.top = (item.start / 60) * 72 + "px";
          b.style.height =
            Math.max(24, ((item.end - item.start) / 60) * 72) + "px";
          b.style.left = (item.column / item.columns) * 100 + "%";
          b.style.width = 100 / item.columns + "%";
          slots.append(b);
        });
        col.append(slots);
        grid.append(col);
      });
      wrap.append(grid);
      content.append(wrap);
      wrap.scrollTop = 8 * 72;
    }
    function navigate(direction) {
      if (["month", "list"].includes(f.view.value)) {
        const d = day(f.date.value);
        d.setUTCDate(1);
        d.setUTCMonth(d.getUTCMonth() + direction);
        f.date.value = dateKey(d);
      } else
        f.date.value = addDays(
          f.date.value,
          direction * (f.view.value === "week" ? 7 : 1),
        );
      load();
    }
    $("[data-calendar-prev]", calendar).addEventListener("click", () =>
      navigate(-1),
    );
    $("[data-calendar-next]", calendar).addEventListener("click", () =>
      navigate(1),
    );
    $("[data-calendar-today]", calendar).addEventListener("click", () => {
      f.date.value = local(new Date().toISOString(), f.timezone.value).slice(
        0,
        10,
      );
      load();
    });
    filters.addEventListener("submit", (e) => e.preventDefault());
    filters.addEventListener("change", (e) => {
      if (e.target.name !== "client_search" && f.date.value) load();
    });
    search(
      f.client_search,
      "client",
      $("[data-calendar-client-results]", calendar),
      (item) => {
        clientId = item.id;
        f.client_search.value = item.title;
        $("[data-calendar-clear-client]", calendar).hidden = false;
        load();
      },
    );
    $("[data-calendar-clear-client]", calendar).addEventListener(
      "click",
      () => {
        clientId = 0;
        f.client_search.value = "";
        $("[data-calendar-clear-client]", calendar).hidden = true;
        load();
      },
    );
    matchMedia("(max-width: 680px)").addEventListener("change", () => render());
    document.addEventListener("peracrm:appointment-saved", load);
    load();
    const query = new URLSearchParams(location.search);
    const id = Number(query.get("appointment"));
    if (id) details(id);
    const book = Number(query.get("book_client"));
    if (book)
      openBooking(book, {
        pipeline: true,
        queue: (query.get("booking_queue") || String(book))
          .split(",")
          .map(Number)
          .filter(Number.isInteger),
      });
  }
})(typeof window !== "undefined" ? window : globalThis);
