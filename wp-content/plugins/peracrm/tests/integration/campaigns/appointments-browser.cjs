"use strict";
const assert = require("node:assert/strict");
module.exports = async function ({ browser, evidence = "/evidence" }) {
  const context = await browser.newContext({
      viewport: { width: 1440, height: 1000 },
    }),
    page = await context.newPage();
  const fixtureResponse = await page.request.get(
    (process.env.APPOINTMENTS_TARGET || "http://fixture.test") +
      "/appointments-fixture.json",
  );
  const fixture = await fixtureResponse.json();
  const target = fixture.target.replace(/\/$/, "");
  page.setDefaultTimeout(15000);
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await page.goto(target + "/wp-login.php");
  await page.locator("#user_login").fill("fixture-admin");
  await page.locator("#user_pass").fill("fixture-admin-only");
  await page.locator("#wp-submit").click();
  await page.goto(target + "/crm/appointments/");
  await page
    .locator("[data-calendar-message]")
    .filter({ hasText: /appointments ·/ })
    .waitFor();
  await page.locator('[name="date"]').fill("2026-11-10");
  await page.locator('[name="date"]').dispatchEvent("change");
  await page.locator(".appointment-positioned").first().waitFor();
  for (const view of ["day", "week", "month", "list"]) {
    await page
      .locator('[data-calendar-filters] [name="view"]')
      .selectOption(view);
    await page
      .locator("[data-calendar-message]")
      .filter({ hasText: /appointments ·/ })
      .waitFor();
    const selector = {
      day: ".appointment-time-grid",
      week: ".appointment-time-grid",
      month: ".appointment-month",
      list: ".appointment-list",
    }[view];
    await page.locator(selector).waitFor();
  }
  await page.locator("[data-calendar-prev]").click();
  await page.locator("[data-calendar-next]").click();
  await page.locator("[data-calendar-today]").click();
  await page
    .getByRole("button", { name: "New appointment", exact: true })
    .click();
  const dialog = page.locator("dialog[open]"),
    form = dialog.locator("form");
  await form.locator('[name="client_search"]').fill("Appointment client A");
  await dialog.locator("[data-client-results] button").first().click();
  await form.locator('[name="type"]').selectOption("property_tour");
  await form.locator('[name="title"]').fill("Browser appointment");
  await form.locator('[name="date"]').fill("2026-11-15");
  await form.locator('[name="end_date"]').fill("2026-11-15");
  await form.locator('[name="start_time"]').fill("15:00");
  await form.locator('[name="end_time"]').fill("16:00");
  await form.locator('[name="property_search"]').fill("Synthetic property A");
  await form.locator("[data-property-results] button").first().click();
  await form.locator('[name="location_type"]').selectOption("hotel");
  assert(await form.locator('[name="hotel_name"]').isVisible());
  assert(!(await form.locator('[name="meeting_url"]').isVisible()));
  await form.locator('[name="location_type"]').selectOption("online");
  assert(await form.locator('[name="meeting_url"]').isVisible());
  await form.locator('[name="meeting_url"]').fill("https://example.org/meet");
  await dialog.evaluate(d=>d.scrollTop=0);
  await page.screenshot({path:evidence+"/appointments-booking-desktop.png",fullPage:false});
  await page.setViewportSize({ width: 390, height: 844 });
  assert(
    !(await page.evaluate(
      () => document.documentElement.scrollWidth > innerWidth + 1,
    )),
  );
  await dialog.evaluate(d=>d.scrollTop=0);
  await page.screenshot({path:evidence+"/appointments-booking-mobile.png",fullPage:false});
  await page.setViewportSize({ width: 1440, height: 1000 });
  let fail = true;
  await page.route("**/admin-ajax.php", async (route) => {
    const payload = route.request().postData() || "";
    if (
      fail &&
      payload.includes("peracrm_appointments") &&
      payload.includes("save")
    ) {
      fail = false;
      await route.abort();
    } else await route.continue();
  });
  await form
    .getByRole("button", { name: "Save appointment", exact: true })
    .click();
  await form
    .locator(".appointment-feedback")
    .filter({ hasText: /fetch|network/i })
    .waitFor();
  assert.equal(
    await form.locator('[name="title"]').inputValue(),
    "Browser appointment",
  );
  await form
    .getByRole("button", { name: "Save appointment", exact: true })
    .click();
  await page.waitForFunction(
    () =>
      !document.querySelector("dialog[open]") ||
      [...document.querySelectorAll("dialog button")].some(
        (b) => b.textContent === "Confirm intentional overlap",
      ),
  );
  if (
    await dialog
      .getByRole("button", { name: "Confirm intentional overlap", exact: true })
      .count()
  )
    await dialog
      .getByRole("button", { name: "Confirm intentional overlap", exact: true })
      .click();
  await page.waitForFunction(() => !document.querySelector("dialog[open]"));
  await page.goto(target + "/crm/client/" + fixture.client + "/");
  await page
    .locator("[data-appointment-history] .appointment-event")
    .first()
    .waitFor();
  await page
    .locator("[data-appointment-history] .appointment-event")
    .filter({ hasText: "Browser appointment" })
    .first()
    .click();
  await dialog.getByRole("button", { name: "Edit", exact: true }).click();
  await form.locator('[name="internal_notes"]').fill("Browser edit retained");
  await form
    .getByRole("button", { name: "Save appointment", exact: true })
    .click();
  await page.waitForFunction(
    () =>
      !document.querySelector("dialog[open]") ||
      [...document.querySelectorAll("dialog button")].some(
        (b) => b.textContent === "Confirm intentional overlap",
      ),
  );
  if (
    await dialog
      .getByRole("button", { name: "Confirm intentional overlap", exact: true })
      .count()
  )
    await dialog
      .getByRole("button", { name: "Confirm intentional overlap", exact: true })
      .click();
  await page.waitForFunction(() => !document.querySelector("dialog[open]"));
  await page
    .locator('select[name="lead_pipeline_stage"]')
    .selectOption("viewing_arranged");
  await dialog.waitFor();
  await dialog.getByRole("button", { name: "Dismiss", exact: true }).click();
  assert.equal(
    await page.locator('select[name="lead_pipeline_stage"]').inputValue(),
    "qualified",
  );
  await page
    .locator('select[name="lead_pipeline_stage"]')
    .selectOption("viewing_arranged");
  await dialog.waitFor();
  await dialog.getByRole("button", { name: /^Use / }).first().click();
  await page.waitForFunction(() => !document.querySelector("dialog[open]"));
  await page.reload();
  assert.equal(
    await page.locator('select[name="lead_pipeline_stage"]').inputValue(),
    "viewing_arranged",
  );
  // Optional panel never owns the only create action.
  assert(
    await page
      .locator(".crm-summary-header [data-appointment-new]")
      .isVisible(),
  );
  await page.goto(target + "/crm/appointments/");
  await page
    .locator('[data-calendar-filters] [name="date"]')
    .fill("2026-11-10");
  await page
    .locator('[data-calendar-filters] [name="date"]')
    .dispatchEvent("change");
  for (const [name, width, height, dark] of [
    ["desktop", 1440, 1000, false],
    ["tablet", 820, 1000, false],
    ["mobile", 390, 844, false],
    ["mobile-dark", 390, 844, true],
  ]) {
    await page.setViewportSize({ width, height });
    await page.emulateMedia({ colorScheme: dark ? "dark" : "light" });
    await page
      .locator("[data-calendar-message]")
      .filter({ hasText: /appointments ·/ })
      .waitFor();
    assert(
      !(await page.evaluate(
        () => document.documentElement.scrollWidth > innerWidth + 1,
      )),
      name + " horizontal overflow",
    );
    await page.screenshot({
      path: evidence + "/appointments-" + name + ".png",
      fullPage: true,
    });
  }
  await page
    .getByRole("button", { name: "New appointment", exact: true })
    .click();
  await page.keyboard.press("Escape");
  await page.waitForFunction(() => !document.querySelector("dialog[open]"));
  assert(
    await page
      .getByRole("button", { name: "New appointment", exact: true })
      .evaluate((b) => b === document.activeElement),
  );
  const employeeContext = await browser.newContext(),
    employeePage = await employeeContext.newPage();
  await employeePage.goto(target + "/wp-login.php");
  await employeePage.locator("#user_login").fill("employee-a");
  await employeePage.locator("#user_pass").fill("synthetic-appointments-only");
  await employeePage.locator("#wp-submit").click();
  await employeePage.goto(target + "/crm/appointments/");
  await employeePage
    .locator('[data-calendar-filters] [name="date"]')
    .fill("2026-11-10");
  await employeePage
    .locator('[data-calendar-filters] [name="date"]')
    .dispatchEvent("change");
  await employeePage
    .locator("[data-calendar-message]")
    .filter({ hasText: /appointments ·/ })
    .waitFor();
  assert(
    !(
      await employeePage.locator("[data-calendar-content]").innerText()
    ).includes("SECRET"),
  );
  const endpoint = await employeePage.evaluate(async () => {
    async function request(payload, nonce = window.peraAppointments.nonce) {
      const cfg = window.peraAppointments,
        b = new FormData();
      b.set("action", "peracrm_appointments");
      b.set("nonce", nonce);
      b.set("payload", JSON.stringify(payload));
      const r = await fetch(cfg.url, { method: "POST", body: b });
      return { status: r.status, data: await r.json() };
    }
    const list = await request({
      operation: "list",
      start: "2026-11-09T00:00",
      end: "2026-11-16T00:00",
    });
    const restricted = list.data.data.items.find((e) => e.restricted);
    const badNonce = await request(
      { operation: "get", id: restricted.id },
      "bad",
    );
    const projected = await request({ operation: "get", id: restricted.id });
    const write = await request({
      operation: "save",
      client_id: 999999,
      request_key: "unauthorized-request-key",
    });
    return { badNonce, projected, write };
  });
  assert.equal(endpoint.badNonce.status, 403);
  assert.equal(endpoint.write.status, 403);
  assert.equal(endpoint.projected.data.data.restricted, true);
  assert(!JSON.stringify(endpoint.projected.data).includes("SECRET"));
  assert(!("client_id" in endpoint.projected.data.data));
  await employeeContext.close();
  assert.deepEqual(errors, []);
  await context.close();
  console.log(
    "PASS appointments browser: four views, navigation, booking, conditional fields, property search, failed-save retention, pipeline dismiss/reuse, focus, responsive/dark screenshots",
  );
};
