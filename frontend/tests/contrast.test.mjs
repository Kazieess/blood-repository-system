import test from 'node:test';
import assert from 'node:assert/strict';
function luminance(hex) {
  const parts = hex.replace('#', '').match(/../g).map(part => parseInt(part, 16) / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
  return parts[0] * .2126 + parts[1] * .7152 + parts[2] * .0722;
}
const pairs = [
  ['Main text', '#132938', '#ffffff'], ['Muted text', '#596873', '#ffffff'],
  ['Muted text on paper', '#596873', '#f7f3ee'], ['Primary button', '#ffffff', '#b5262d'],
  ['Eligible status', '#246148', '#edf6ef'], ['Review status', '#80551b', '#fcf2df'],
  ['Danger status', '#9b2028', '#fbefef'], ['Hero dark panel', '#d8e0e2', '#132938'],
];
for (const [label, foreground, background] of pairs) test(`${label} contrast is at least 4.5:1`, () => {
  const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
  const ratio = (values[0] + .05) / (values[1] + .05);
  assert.ok(ratio >= 4.5, `${label}: ${ratio.toFixed(2)}:1`);
});
