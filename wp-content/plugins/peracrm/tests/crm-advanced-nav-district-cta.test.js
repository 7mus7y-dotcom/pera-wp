'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
const nav = fs.readFileSync(path.join(__dirname, '../inc/views/partials/crm-side-nav.php'), 'utf8');
const crmCss = fs.readFileSync(path.join(__dirname, '../assets/frontend/crm.css'), 'utf8');
const property = fs.readFileSync(path.join(root, 'themes/hello-elementor-child/single-property.php'), 'utf8');

// Advanced links remain capability-gated and exist in both menu presentations.
assert.match(nav, /if \( \$can_view_logs \) \{/);
assert.match(nav, /current_user_can\( 'edit_posts' \)/);
assert.match(nav, /home_url\( '\/manage\/' \)/);
assert.equal((nav.match(/class="crm-side-nav__advanced-details"/g) || []).length, 2);
assert.match(nav, /<details class="crm-side-nav__advanced-details"/);
assert.match(crmCss, /\.crm-side-nav__advanced-summary:focus-visible/);

// Owner CTA uses taxonomy imagery, falling back to the existing approved asset.
assert.match(property, /get_field\( 'district_image', 'district_' \. \$district_term->term_id \)/);
assert.match(property, /pera_get_term_featured_image_id/);
assert.match(property, /\$owner_cta_image_id = 55686;/);
assert.match(property, /wp_get_attachment_image_url\( \$owner_cta_image_id, 'pera-card' \)/);
console.log('PASS Advanced navigation + owner CTA district image assertions');
