# ML-PROP-010: advisor position remediation

The property template reads its `advisors` ACF relationship from the Property
post. Each selected relationship value resolves to a `team` custom post type;
when none is selected, the template queries published `team` posts whose
`advisor` ACF flag is enabled. The displayed role is the Team post's scalar,
free-text `position` ACF field. It is also displayed by the public Team cards on
the About page. Name, contact details, photo and the advisor flag are not part of
this focused editorial contract.

`team.position` is therefore the only Team-owned scalar approved by Pera
Multilingual. Normal ACF formatting hooks serve both public renderers, while the
canonical, unformatted English ACF value remains the source used for translation
storage and stale detection. Missing, stale, failed or blank translations fall
back to that English value. Translation Health inventories a non-empty position
for Chinese, Arabic and German, and omits empty positions.

By project decision, attachment, image, gallery and floor-plan alt text—and media
metadata generally—do not require translation. They are intentionally excluded
from the field contract and Translation Health. Existing canonical English alt
attributes and accessibility behaviour are unaffected.

No other Team copy is rendered in the property advisor card. The About Team card
also displays a biography/description, but that broader Team-page translation
work is unrelated to ML-PROP-010 and remains deferred.
