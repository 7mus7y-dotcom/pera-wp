"use strict";
const assert = require("node:assert/strict");
const {
  period,
  local,
  layout,
  addDays,
} = require("../assets/frontend/appointments.js");
assert.deepEqual(period("2026-11-09", "week"), {
  start: "2026-11-09",
  end: "2026-11-16",
});
assert.deepEqual(period("2026-11-15", "week"), {
  start: "2026-11-09",
  end: "2026-11-16",
});
assert.deepEqual(period("2026-12-31", "month"), {
  start: "2026-12-01",
  end: "2027-01-01",
});
assert.deepEqual(period("2028-02-29", "list"), {
  start: "2028-02-01",
  end: "2028-03-01",
});
assert.deepEqual(period("2026-03-29", "day"), {
  start: "2026-03-29",
  end: "2026-03-30",
});
assert.equal(addDays("2026-12-31", 1), "2027-01-01");
assert.equal(
  local("2026-11-09T21:30:00Z", "Europe/Istanbul"),
  "2026-11-10T00:30",
);
assert.equal(
  local("2026-03-29T00:30:00Z", "Europe/London"),
  "2026-03-29T00:30",
);
assert.equal(
  local("2026-03-29T01:30:00Z", "Europe/London"),
  "2026-03-29T02:30",
);
assert.equal(
  local("2026-11-01T05:30:00Z", "America/New_York"),
  "2026-11-01T01:30",
);
const event = (id, start, end) => ({
  id,
  start_at: `2026-11-09T${start}:00Z`,
  end_at: `2026-11-09T${end}:00Z`,
});
const slots = layout(
  [
    event(1, "07:00", "08:00"),
    event(2, "07:30", "08:30"),
    event(3, "08:30", "09:00"),
  ],
  "2026-11-09",
  "Europe/Istanbul",
);
assert.equal(slots[0].start, 600);
assert.equal(slots[0].columns, 2);
assert.equal(slots[1].column, 1);
assert.equal(slots[2].columns, 1);
assert.equal(
  layout([event(1, "07:00", "08:00")], "2026-11-10", "Europe/Istanbul").length,
  0,
);
const overnight = layout(
  [{ id: 1, start_at: "2026-11-09T20:30:00Z", end_at: "2026-11-09T22:30:00Z" }],
  "2026-11-10",
  "Europe/Istanbul",
);
assert.equal(overnight[0].start, 0);
assert.equal(overnight[0].end, 90);
console.log(
  "appointments JS: 18 date, timezone, overlap and boundary assertions passed",
);
