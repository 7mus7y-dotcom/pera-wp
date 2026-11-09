"use strict";
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const root = path.resolve(__dirname, "..");
const js = fs.readFileSync(path.join(root, "assets/frontend/appointments.js"), "utf8");
const css = fs.readFileSync(path.join(root, "assets/frontend/appointments.css"), "utf8");
const service = fs.readFileSync(path.join(root, "inc/services/appointment_service.php"), "utf8");
const api = fs.readFileSync(path.join(root, "inc/appointments.php"), "utf8");
const current = js.slice(js.indexOf("  async function histories() {"), js.indexOf("  histories();",js.indexOf("  async function histories() {")));
const details = js.slice(js.indexOf("  async function details("),js.indexOf('  document.addEventListener("click",',js.indexOf("  async function details(")));

assert.ok(current.startsWith("  async function histories()"), "history function exists");
assert.ok(!current.includes('"Older appointments"'), "redundant load-more button removed");
assert.match(current, /while \(more\)/, "history loads subsequent pages automatically");
assert.match(current, /offset \+= items\.length/, "pagination advances by received count");
assert.match(current, /!!result\.more && items\.length > 0/, "empty pages stop pagination");
assert.match(current, /historyRequests\.get\(container\) !== request/, "outdated requests do not append duplicate rows");
assert.equal((current.match(/"No appointments yet\."/g)||[]).length, 1, "single empty state per refresh");
assert.match(current, /if \(!rows\.length\)/, "empty state is conditional on final collection");
assert.match(current, /row\.append\(eventButton\(e, cfg\.timezone\)\)/, "existing clickable detail summary retained");
assert.match(current, /Array\.isArray\(e\.properties\)/, "linked properties safely rendered");
assert.match(details, /if \(e\.can_manage\)/, "edit actions retain authorization");
assert.match(details, /actions\.append\(button\("Edit"/, "edit offered for managed appointments");
assert.match(details, /if \(\["confirmed", "tentative"\]\.includes\(e\.status\)\)/, "lifecycle actions only for active appointments");
for (const label of ["Reschedule", "Cancel appointment", "Complete"]) {
  assert.ok(details.indexOf('button("'+label+'"') > details.indexOf('if (["confirmed", "tentative"].includes(e.status))'),
    label+" button guarded by active status");
}
assert.match(service, /'completed'=>'Completed'/, "completed backend status exists");
assert.match(service, /'no_show'=>'No-show'/, "no-show backend status exists");
assert.match(api, /if \(\$operation==='history'\)/, "history API retained");
assert.match(api, /OFFSET %d/, "server paging supported");
assert.match(css, /\.appointment-detail-actions\s*\{[\s\S]*?display: flex;[\s\S]*?gap: 8px;/, "detail buttons spaced in flex row");
assert.match(css, /\.appointment-detail-actions > \.btn\s*\{[\s\S]*?margin: 0;/, "detail buttons do not inherit stray margins");
console.log("PASS CRM appointment history/active-status action regression.");
